<?php
/**
 * modules/DistanceCalculator/log-error.php
 * @version 1.0.0
 *
 * Receives client-side (browser JS) debug/error reports from the Trip
 * Distance Calculator's Places Autocomplete wiring and writes them into
 * the existing system_logs table via includes/logger.php, so they can be
 * reviewed at /maintenance/logs.php instead of a browser console.
 *
 * Expected POST JSON body: { "level": "ERROR", "message": "...", "context": {...} }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/logger.php';

$response = ['success' => false];

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$allowedLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
$level = strtoupper($body['level'] ?? 'ERROR');
if (!in_array($level, $allowedLevels, true)) {
    $level = 'ERROR';
}

$message = trim((string) ($body['message'] ?? ''));
$context = is_array($body['context'] ?? null) ? $body['context'] : [];

// Always include what the browser/page looked like, useful since this is
// mobile-only debugging with no console access.
$context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

if ($message === '') {
    $response['message'] = 'No message provided';
    echo json_encode($response);
    exit;
}

logMessage($level, 'DISTCALC_JS', $message, $context);

$response['success'] = true;
echo json_encode($response);
