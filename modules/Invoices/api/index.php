<?php
// modules/Invoices/api/index.php
// Invoices API: create, update (edit) and delete an invoice, mark an invoice paid/unpaid.
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/../helpers.php';
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    switch ($action) {
        case 'create':
            handleCreateInvoice();
            break;
        case 'update':
            handleUpdateInvoice();
            break;
        case 'mark_paid':
            handleMarkInvoicePaid();
            break;
        case 'delete':
            handleDeleteInvoice();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    logError('INVOICE', 'Invoice API error', [
        'action' => $action,
        'error' => $e->getMessage(),
    ]);
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
}

/**
 * Create an invoice from the posted client + booking IDs.
 */
function handleCreateInvoice(): void
{
    global $pdo;

    $contactId = filter_var($_POST['contact_id'] ?? null, FILTER_VALIDATE_INT);
    $bookingIds = $_POST['booking_ids'] ?? [];
    $issueDate = trim((string) ($_POST['issue_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!$contactId || $contactId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Please choose a client.'], 400);
    }
    if (!is_array($bookingIds) || empty($bookingIds)) {
        jsonResponse(['success' => false, 'message' => 'Select at least one booking.'], 400);
    }
    if (mb_strlen($notes) > 1000) {
        jsonResponse(['success' => false, 'message' => 'Notes are too long (max 1000 characters).'], 400);
    }

    try {
        $result = createInvoice($pdo, $contactId, $bookingIds, $issueDate, $notes);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }

    logInfo('INVOICE', 'Invoice created', [
        'invoice_id' => $result['id'],
        'invoice_number' => $result['invoice_number'],
        'contact_id' => $contactId,
        'booking_count' => count($bookingIds),
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Invoice ' . $result['invoice_number'] . ' created.',
        'invoice_id' => $result['id'],
        'redirect_url' => BASE_URL . '/modules/Invoices/view.php?id=' . $result['id'],
    ]);
}

/**
 * Edit an invoice: date, notes, which bookings are on it, and which are paid.
 */
function handleUpdateInvoice(): void
{
    global $pdo;

    $invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    $bookingIds = $_POST['booking_ids'] ?? [];
    $keepItemIds = $_POST['keep_item_ids'] ?? [];
    $paidBookingIds = $_POST['paid_booking_ids'] ?? [];
    $issueDate = trim((string) ($_POST['issue_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!$invoiceId || $invoiceId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid invoice ID.'], 400);
    }
    if (!is_array($bookingIds) || !is_array($keepItemIds) || !is_array($paidBookingIds)) {
        jsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
    }
    if (mb_strlen($notes) > 1000) {
        jsonResponse(['success' => false, 'message' => 'Notes are too long (max 1000 characters).'], 400);
    }

    try {
        $result = updateInvoice($pdo, $invoiceId, $bookingIds, $keepItemIds, $paidBookingIds, $issueDate, $notes);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }

    logInfo('INVOICE', 'Invoice updated', [
        'invoice_id' => $result['id'],
        'invoice_number' => $result['invoice_number'],
        'booking_count' => count($bookingIds),
        'paid_count' => count($paidBookingIds),
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Invoice ' . $result['invoice_number'] . ' updated.',
        'redirect_url' => BASE_URL . '/modules/Invoices/view.php?id=' . $result['id'],
    ]);
}

/**
 * Mark all trips on an invoice paid (paid=1) or unpaid (paid=0).
 * Returns the new status so the page can update without a reload.
 */
function handleMarkInvoicePaid(): void
{
    global $pdo;

    $invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$invoiceId || $invoiceId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid invoice ID.'], 400);
    }
    $paid = ($_POST['paid'] ?? '1') === '1';

    $summary = setInvoicePaid($pdo, $invoiceId, $paid);
    if ($summary === null) {
        jsonResponse(['success' => false, 'message' => 'Invoice not found.'], 404);
    }

    logInfo('INVOICE', $paid ? 'Invoice marked paid' : 'Invoice marked unpaid', [
        'invoice_id' => $invoiceId,
        'invoice_number' => $summary['invoice_number'],
        'status' => $summary['status'],
    ]);

    $message = 'Invoice ' . $summary['invoice_number'] . ($paid ? ' marked as paid.' : ' marked as unpaid.');
    if ($paid && $summary['status'] !== 'paid') {
        $message .= ' Some lines are for bookings that no longer exist, so a balance remains. Edit the invoice to remove them.';
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'status' => $summary['status'],
        'status_label' => getInvoiceStatusLabel($summary['status']),
        'total' => $summary['total'],
        'balance' => $summary['balance'],
    ]);
}

/**
 * Delete an invoice and release its bookings.
 */
function handleDeleteInvoice(): void
{
    global $pdo;

    $invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$invoiceId || $invoiceId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid invoice ID.'], 400);
    }

    $number = deleteInvoice($pdo, $invoiceId);
    if ($number === null) {
        jsonResponse(['success' => false, 'message' => 'Invoice not found.'], 404);
    }

    logInfo('INVOICE', 'Invoice deleted', [
        'invoice_id' => $invoiceId,
        'invoice_number' => $number,
    ]);

    jsonResponse(['success' => true, 'message' => 'Invoice ' . $number . ' deleted. Its bookings can be invoiced again.']);
}
