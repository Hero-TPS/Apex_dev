<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

// Validate booking ID
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId <= 0) {
    die('<div class="error-message">Invalid booking ID.</div>');
}

// Fetch full booking
$booking = getBookingById($pdo, $bookingId);
if (!$booking) {
    die('<div class="error-message">Booking not found.</div>');
}

// Apply swap logic
$pickup = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
$destination = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];

// Build WhatsApp link
$invoice_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$message = "Good day " . $booking['client_name'] . ",\n\nPlease find your invoice here: " . $invoice_url;

// Clean and format phone number
$phone = formatPhoneNumberForWhatsApp($booking['client_phone']);
$whatsapp_link = "https://wa.me/" . $phone . "?text=" . urlencode($message);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars(BUSINESS_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="icon" href="assets/favicon.ico">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
</head>

<body>
    <div class="container invoice-container">
        <div class="invoice-header">
            <h1>Invoice</h1>
            <div class="company-details">
                <strong><?= htmlspecialchars(BUSINESS_NAME) ?></strong><br>
                <?= nl2br(htmlspecialchars(BUSINESS_ADDRESS)) ?><br>
                <?= htmlspecialchars(BUSINESS_PHONE) ?>
            </div>
        </div>
        <div class="invoice-meta">
            <div class="meta-left">
                <strong>Bill To:</strong><br>
                <?= htmlspecialchars($booking['client_name']) ?><br>
                <?= nl2br(htmlspecialchars($booking['client_address'] ?? '')) ?><br>
                <?= htmlspecialchars($booking['client_email'] ?? '') ?>
            </div>
            <div class="meta-right">
                <strong>Invoice #:</strong> <?= 1000 + (int) $booking['id'] ?><br>
                <strong>Date:</strong> <?= date("d M Y") ?>
            </div>
        </div>
        <table class="bookings-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Trip Date</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Transport from <?= htmlspecialchars($pickup) ?>
                        to <?= htmlspecialchars($destination) ?>.
                        <?php if (!empty($booking['flight_number'])): ?>
                            <br><small>Flight: <?= htmlspecialchars($booking['flight_number']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($booking['trip_date'])) ?></td>
                    <td style="text-align:right;">R <?= number_format((float) $booking['cost'], 2) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="invoice-total">
            <strong>Total:</strong> R <?= number_format((float) $booking['cost'], 2) ?>
        </div>
        <div class="invoice-footer">
            <p>Thank you for your business!</p>
        </div>
        <div class="invoice-actions">
            <button onclick="window.print()" class="btn">🖨️ Print / Save as PDF</button>
            <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" class="btn whatsapp-link">💬 Send via
                WhatsApp</a>
        </div>
    </div>
</body>

</html>