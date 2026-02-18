<?php
// This file handles the authentication with the Google API using a service account.

// Start a session to cache the access token
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

/**
 * Gets a Google API access token, using a cached one if available and still valid.
 *
 * @return string|null The access token, or null on failure.
 */
function getGoogleAccessToken()
{
    // If we have a valid, non-expired token in the session, use it
    if (isset($_SESSION['google_access_token']) && $_SESSION['google_token_expiry'] > time()) {
        return $_SESSION['google_access_token'];
    }

    $key_file_path = __DIR__ . '/service-account-key.json';

    if (!file_exists($key_file_path)) {
        error_log("❌ Service account key file not found: " . $key_file_path);
        return null;
    }

    if (!is_readable($key_file_path)) {
        error_log("❌ Service account key file is not readable: " . $key_file_path);
        return null;
    }

    $key_file_content = json_decode(file_get_contents($key_file_path), true);

    if (!$key_file_content) {
        error_log("❌ Failed to parse service account key file. JSON error: " . json_last_error_msg());
        return null;
    }

    if (
        !isset($key_file_content['private_key']) ||
        !isset($key_file_content['client_email']) ||
        !isset($key_file_content['token_uri'])
    ) {
        error_log("❌ Service account key file is missing required fields");
        return null;
    }

    // ✅ FIX: Handle both escaped and literal newlines
    $private_key = $key_file_content['private_key'];
    // Replace literal \n string with actual newline
    $private_key = str_replace('\n', "\n", $private_key);

    $client_email = $key_file_content['client_email'];
    $token_uri = $key_file_content['token_uri'];

    // ✅ Validate private key format
    if (strpos($private_key, '-----BEGIN PRIVATE KEY-----') === false) {
        error_log("❌ Invalid private key format - missing BEGIN marker");
        error_log("Key preview: " . substr($private_key, 0, 100));
        return null;
    }

    error_log("🔑 Attempting Google auth with email: " . $client_email);

    // Create the JWT header
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $header = base64url_encode($header);

    // Create the JWT payload
    $now = time();
    $payload = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => $token_uri,
        'exp' => $now + 3600,
        'iat' => $now,
    ]);
    $payload = base64url_encode($payload);

    // Sign the JWT
    $signature = '';
    $sign_result = openssl_sign($header . '.' . $payload, $signature, $private_key, 'sha256');

    if (!$sign_result) {
        error_log("❌ Failed to sign JWT. OpenSSL error: " . openssl_error_string());
        return null;
    }

    $signature = base64url_encode($signature);
    $jwt = $header . '.' . $payload . '.' . $signature;

    // Send the request for an access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_uri);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response_text = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error) {
        error_log("❌ cURL error: " . $curl_error);
        return null;
    }

    error_log("📡 Google OAuth HTTP response code: " . $http_code);

    $response_json = json_decode($response_text, true);

    if (isset($response_json['access_token'])) {
        $_SESSION['google_access_token'] = $response_json['access_token'];
        $_SESSION['google_token_expiry'] = time() + $response_json['expires_in'] - 30;

        error_log("✅ Google access token obtained successfully");
        return $response_json['access_token'];
    }

    error_log("❌ Failed to get Google access token.");
    error_log("HTTP Code: " . $http_code);
    error_log("Response: " . $response_text);

    if (isset($response_json['error'])) {
        error_log("Error type: " . $response_json['error']);
        if (isset($response_json['error_description'])) {
            error_log("Error description: " . $response_json['error_description']);
        }
    }

    return null;
}

/**
 * A helper function for base64 URL encoding.
 */
function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

?>