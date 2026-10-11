<?php
// modules/Invoices/view.php
// Logged-in view of one invoice, with Print / WhatsApp / Copy link actions.
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$invoice = $invoiceId ? getInvoiceById($pdo, $invoiceId) : null;
if (!$invoice) {
    http_response_code(404);
    die('<div class="error-message">Invoice not found.</div>');
}

$items = getInvoiceItems($pdo, (int) $invoice['id']);
$settings = getInvoiceSettings($pdo);
$summary = summariseInvoice((float) $invoice['total'], $items);

$publicUrl = buildInvoicePublicUrl($invoice['public_token']);

$waMessage = "Good day " . $invoice['bill_to_name'] . ",\n\n"
    . "Please find invoice " . $invoice['invoice_number']
    . " for " . formatInvoiceMoney((float) $invoice['total']) . " here:\n"
    . $publicUrl . "\n\n"
    . "Regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
$waPhone = formatPhoneNumberForWhatsApp($invoice['client_phone'] ?? '');
$waLink = $waPhone !== '' ? 'https://wa.me/' . $waPhone . '?text=' . urlencode($waMessage) : '';
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
    <script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
    <script src="<?= BASE_URL ?>/assets/js/error-logging.js"></script>
</head>

<body class="at-inv-body-bg">
    <div class="at-inv-page">
        <div class="at-inv-actions">
            <a href="<?= BASE_URL ?>/modules/Invoices/" class="page-action-btn back">← Invoices</a>
            <button type="button" onclick="window.print()" class="page-action-btn primary">🖨️ Print / Save as PDF</button>
            <?php if ($waLink !== ''): ?>
                <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"
                    class="page-action-btn whatsapp">💬 Send via WhatsApp</a>
            <?php endif; ?>
            <button type="button" id="copyLinkBtn" class="page-action-btn edit"
                data-url="<?= htmlspecialchars($publicUrl) ?>">🔗 Copy client link</button>
        </div>
        <div id="copyLinkNote" class="at-inv-copy-note" role="status"></div>

        <?php include __DIR__ . '/partials/invoice_document.php'; ?>
    </div>

    <script>
        document.getElementById('copyLinkBtn').addEventListener('click', function () {
            var url = this.getAttribute('data-url');
            var note = document.getElementById('copyLinkNote');
            function done() { note.textContent = '✅ Client link copied.'; }
            function manual() { window.prompt('Copy this link:', url); }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(function (err) {
                    if (window.logJsError) { window.logJsError('warning', 'Copy invoice link failed', { error: String(err) }); }
                    manual();
                });
            } else {
                manual();
            }
        });
    </script>
</body>

</html>
