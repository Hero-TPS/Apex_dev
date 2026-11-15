<?php
// modules/Uber/api/report.php
// Fleet manager report API: build the WhatsApp text for one Uber week, log it, and
// return the wa.me link to open. Nothing is sent from the server — the person taps
// Send in WhatsApp, so the log records "opened for sending", not confirmed delivery.

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/WhatsApp/helpers.php';
require_once __DIR__ . '/../helper.php';

header('Content-Type: application/json');
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    switch ($action) {
        case 'send':
            handleSendReport();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (PDOException $e) {
    logError('UBER_REPORT', 'Database error', ['error' => $e->getMessage(), 'action' => $action]);
    jsonResponse([
        'success' => false,
        'message' => 'Database error. If this is the first time, run the pending updates under Services → Database Updates.',
    ], 500);
} catch (Throwable $e) {
    logCritical('UBER_REPORT', 'Unhandled exception', ['error' => $e->getMessage(), 'action' => $action]);
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
}

// ========== HANDLERS ==========

/**
 * Build the report message for one week, log it, return the wa.me link.
 * The log entry is written BEFORE the link is returned: if it cannot be saved,
 * nothing is sent, so there is never a report out the door without a record.
 * POST: id (uber_income id)
 */
function handleSendReport(): void
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid week.'], 400);
    }

    $rawPhone = trim((string) getSystemVariable($pdo, 'fleet_manager_whatsapp'));
    if ($rawPhone === '') {
        jsonResponse(['success' => false, 'message' => 'No fleet manager number is set. Add it under Services → System Variables.'], 400);
    }
    // Standard formatter first; when it does not accept the number (e.g. an international
    // number saved without "+"), the digits as saved are used, without a leading 00.
    $waPhone = formatPhoneNumberForWhatsApp($rawPhone);
    if ($waPhone === '') {
        $waPhone = preg_replace('/^00/', '', preg_replace('/\D/', '', $rawPhone));
    }
    if ($waPhone === '') {
        jsonResponse(['success' => false, 'message' => 'The fleet manager number is not a usable phone number. Check it under Services → System Variables.'], 400);
    }

    $report = getUberWeekReport($pdo, $id);
    if ($report === null) {
        jsonResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    if (getWhatsAppTemplate($pdo, 'uber_fleet_report') === null) {
        jsonResponse(['success' => false, 'message' => 'The report message is not set up yet. Run the pending updates under Services → Database Updates.'], 400);
    }

    $text = getUberReportMessage($pdo, $report);
    if ($text === '') {
        jsonResponse(['success' => false, 'message' => 'Could not build the message.'], 500);
    }

    $stmt = $pdo->prepare("
        INSERT INTO uber_report_log (uber_income_id, recipient_phone, message_content, sent_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$id, $waPhone, $text, time()]);

    logInfo('UBER_REPORT', 'Fleet report prepared for sending', ['uber_income_id' => $id]);

    jsonResponse([
        'success' => true,
        'text' => $text,
        'url' => 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($text),
    ]);
}
