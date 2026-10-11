<?php
// modules/Invoices/public.php
// Client-facing invoice, opened from the link sent by WhatsApp.
// Deliberately NOT behind login: access is by the invoice's random
// 128-bit token only. Do not add includes/auth.php here.
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store');

$token = (string) ($_GET['t'] ?? '');
$invoice = getInvoiceByToken($pdo, $token);

if (!$invoice) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Invoice not found</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    </head>
    <body class="at-inv-body-bg">
        <div class="at-inv-page">
            <div class="at-inv-sheet"><div class="at-inv-body">
                <h1 class="at-inv-business">Invoice not found</h1>
                <p>This invoice link is not valid. Please contact <?= htmlspecialchars(BUSINESS_NAME) ?> for a new link.</p>
            </div></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$items = getInvoiceItems($pdo, (int) $invoice['id']);
$settings = getInvoiceSettings($pdo);
$summary = summariseInvoice((float) $invoice['total'], $items);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?> - <?= htmlspecialchars(BUSINESS_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
</head>

<body class="at-inv-body-bg">
    <div class="at-inv-page">
        <div class="at-inv-actions">
            <button type="button" onclick="window.print()" class="page-action-btn primary">🖨️ Print / Save as PDF</button>
        </div>

        <?php include __DIR__ . '/partials/invoice_document.php'; ?>
    </div>
</body>

</html>
