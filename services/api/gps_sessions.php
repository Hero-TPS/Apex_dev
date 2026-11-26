<?php
// services/api/gps_sessions.php
// JSON API for services/gps_sessions.php (GPS Log buttons). POST only, login and CSRF token required.
// Every action answers with the full page state, so the page draws itself from one code path.
// Actions: state, press (type), undo, location (event_id, lat, lon, acc), edit_time (event_id, time), delete (event_id).
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/gps_import.php';
require_once ROOT_DIR . '/includes/gps_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Unsupported method.'], 405);
}

$token = $_POST['csrf'] ?? '';
if (empty($_SESSION['gps_session_csrf']) || !is_string($token) || !hash_equals($_SESSION['gps_session_csrf'], $token)) {
    jsonResponse(['success' => false, 'message' => 'Your session token has expired. Reload the page and try again.'], 403);
}

$action = $_GET['action'] ?? '';
$eventId = (int) ($_POST['event_id'] ?? 0);

try {
    $result = ['ok' => true, 'message' => '', 'event_id' => null];

    switch ($action) {
        case 'state':
            // Points that arrived by FTP since the last look, so the "last GPS point" shown is current
            gpsImportOnDemand($pdo);
            break;

        case 'press':
            $type = (string) ($_POST['type'] ?? '');
            $result = gpsSessionPress($pdo, $type);
            if ($result['ok'] && ($type === 'start' || $type === 'end')) {
                logInfo('GPS', $type === 'start' ? 'GPS logging started' : 'GPS logging ended');
            }
            break;

        case 'undo':
            $result = gpsSessionUndo($pdo);
            break;

        case 'location':
            $accuracy = isset($_POST['acc']) && is_numeric($_POST['acc']) ? (float) $_POST['acc'] : null;
            if (!isset($_POST['lat'], $_POST['lon']) || !is_numeric($_POST['lat']) || !is_numeric($_POST['lon'])) {
                $result = ['ok' => false, 'message' => 'That position is not valid.'];
                break;
            }
            $result = gpsSessionSetLocation($pdo, $eventId, (float) $_POST['lat'], (float) $_POST['lon'], $accuracy);
            break;

        case 'edit_time':
            $result = gpsSessionEditTime($pdo, $eventId, (string) ($_POST['time'] ?? ''));
            break;

        case 'delete':
            $result = gpsSessionDeleteEvent($pdo, $eventId);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    jsonResponse([
        'success'  => $result['ok'],
        'message'  => $result['message'],
        'event_id' => $result['event_id'] ?? null,
        'state'    => gpsSessionState($pdo),
    ]);
} catch (PDOException $e) {
    logError('GPS', 'GPS Log action failed', ['action' => $action, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'That could not be saved. If a table is missing, run Services → Database Updates.',
    ]);
}
