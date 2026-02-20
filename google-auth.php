<?php
// Google API authentication using service account

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
    // Return cached token if still valid
    if (isset($_SESSION['google_access_token']) && $_SESSION['google_token_expiry'] > time()) {
        return $_SESSION['google_access_token'];
    }

    $key_file_path = __DIR__ . '/service-account-key.json';

    // Validate key file
    if (!file_exists($key_file_path)) {
        logCritical('GOOGLE_AUTH', 'Service account key file not found', [
            'path' => $key_file_path
        ]);
        return null;
    }

    if (!is_readable($key_file_path)) {
        logCritical('GOOGLE_AUTH', 'Service account key file is not readable', [
            'path' => $key_file_path
        ]);
        return null;
    }

    $key_file_content = json_decode(file_get_contents($key_file_path), true);

    if (!$key_file_content) {
        logCritical('GOOGLE_AUTH', 'Failed to parse service account key file', [
            'json_error' => json_last_error_msg()
        ]);
        return null;
    }

    // Validate required fields
    if (!isset($key_file_content['private_key']) ||
        !isset($key_file_content['client_email']) ||
        !isset($key_file_content['token_uri'])) {
        logCritical('GOOGLE_AUTH', 'Service account key missing required fields', [
            'has_private_key' => isset($key_file_content['private_key']),
            'has_client_email' => isset($key_file_content['client_email']),
            'has_token_uri' => isset($key_file_content['token_uri'])
        ]);
        return null;
    }

    // Extract credentials
    $private_key = $key_file_content['private_key'];
    $client_email = $key_file_content['client_email'];
    $token_uri = $key_file_content['token_uri'];

    // Validate private key format
    if (strpos($private_key, '-----BEGIN PRIVATE KEY-----') === false) {
        logCritical('GOOGLE_AUTH', 'Invalid private key format', [
            'key_length' => strlen($private_key)
        ]);
        return null;
    }

    // Create JWT header
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $header = base64url_encode($header);

    // Create JWT payload
    $now = time();
    $iat = $now - 30;  // Issue time: 30 seconds ago (clock skew buffer)
    $exp = $now + 3600; // Expiry: 1 hour from now

    $payload = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => $token_uri,
        'exp' => $exp,
        'iat' => $iat,
    ]);
    $payload = base64url_encode($payload);

    // Sign the JWT
    $signature = '';
    $sign_result = openssl_sign(
        $header . '.' . $payload,
        $signature,
        $private_key,
        OPENSSL_ALGO_SHA256
    );

    if (!$sign_result) {
        $errors = '';
        while ($msg = openssl_error_string()) {
            $errors .= $msg . '; ';
        }
        logError('GOOGLE_AUTH', 'Failed to sign JWT', [
            'openssl_errors' => $errors
        ]);
        return null;
    }

    $signature = base64url_encode($signature);
    $jwt = $header . '.' . $payload . '.' . $signature;

    // Request access token
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
        logError('GOOGLE_AUTH', 'cURL error during token request', [
            'error' => $curl_error,
            'http_code' => $http_code
        ]);
        return null;
    }

    $response_json = json_decode($response_text, true);

    if (isset($response_json['access_token'])) {
        // Cache the token
        $_SESSION['google_access_token'] = $response_json['access_token'];
        $_SESSION['google_token_expiry'] = time() + $response_json['expires_in'] - 30;

        logInfo('GOOGLE_AUTH', 'Access token obtained successfully');
        
        return $response_json['access_token'];
    }

    // Log failure details
    logError('GOOGLE_AUTH', 'Failed to obtain access token', [
        'http_code' => $http_code,
        'error' => $response_json['error'] ?? 'Unknown',
        'error_description' => $response_json['error_description'] ?? 'No description'
    ]);

    return null;
}

/**
 * Helper function for base64 URL encoding
 */
function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}