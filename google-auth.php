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
function getGoogleAccessToken() {
    // If we have a valid, non-expired token in the session, use it
    if (isset($_SESSION['google_access_token']) && $_SESSION['google_token_expiry'] > time()) {
        return $_SESSION['google_access_token'];
    }

    $key_file_path = __DIR__ . '/service-account-key.json';
    if (!file_exists($key_file_path)) {
        error_log("Service account key file not found: " . $key_file_path);
        return null;
    }
    $key_file_content = json_decode(file_get_contents($key_file_path), true);
    if (!$key_file_content) {
        error_log("Failed to parse service account key file.");
        return null;
    }

    $private_key = $key_file_content['private_key'];
    $client_email = $key_file_content['client_email'];
    $token_uri = $key_file_content['token_uri'];

    // Create the JWT header
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $header = base64url_encode($header);

    // Create the JWT payload
    $now = time();
    $payload = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => $token_uri,
        'exp' => $now + 3600, // Token valid for 1 hour
        'iat' => $now,
    ]);
    $payload = base64url_encode($payload);

    // Sign the JWT
    $signature = '';
    openssl_sign($header . '.' . $payload, $signature, $private_key, 'sha256');
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
    $response_text = curl_exec($ch);
    curl_close($ch);

    $response_json = json_decode($response_text, true);

    if (isset($response_json['access_token'])) {
        // Cache the new token and its expiry time in the session
        $_SESSION['google_access_token'] = $response_json['access_token'];
        $_SESSION['google_token_expiry'] = time() + $response_json['expires_in'] - 30; // Subtract 30s buffer
        return $response_json['access_token'];
    }

    error_log("Failed to get Google access token: " . $response_text);
    return null;
}

/**
 * A helper function for base64 URL encoding.
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

?>