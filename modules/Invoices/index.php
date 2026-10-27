<?php
// modules/Invoices/index.php
// List of all invoices with payment status and quick actions (view, edit, mark paid, copy link, delete).
$page_title = 'Invoices';
$page_subtitle = 'Invoices';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$breadcrumb = buildBreadcrumb([['label' => 'Invoices']]);

$loadError = false;
$invoices = [];
try {
    $invoices = getInvoiceList($pdo);
} catch (PDOException $e) {
    $loadError = true;
    logError('INVOICE', 'Failed to load invoice list', ['error' => $e->getMessage()]);
}

$outstanding = 0.0;
$outstandingCount = 0;
foreach ($invoices as &$inv) {
    $inv['balance'] = max(0.0, round((float) $inv['total'] - (float) $inv['paid_amount'], 2));
    $inv['status'] = getInvoicePaymentStatus((float) $inv['total'], (float) $inv['paid_amount']);
    if ($inv['balance'] > 0.004) {
        $outstanding += $inv['balance'];
        $outstandingCount++;
    }
}
unset($inv);

include ROOT_DIR . '/includes/header.php';
?>

<h2>🧾 Invoices</h2>

<a href="<?= BASE_URL ?>/modules/Invoices/create.php" class="page-action-btn save">➕ Create Invoice</a>

<div id="notification-area"></div>

<?php if ($loadError): ?>
    <div class="error-message">Could not load invoices. If this is the first time, run the pending updates under Services → Database Updates.</div>
<?php elseif (empty($invoices)): ?>
    <div class="no-bookings">
        <h3>🧾 No invoices yet</h3>
        <p>Create your first invoice from a client's bookings.</p>
    </div>
