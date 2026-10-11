<?php
// modules/Bookings/invoice.php
// Legacy single-booking invoice URL. Invoicing now lives in modules/Invoices/;
// this keeps the "View Invoice" button on the booking page (and any old
// links) working by forwarding to the booking's invoice if it has one, or to
// the Create Invoice page with this booking pre-selected if it doesn't.
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId <= 0) {
    die('<div class="error-message">Invalid booking ID.</div>');
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? LIMIT 1");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();
if (!$booking) {
    die('<div class="error-message">Booking not found.</div>');
}

if (!empty($booking['invoice_id'])) {
    header('Location: ' . BASE_URL . '/modules/Invoices/view.php?id=' . (int) $booking['invoice_id']);
} else {
    header('Location: ' . BASE_URL . '/modules/Invoices/create.php?booking_id=' . (int) $booking['id']);
}
exit;
