<?php
// modules/Invoices/view.php
// Logged-in view of one invoice, with Edit / Mark paid / Print / WhatsApp / Copy link actions.
// Uses the normal site header and footer (for the breadcrumb); the site chrome is hidden when printing.
$page_title = 'Invoice';
$page_subtitle = 'Invoice';
$show_breadcrumb = true;

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

$breadcrumb = buildBreadcrumb([
    ['label' => 'Invoices', 'url' => BASE_URL . '/modules/Invoices/'],
    ['label' => $invoice['invoice_number']],
]);

$publicUrl = buildInvoicePublicUrl($invoice['public_token']);

// An invoice sent after the client has paid says so; otherwise it is the normal request wording.
$waPaidNote = $summary['status'] === 'paid' ? ' (paid in full)' : '';
$waMessage = "Good day " . $invoice['bill_to_name'] . ",\n\n"
    . "Please find invoice " . $invoice['invoice_number']
    . " for " . formatInvoiceMoney((float) $invoice['total']) . $waPaidNote . " here:\n"
    . $publicUrl . "\n\n"
    . "Regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
$waPhone = formatPhoneNumberForWhatsApp($invoice['client_phone'] ?? '');
$waLink = $waPhone !== '' ? 'https://wa.me/' . $waPhone . '?text=' . urlencode($waMessage) : '';

include ROOT_DIR . '/includes/header.php';
?>

<script>document.body.classList.add('at-inv-view');</script>

<div class="at-inv-page">
    <div class="at-inv-actions">
        <a href="<?= BASE_URL ?>/modules/Invoices/edit.php?id=<?= (int) $invoice['id'] ?>"
            class="page-action-btn edit">✏️ Edit</a>
        <?php if ($summary['status'] === 'paid'): ?>
            <button type="button" id="togglePaidBtn" class="page-action-btn confirm" data-paid="1">↩️ Mark unpaid</button>
        <?php else: ?>
            <button type="button" id="togglePaidBtn" class="page-action-btn save" data-paid="0">✅ Mark paid</button>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="page-action-btn primary">🖨️ Print / Save as PDF</button>
        <?php if ($waLink !== ''): ?>
            <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener"
                class="page-action-btn whatsapp">💬 Send via WhatsApp</a>
        <?php endif; ?>
        <button type="button" id="copyLinkBtn" class="page-action-btn edit"
            data-url="<?= htmlspecialchars($publicUrl) ?>">🔗 Copy client link</button>
    </div>
    <div id="notification-area"></div>

    <?php include __DIR__ . '/partials/invoice_document.php'; ?>
</div>

<script>
    $(document).ready(function () {
        var notificationArea = $('#notification-area');

        function showMessage(cls, text) {
            notificationArea.html($('<div>').addClass(cls).text(text));
        }

        $('#copyLinkBtn').on('click', function () {
            var url = $(this).attr('data-url');
            function done() { showMessage('success-message', '✅ Client link copied.'); }
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

        // Mark paid / Mark unpaid flips every trip on the invoice, then reloads to show the new status.
        $('#togglePaidBtn').on('click', function () {
            var btn = $(this);
            var markPaid = String(btn.attr('data-paid')) !== '1';
            btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Invoices/api/index.php',
                data: { action: 'mark_paid', invoice_id: <?= (int) $invoice['id'] ?>, paid: markPaid ? 1 : 0 },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        window.location.reload();
                        return;
                    }
                    btn.prop('disabled', false);
                    showMessage('error-message', response.message || 'Could not update the invoice.');
                },
                error: function () {
                    btn.prop('disabled', false);
                    showMessage('error-message', 'Could not update the invoice. Please try again.');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
