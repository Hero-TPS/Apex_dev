<?php
// services/api/updates.php
// JSON API for services/updates.php (Database Updates). POST only, login and
// CSRF token required. File names are only ever matched against the real
// contents of /migrations, never used to build a path.
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/migrations.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Unsupported method.'], 405);
}

$token = $_POST['csrf'] ?? '';
if (empty($_SESSION['updates_csrf']) || !is_string($token) || !hash_equals($_SESSION['updates_csrf'], $token)) {
    jsonResponse(['success' => false, 'message' => 'Your session token has expired. Reload the page and try again.'], 403);
}

$action = $_GET['action'] ?? '';
$file = basename((string) ($_POST['file'] ?? ''));

try {
    if ($action === 'run' && $file !== '') {
        jsonResponse(runMigration($pdo, $file));
    } elseif ($action === 'run_all') {
        jsonResponse(runPendingMigrations($pdo));
    } elseif ($action === 'mark' && $file !== '') {
        jsonResponse(markMigrationApplied($pdo, $file));
    }

    jsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
} catch (Exception $e) {
    logError('DB_UPDATE', 'Database update request failed', [
        'error' => $e->getMessage(),
        'action' => $action,
        'file' => $file,
    ]);
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}
