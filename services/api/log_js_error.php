<?php
// services/api/log_js_error.php
/**
 * Receives client-side (browser JS) error reports from assets/js/error-logging.js
 * and writes them into system_logs via includes/logger.php, so they show up
 * at /services/logs.php instead of a browser console.
 *
 * Expected POST JSON body: { "level": "ERROR", "message": "...", "context": {...} }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$allowedLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
$level = strtoupper((string) ($body['level'] ?? 'ERROR'));
if (!in_array($level, $allowedLevels, true)) {
    $level = 'ERROR';
}

$message = trim((string) ($body['message'] ?? ''));
if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'No message provided']);
    exit;
}
$message = mb_substr($message, 0, 1000);

$context = is_array($body['context'] ?? null) ? $body['context'] : [];
$context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
$context['page'] = $_SERVER['HTTP_REFERER'] ?? null;

logMessage($level, 'JS', $message, $context);

echo json_encode(['success' => true]);
