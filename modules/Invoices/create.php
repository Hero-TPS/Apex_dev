<?php
// modules/Invoices/create.php
// Pick a client, tick the bookings to bill, and create one invoice.
$page_title = 'Create Invoice';
$page_subtitle = 'Create Invoice';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'Invoices', 'url' => BASE_URL . '/modules/Invoices/'],
    ['label' => 'Create'],
]);

$contactId = filter_input(INPUT_GET, 'contact_id', FILTER_VALIDATE_INT) ?: 0;
$preselectBookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT) ?: 0;

$loadError = false;
$clients = [];
$bookings = [];
$selectedClientName = '';

try {
    // Arriving from a booking page: work out the client from the booking.
    if ($contactId <= 0 && $preselectBookingId > 0) {
        $stmt = $pdo->prepare("SELECT contact_id FROM bookings WHERE id = ?");
        $stmt->execute([$preselectBookingId]);
        $contactId = (int) ($stmt->fetchColumn() ?: 0);
    }

    $clients = getClientsWithBookings($pdo);
    if ($contactId > 0) {
        foreach ($clients as $c) {
            if ((int) $c['id'] === $contactId) {
                $selectedClientName = $c['name'];
            }
        }
        if ($selectedClientName === '') {
            $contactId = 0; // unknown client or one with no bookings
        } else {
            $bookings = getClientBookingsForInvoicing($pdo, $contactId);
        }
    }
} catch (PDOException $e) {
    $loadError = true;
    logError('INVOICE', 'Failed to load invoice create page', ['error' => $e->getMessage()]);
}

$today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

include ROOT_DIR . '/includes/header.php';
?>

<h2>🧾 Create Invoice</h2>

<?php if ($loadError): ?>
    <div class="error-message">Could not load bookings. If this is the first time, run the invoices entry in LIVE_MIGRATION.md on this database.</div>
<?php else: ?>

    <form method="get" class="at-inv-client-form">
        <div class="form-group">
            <label for="contact_id">Client</label>
            <select id="contact_id" name="contact_id" onchange="this.form.submit()">
                <option value="">— Choose a client —</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $contactId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?><?= !empty($c['is_archived']) ? ' (archived)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="page-action-btn primary">Load bookings</button></noscript>
    </form>

    <div id="notification-area"></div>

    <?php if ($contactId > 0): ?>
        <form id="invoiceForm">
            <input type="hidden" name="contact_id" value="<?= (int) $contactId ?>">

            <h3>Bookings for <?= htmlspecialchars($selectedClientName) ?></h3>

            <div class="at-inv-pick-tools">
                <button type="button" id="selectUninvoicedBtn" class="page-action-btn toggle">Select all not yet invoiced</button>
                <button type="button" id="clearSelectionBtn" class="page-action-btn back">Clear</button>
            </div>

            <table class="bookings-table at-inv-pick-table">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Date</th>
                        <th>Route</th>
                        <th>Cost</th>
                        <th>Payment</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <?php
                        $pickup = $b['was_swapped'] ? $b['original_destination'] : $b['original_pickup'];
                        $destination = $b['was_swapped'] ? $b['original_pickup'] : $b['original_destination'];
                        $isInvoiced = !empty($b['invoice_id']);
                        $isPreselected = !$isInvoiced && (int) $b['id'] === $preselectBookingId;
                        ?>
                        <tr class="<?= $isInvoiced ? 'at-inv-row-locked' : '' ?>">
                            <td data-label="Select">
                                <input type="checkbox" class="at-inv-pick" name="booking_ids[]"
                                    value="<?= (int) $b['id'] ?>"
                                    data-amount="<?= htmlspecialchars((string) round((float) $b['cost'], 2)) ?>"
                                    <?= $isInvoiced ? 'disabled' : '' ?> <?= $isPreselected ? 'checked' : '' ?>>
                            </td>
                            <td data-label="Date">
                                <?= date('d/m/Y', strtotime($b['trip_date'])) ?>
                                <span class="client-subline"><?= date('H:i', strtotime($b['start_time'])) ?></span>
                            </td>
                            <td data-label="Route"><?= htmlspecialchars($pickup) ?> → <?= htmlspecialchars($destination) ?></td>
                            <td data-label="Cost"><?= htmlspecialchars(formatInvoiceMoney((float) $b['cost'])) ?></td>
                            <td data-label="Payment"><?= !empty($b['payment_received']) ? '✅ Received' : '—' ?></td>
                            <td data-label="Invoice">
                                <?php if ($isInvoiced): ?>
                                    <a href="<?= BASE_URL ?>/modules/Invoices/view.php?id=<?= (int) $b['invoice_id'] ?>">
                                        <?= htmlspecialchars($b['invoice_number'] ?? 'View') ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="at-inv-pick-summary">
                <span id="selectedCount">0</span> selected —
                total <strong id="selectedTotal">R 0.00</strong>
            </div>

            <div class="form-group">
                <label for="issue_date">Invoice date</label>
                <input type="date" id="issue_date" name="issue_date" value="<?= htmlspecialchars($today) ?>" required>
            </div>

            <div class="form-group">
                <label for="notes">Notes (optional, shown on the invoice)</label>
                <textarea id="notes" name="notes" rows="3" maxlength="1000"></textarea>
            </div>

            <button type="submit" id="createInvoiceBtn" class="page-action-btn save" disabled>🧾 Create Invoice</button>
        </form>

        <script>
            $(document).ready(function () {
                var picks = $('.at-inv-pick');
                var createBtn = $('#createInvoiceBtn');
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
                    $('#selectedCount').text(count);
                    $('#selectedTotal').text(formatMoney(total));
                    createBtn.prop('disabled', count === 0);
                }

                picks.on('change', updateSummary);

                $('#selectUninvoicedBtn').on('click', function () {
                    picks.not(':disabled').prop('checked', true);
                    updateSummary();
                });
                $('#clearSelectionBtn').on('click', function () {
                    picks.prop('checked', false);
                    updateSummary();
                });

                $('#invoiceForm').on('submit', function (e) {
                    e.preventDefault();
                    createBtn.prop('disabled', true).text('Creating…');
                    $.ajax({
                        type: 'POST',
                        url: '<?= BASE_URL ?>/modules/Invoices/api/index.php',
                        data: $(this).serialize() + '&action=create',
                        dataType: 'json',
                        success: function (response) {
                            if (response.success && response.redirect_url) {
                                window.location.href = response.redirect_url;
                                return;
                            }
                            notificationArea.html($('<div>').addClass('error-message').text(response.message || 'Could not create the invoice.'));
                            createBtn.prop('disabled', false).text('🧾 Create Invoice');
                            updateSummary();
                        },
                        error: function (xhr) {
                            var msg = 'Could not create the invoice. Please try again.';
                            if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                            notificationArea.html($('<div>').addClass('error-message').text(msg));
                            createBtn.prop('disabled', false).text('🧾 Create Invoice');
                            updateSummary();
                        }
                    });
                });

                updateSummary();
            });
        </script>
    <?php endif; ?>

<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