<?php else: ?>
    <div class="at-inv-summary" id="outstandingSummary">
        <strong>Outstanding:</strong> <?= htmlspecialchars(formatInvoiceMoney($outstanding)) ?>
        across <?= (int) $outstandingCount ?> invoice<?= $outstandingCount === 1 ? '' : 's' ?>
    </div>

    <table class="bookings-table at-inv-list-table">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Date</th>
                <th>Client</th>
                <th>Trips</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr id="invoice-row-<?= (int) $inv['id'] ?>" data-balance="<?= htmlspecialchars((string) $inv['balance']) ?>">
                    <td data-label="Invoice"><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td data-label="Date"><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
                    <td data-label="Client"><?= htmlspecialchars($inv['bill_to_name']) ?></td>
                    <td data-label="Trips"><?= (int) $inv['item_count'] ?></td>
                    <td data-label="Total"><?= htmlspecialchars(formatInvoiceMoney((float) $inv['total'])) ?></td>
                    <td data-label="Status">
                        <span class="at-inv-status at-inv-status--<?= htmlspecialchars($inv['status']) ?>">
                            <?= htmlspecialchars(getInvoiceStatusLabel($inv['status'])) ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <div class="actions-container">
                            <a href="<?= BASE_URL ?>/modules/Invoices/view.php?id=<?= (int) $inv['id'] ?>"
                                class="action-btn view-details-btn">View</a>
                            <a href="<?= BASE_URL ?>/modules/Invoices/edit.php?id=<?= (int) $inv['id'] ?>"
                                class="action-btn edit-btn">Edit</a>
                            <?php if ($inv['status'] === 'paid'): ?>
                                <button type="button" class="action-btn convert-btn at-inv-toggle-paid"
                                    data-id="<?= (int) $inv['id'] ?>" data-paid="1">Mark unpaid</button>
                            <?php else: ?>
                                <button type="button" class="action-btn invoice-btn at-inv-toggle-paid"
                                    data-id="<?= (int) $inv['id'] ?>" data-paid="0">Mark paid</button>
                            <?php endif; ?>
                            <button type="button" class="action-btn edit-btn at-inv-copy-link"
                                data-url="<?= htmlspecialchars(buildInvoicePublicUrl($inv['public_token'])) ?>">Copy link</button>
                            <button type="button" class="action-btn delete-btn at-inv-delete"
                                data-id="<?= (int) $inv['id'] ?>"
                                data-number="<?= htmlspecialchars($inv['invoice_number']) ?>">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteInvoiceModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Delete invoice?</h3>
        <p id="deleteInvoiceText"></p>
        <div class="modal-buttons">
            <button id="confirmDeleteInvoiceBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteInvoiceBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var modal = $('#deleteInvoiceModal');
        var invoiceToDelete = null;
        var notificationArea = $('#notification-area');

        function showMessage(cls, text) {
            notificationArea.html($('<div>').addClass(cls).text(text));
        }

        function formatMoney(n) {
            return 'R ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // Recalculate the Outstanding line from each row's balance.
        function updateOutstanding() {
            var total = 0, count = 0;
            $('tr[id^="invoice-row-"]').each(function () {
                var balance = parseFloat($(this).attr('data-balance')) || 0;
                if (balance > 0.004) {
                    total += balance;
                    count++;
                }
            });
            $('#outstandingSummary').html('<strong>Outstanding:</strong> ' + formatMoney(total) +
                ' across ' + count + ' invoice' + (count === 1 ? '' : 's'));
        }

        // Mark paid / Mark unpaid: flips every trip on the invoice, no page reload.
        $('.at-inv-toggle-paid').on('click', function () {
            var btn = $(this);
            var id = btn.data('id');
            var markPaid = String(btn.attr('data-paid')) !== '1';
            btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Invoices/api/index.php',
                data: { action: 'mark_paid', invoice_id: id, paid: markPaid ? 1 : 0 },
                dataType: 'json',
                success: function (response) {
                    btn.prop('disabled', false);
                    if (!response.success) {
                        showMessage('error-message', response.message || 'Could not update the invoice.');
                        return;
                    }
                    var row = $('#invoice-row-' + id);
                    row.attr('data-balance', response.balance);
                    row.find('.at-inv-status')
                        .attr('class', 'at-inv-status at-inv-status--' + response.status)
                        .text(response.status_label);
                    if (response.status === 'paid') {
                        btn.attr('data-paid', '1').text('Mark unpaid')
                            .removeClass('invoice-btn').addClass('convert-btn');
                    } else {
                        btn.attr('data-paid', '0').text('Mark paid')
                            .removeClass('convert-btn').addClass('invoice-btn');
                    }
                    updateOutstanding();
                    showMessage('success-message', response.message);
                },
                error: function () {
                    btn.prop('disabled', false);
                    showMessage('error-message', 'Could not update the invoice. Please try again.');
                }
            });
        });

        $('.at-inv-copy-link').on('click', function () {
            var url = $(this).data('url');
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function () {
                    showMessage('success-message', '✅ Client link copied.');
                }).catch(function (err) {
                    if (window.logJsError) { window.logJsError('warning', 'Copy invoice link failed', { error: String(err) }); }
                    window.prompt('Copy this link:', url);
                });
            } else {
                window.prompt('Copy this link:', url);
            }
        });

        $('.at-inv-delete').on('click', function () {
            invoiceToDelete = $(this).data('id');
            $('#deleteInvoiceText').text(
                'This permanently deletes invoice ' + $(this).data('number') +
                ' and its client link. The bookings on it can be invoiced again.'
            );
            modal.css('display', 'flex');
        });

        $('#cancelDeleteInvoiceBtn').on('click', function () {
            modal.hide();
            invoiceToDelete = null;
        });

        $('#confirmDeleteInvoiceBtn').on('click', function () {
            if (!invoiceToDelete) { return; }
            var id = invoiceToDelete;
            modal.hide();
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Invoices/api/index.php',
                data: { action: 'delete', invoice_id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#invoice-row-' + id).remove();
                        updateOutstanding();
                        showMessage('success-message', response.message);
                    } else {
                        showMessage('error-message', response.message || 'Could not delete the invoice.');
                    }
                },
                error: function () {
                    showMessage('error-message', 'Could not delete the invoice. Please try again.');
                }
            });
            invoiceToDelete = null;
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
