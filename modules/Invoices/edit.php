<?php
// modules/Invoices/edit.php
// Edit an invoice: add or remove bookings, set which trips are paid, change the date and notes.
// The client cannot be changed here: delete the invoice and create a new one instead.
$page_title = 'Edit Invoice';
$page_subtitle = 'Edit Invoice';
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

$breadcrumb = buildBreadcrumb([
    ['label' => 'Invoices', 'url' => BASE_URL . '/modules/Invoices/'],
    ['label' => $invoice['invoice_number'], 'url' => BASE_URL . '/modules/Invoices/view.php?id=' . (int) $invoice['id']],
    ['label' => 'Edit'],
]);

$loadError = false;
$bookings = [];
$orphans = [];
$itemAmountByBooking = [];

try {
    $bookings = getClientBookingsForInvoicing($pdo, (int) $invoice['contact_id']);
    foreach (getInvoiceItems($pdo, (int) $invoice['id']) as $item) {
        if ($item['live_booking_id'] === null) {
            $orphans[] = $item;
        } else {
            $itemAmountByBooking[(int) $item['booking_id']] = (float) $item['amount'];
        }
    }
} catch (PDOException $e) {
    $loadError = true;
    logError('INVOICE', 'Failed to load invoice edit page', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
}

include ROOT_DIR . '/includes/header.php';
?>

<h2>✏️ Edit Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h2>

<?php if ($loadError): ?>
    <div class="error-message">Could not load this invoice's bookings. Please try again.</div>
<?php else: ?>

    <p class="at-inv-edit-client">
        <strong>Client:</strong> <?= htmlspecialchars($invoice['bill_to_name']) ?>
        <span class="client-subline">Wrong client? Delete the invoice and create a new one.</span>
    </p>

    <div id="notification-area"></div>

    <form id="invoiceEditForm">
        <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">

        <h3>Trips</h3>
        <p class="client-subline">Tick the bookings that should be on this invoice, and tick Paid for the ones that have been paid (ticking Paid updates the booking too).</p>

        <div class="at-inv-pick-tools">
            <button type="button" id="selectUninvoicedBtn" class="page-action-btn toggle">Select all not yet invoiced</button>
            <button type="button" id="markAllPaidBtn" class="page-action-btn save">Mark all paid</button>
            <button type="button" id="markAllUnpaidBtn" class="page-action-btn back">Mark all unpaid</button>
        </div>

        <table class="bookings-table at-inv-pick-table">
            <thead>
                <tr>
                    <th>On invoice</th>
                    <th>Date</th>
                    <th>Route</th>
                    <th>Cost</th>
                    <th>Paid</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <?php
                    $pickup = $b['was_swapped'] ? $b['original_destination'] : $b['original_pickup'];
                    $destination = $b['was_swapped'] ? $b['original_pickup'] : $b['original_destination'];
                    $onThis = (int) $b['invoice_id'] === (int) $invoice['id'];
                    $onOther = !empty($b['invoice_id']) && !$onThis;
                    // A trip already on this invoice keeps its frozen amount.
                    $amount = $onThis && isset($itemAmountByBooking[(int) $b['id']])
                        ? $itemAmountByBooking[(int) $b['id']]
                        : (float) $b['cost'];
                    ?>
                    <tr class="<?= $onOther ? 'at-inv-row-locked' : '' ?>">
                        <td data-label="On invoice">
                            <input type="checkbox" class="at-inv-pick" name="booking_ids[]"
                                value="<?= (int) $b['id'] ?>"
                                data-amount="<?= htmlspecialchars((string) round($amount, 2)) ?>"
                                data-on-invoice="<?= $onThis ? '1' : '0' ?>"
                                <?= $onOther ? 'disabled' : '' ?> <?= $onThis ? 'checked' : '' ?>>
                        </td>
                        <td data-label="Date">
                            <?= date('d/m/Y', strtotime($b['trip_date'])) ?>
                            <span class="client-subline"><?= date('H:i', strtotime($b['start_time'])) ?></span>
                        </td>
                        <td data-label="Route"><?= htmlspecialchars($pickup) ?> → <?= htmlspecialchars($destination) ?></td>
                        <td data-label="Cost"><?= htmlspecialchars(formatInvoiceMoney($amount)) ?></td>
                        <td data-label="Paid">
                            <?php if ($onOther): ?>
                                <?= !empty($b['payment_received']) ? '✅ Received' : '—' ?>
                            <?php else: ?>
                                <input type="checkbox" class="at-inv-paid-pick" name="paid_booking_ids[]"
                                    value="<?= (int) $b['id'] ?>"
                                    <?= !empty($b['payment_received']) ? 'checked' : '' ?>
                                    <?= $onThis ? '' : 'disabled' ?>>
                            <?php endif; ?>
                        </td>
                        <td data-label="Invoice">
                            <?php if ($onOther): ?>
                                <a href="<?= BASE_URL ?>/modules/Invoices/view.php?id=<?= (int) $b['invoice_id'] ?>">
                                    <?= htmlspecialchars($b['invoice_number'] ?? 'View') ?>
                                </a>
                            <?php elseif ($onThis): ?>
                                This invoice
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($orphans)): ?>
            <h3>Trips whose booking no longer exists</h3>
            <p class="client-subline">These lines were kept when their booking was deleted. They count as unpaid. Untick a line to remove it from the invoice.</p>
            <table class="bookings-table at-inv-pick-table">
                <thead>
                    <tr>
                        <th>Keep</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphans as $o): ?>
                        <tr>
                            <td data-label="Keep">
                                <input type="checkbox" class="at-inv-keep" name="keep_item_ids[]"
                                    value="<?= (int) $o['id'] ?>"
                                    data-amount="<?= htmlspecialchars((string) round((float) $o['amount'], 2)) ?>" checked>
                            </td>
                            <td data-label="Date"><?= date('d/m/Y', strtotime($o['trip_date'])) ?></td>
                            <td data-label="Description"><?= nl2br(htmlspecialchars($o['description'])) ?></td>
                            <td data-label="Amount"><?= htmlspecialchars(formatInvoiceMoney((float) $o['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="at-inv-pick-summary">
            <span id="selectedCount">0</span> on invoice —
            total <strong id="selectedTotal">R 0.00</strong>
        </div>

        <div class="form-group">
            <label for="issue_date">Invoice date</label>
            <input type="date" id="issue_date" name="issue_date" value="<?= htmlspecialchars($invoice['issue_date']) ?>" required>
        </div>

        <div class="form-group">
            <label for="notes">Notes (optional, shown on the invoice)</label>
            <textarea id="notes" name="notes" rows="3" maxlength="1000"><?= htmlspecialchars((string) ($invoice['notes'] ?? '')) ?></textarea>
        </div>

        <button type="submit" id="saveInvoiceBtn" class="page-action-btn save" disabled>💾 Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/Invoices/view.php?id=<?= (int) $invoice['id'] ?>" class="page-action-btn back">Cancel</a>
    </form>

    <script>
        $(document).ready(function () {
            var picks = $('.at-inv-pick');
            var paidPicks = $('.at-inv-paid-pick');
            var keeps = $('.at-inv-keep');
            var saveBtn = $('#saveInvoiceBtn');
            var notificationArea = $('#notification-area');

            function formatMoney(n) {
                return 'R ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function updateSummary() {
                var count = 0, total = 0;
                picks.filter(':checked').each(function () {
                    count++;
                    total += parseFloat($(this).data('amount')) || 0;
                });
                keeps.filter(':checked').each(function () {
                    count++;
                    total += parseFloat($(this).data('amount')) || 0;
                });
                $('#selectedCount').text(count);
                $('#selectedTotal').text(formatMoney(total));
                saveBtn.prop('disabled', count === 0);
            }

            // A trip's Paid box only applies while the trip is on the invoice.
            function syncPaidBoxes() {
                picks.each(function () {
                    var paidBox = paidPicks.filter('[value="' + $(this).val() + '"]');
                    paidBox.prop('disabled', !$(this).is(':checked'));
                });
            }

            picks.on('change', function () {
                syncPaidBoxes();
                updateSummary();
            });
            keeps.on('change', updateSummary);

            $('#selectUninvoicedBtn').on('click', function () {
                picks.not(':disabled').prop('checked', true);
                syncPaidBoxes();
                updateSummary();
            });
            $('#markAllPaidBtn').on('click', function () {
                paidPicks.not(':disabled').prop('checked', true);
            });
            $('#markAllUnpaidBtn').on('click', function () {
                paidPicks.not(':disabled').prop('checked', false);
            });

            $('#invoiceEditForm').on('submit', function (e) {
                e.preventDefault();
                saveBtn.prop('disabled', true).text('Saving…');
                $.ajax({
                    type: 'POST',
                    url: '<?= BASE_URL ?>/modules/Invoices/api/index.php',
                    data: $(this).serialize() + '&action=update',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success && response.redirect_url) {
                            window.location.href = response.redirect_url;
                            return;
                        }
                        notificationArea.html($('<div>').addClass('error-message').text(response.message || 'Could not save the invoice.'));
                        saveBtn.text('💾 Save Changes');
                        updateSummary();
                    },
                    error: function (xhr) {
                        var msg = 'Could not save the invoice. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                        notificationArea.html($('<div>').addClass('error-message').text(msg));
                        saveBtn.text('💾 Save Changes');
                        updateSummary();
                    }
                });
            });

            syncPaidBoxes();
            updateSummary();
        });
    </script>

<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
