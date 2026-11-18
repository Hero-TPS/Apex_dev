<?php
// services/api/backup.php
// Backup and restore API for services/updates.php (Database Updates). POST only, login and
// CSRF token required. Nothing is stored on the server: a backup is built in memory and
// streamed to the browser, and an uploaded file is read from PHP's upload temp file only.
//
// Actions: export (download a backup), check (read an uploaded backup, change nothing),
//          restore (replace this database's tables from an uploaded backup)
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/db_backup.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Unsupported method.'], 405);
}

$token = $_POST['csrf'] ?? '';
if (empty($_SESSION['updates_csrf']) || !is_string($token) || !hash_equals($_SESSION['updates_csrf'], $token)) {
    jsonResponse(['success' => false, 'message' => 'Your session token has expired. Reload the page and try again.'], 403);
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'export') {
        handleExport($pdo);
    } elseif ($action === 'check') {
        handleCheck($pdo);
    } elseif ($action === 'restore') {
        handleRestore($pdo);
    }
    jsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
} catch (Throwable $e) {
    logError('DB_BACKUP', 'Backup request failed', ['error' => $e->getMessage(), 'action' => $action]);
    jsonResponse(['success' => false, 'message' => 'Something went wrong: ' . $e->getMessage()], 500);
}

/**
 * Stream a backup to the browser as a download.
 *
 */
function handleExport(PDO $pdo): void
{
    $backup = createDbBackup($pdo);

    // A restore needs a backup downloaded in this session shortly before it
    $_SESSION['backup_snapshot_at'] = time();

    logInfo('DB_BACKUP', 'Backup downloaded', [
        'file' => $backup['filename'],
        'tables' => $backup['tables'],
        'rows' => $backup['rows'],
        'role' => dbBackupRole(),
    ]);

    header('Content-Type: ' . $backup['mime']);
    header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
    header('Content-Length: ' . strlen($backup['data']));
    header('Cache-Control: no-store');
    echo $backup['data'];
    exit;
}

/**
 * Read an uploaded backup and say what a restore would do. Changes nothing.
 */
function handleCheck(PDO $pdo): void
{
    $content = readUploadedBackup();
    $check = dbBackupCheck($pdo, $content);
    if (!$check['ok']) {
        jsonResponse(['success' => false, 'message' => $check['error']]);
    }

    jsonResponse([
        'success' => true,
        'allowed' => $check['allowed'],
        'reason' => $check['reason'],
        'site_role' => $check['role'],
        'needs_phrase' => $check['role'] === 'live',
        'header' => [
            'role' => $check['header']['role'],
            'db_name' => $check['header']['db_name'],
            'created' => $check['header']['created'],
            'latest_migration' => $check['header']['latest_migration'] ?? '',
        ],
        'tables' => $check['tables'],
        'warnings' => $check['warnings'],
    ]);
}

/**
 * Replace this database's tables with the ones in an uploaded backup.
 * POST: file, confirm (the phrase "RESTORE LIVE" on the live site)
 */
function handleRestore(PDO $pdo): void
{
    $content = readUploadedBackup();
    $check = dbBackupCheck($pdo, $content);
    if (!$check['ok']) {
        jsonResponse(['success' => false, 'message' => $check['error']]);
    }
    if (!$check['allowed']) {
        logWarning('DB_BACKUP', 'Restore refused', ['reason' => $check['reason'], 'file_role' => $check['header']['role'], 'site_role' => $check['role']]);
        jsonResponse(['success' => false, 'message' => $check['reason']], 403);
    }
    if ($check['role'] === 'live' && trim((string) ($_POST['confirm'] ?? '')) !== 'RESTORE LIVE') {
        jsonResponse(['success' => false, 'message' => 'Type RESTORE LIVE to confirm restoring the live database.'], 400);
    }
    $snapshotAt = (int) ($_SESSION['backup_snapshot_at'] ?? 0);
    if ($snapshotAt < time() - DB_BACKUP_SNAPSHOT_WINDOW) {
        jsonResponse(['success' => false, 'message' => 'A backup of the current database must be downloaded just before a restore. Try the restore again from the page.'], 400);
    }

    $result = dbBackupRestore($pdo, $check['statements']);

    // The log table may itself have just been replaced, so a failure to log here is not an error
    try {
        if ($result['ok']) {
            logInfo('DB_BACKUP', 'Database restored from a backup', [
                'file_role' => $check['header']['role'],
                'file_created' => $check['header']['created'],
                'site_role' => $check['role'],
                'statements' => count($check['statements']),
            ]);
        } else {
            logCritical('DB_BACKUP', 'Restore failed part-way', ['message' => $result['message']]);
        }
    } catch (Throwable $e) {
    }

    jsonResponse(['success' => $result['ok'], 'message' => $result['message']]);
}

/**
 * The text of the uploaded backup file, or a JSON error response.
 */
function readUploadedBackup(): string
{
    $read = dbBackupReadUpload($_FILES['file'] ?? []);
    if (!$read['ok']) {
        jsonResponse(['success' => false, 'message' => $read['error']]);
    }
    return $read['content'];
}
