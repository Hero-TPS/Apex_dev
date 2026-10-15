<?php
// modules/Invoices/create.php
// Search for a client, tick the bookings to bill, and create one invoice.
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

    <div class="at-inv-client-form">
        <div class="form-group">
            <label for="clientSearch">Client</label>
            <input type="text" id="clientSearch" placeholder="Search client by name or phone..." autocomplete="off"
                value="<?= htmlspecialchars($selectedClientName) ?>">
            <div id="clientSuggestions" class="suggestions-box"></div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            var clientSearch = $('#clientSearch');
            var suggestionsBox = $('#clientSuggestions');
            var clients = <?= json_encode($clients, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
            var selected = -1;

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str || ''));
                return div.innerHTML;
            }

            function highlight(items) {
                items.removeClass('active');
                if (selected >= 0) {
                    items.eq(selected).addClass('active');
                }
            }

            // Choosing a client reloads the page with that client's bookings.
            function selectClient(client) {
                suggestionsBox.hide();
                window.location.href = '?contact_id=' + encodeURIComponent(client.id);
            }

            clientSearch.on('input focus', function () {
                var query = $(this).val().trim().toLowerCase();
                if (query.length === 0) {
                    suggestionsBox.hide();
                    return;
                }
                var filtered = clients.filter(function (c) {
                    return (c.name && c.name.toLowerCase().includes(query)) ||
                        (c.phone && c.phone.toLowerCase().includes(query));
                });
                suggestionsBox.empty();
                selected = -1;
                if (filtered.length > 0) {
                    filtered.forEach(function (client) {
                        var archivedNote = client.is_archived == 1
                            ? '<br><small class="archived-note">📦 archived</small>'
                            : '';
                        var item = $('<div class="suggestion-item">' + escapeHtml(client.name) +
                            '<br><small>' + escapeHtml(client.phone || '') + '</small>' +
                            '<br><small>' + escapeHtml(client.address || '') + '</small>' + archivedNote + '</div>');
                        item.data('client', client);
                        item.on('click', function () {
                            selectClient($(this).data('client'));
                        });
                        suggestionsBox.append(item);
                    });
                } else {
                    suggestionsBox.html('<div class="suggestion-item">No clients with bookings found</div>');
                }
                suggestionsBox.show();
            });

            clientSearch.on('keydown', function (e) {
                var items = suggestionsBox.find('.suggestion-item');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selected = Math.min(selected + 1, items.length - 1);
                    highlight(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selected = Math.max(selected - 1, -1);
                    highlight(items);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selected >= 0 && items.eq(selected).data('client')) {
                        selectClient(items.eq(selected).data('client'));
                    }
                }
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#clientSearch, #clientSuggestions').length) {
                    suggestionsBox.hide();
                }
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    suggestionsBox.hide();
                }
            });
        });
    </script>

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
