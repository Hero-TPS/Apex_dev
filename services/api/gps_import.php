<?php
// services/api/gps_import.php
// JSON API for services/gps_import.php (GPS Import). POST only, login and CSRF token required.
// Reads the CSV files in gps_inbox/ and adds their points to gps_log.
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/gps_import.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Unsupported method.'], 405);
}

$token = $_POST['csrf'] ?? '';
if (empty($_SESSION['gps_import_csrf']) || !is_string($token) || !hash_equals($_SESSION['gps_import_csrf'], $token)) {
    jsonResponse(['success' => false, 'message' => 'Your session token has expired. Reload the page and try again.'], 403);
}

if (($_GET['action'] ?? '') !== 'run') {
    jsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
}

try {
    set_time_limit(120);
    $summary = gpsImportRun($pdo);
    logInfo('GPS', 'GPS CSV import run', $summary);

    if ($summary['files'] === 0 && empty($summary['errors'])) {
        jsonResponse(['success' => true, 'message' => 'No CSV files in the inbox yet.', 'summary' => $summary]);
    }

    $message = "Read {$summary['files']} file(s), {$summary['rows']} rows: "
        . "{$summary['new']} new, {$summary['existing']} already in the log, {$summary['skipped']} skipped.";
    if (!empty($summary['errors'])) {
        $message .= ' Problems: ' . implode('; ', $summary['errors']) . '.';
    }
    jsonResponse(['success' => empty($summary['errors']), 'message' => $message, 'summary' => $summary]);
} catch (PDOException $e) {
    logError('GPS', 'GPS CSV import failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'The import could not save to the database. If a table or column is missing, run Services → Database Updates.',
    ]);
}
