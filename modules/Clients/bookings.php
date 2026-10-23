<?php
// modules/Clients/bookings.php
$page_title = 'Client Bookings';
$page_subtitle = 'View Client Bookings';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/WhatsApp/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Clients', 'url' => BASE_URL . '/modules/Clients/'],
    ['label' => 'Bookings'],
]);
include ROOT_DIR . '/includes/header.php';

$client_id = intval($_GET['id'] ?? 0);

if ($client_id <= 0) {
    echo '<div class="error-message">Invalid client ID</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

// Get client info
try {
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo '<div class="error-message">Client not found</div>';
        include ROOT_DIR . '/includes/footer.php';
        exit;
    }
    
    // Get client's bookings
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            d.name AS driver_name,
            d.phone AS driver_phone,
            CASE 
                WHEN b.status = 'completed' THEN '✓ Completed'
                WHEN b.trip_date < CURDATE() THEN '⚠ Overdue'
                WHEN b.trip_date = CURDATE() THEN '📍 Today'
                ELSE '📅 Upcoming'
            END as status_display
        FROM bookings b
        LEFT JOIN drivers d ON b.driver_id = d.id
        WHERE b.contact_id = ?
        ORDER BY b.trip_date DESC, b.start_time DESC
    ");
    $stmt->execute([$client_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get linked contacts
    $linkedStmt = $pdo->prepare("
        SELECT c.id, c.name, c.phone,
               (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
        FROM contact_links cl
        JOIN contacts c ON c.id = CASE
            WHEN cl.contact_id_a = ? THEN cl.contact_id_b
            ELSE cl.contact_id_a
        END
        WHERE cl.contact_id_a = ? OR cl.contact_id_b = ?
    ");
    $linkedStmt->execute([$client_id, $client_id, $client_id]);
    $linkedContacts = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    logError('CLIENT', 'Failed to fetch client bookings', [
        'error' => $e->getMessage(),
        'client_id' => $client_id
    ]);
    echo '<div class="error-message">Database error occurred</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}
?>

<div class="client-info-card">
    <h3>👤 <?= htmlspecialchars($client['name']) ?></h3>
    
    <?php if ($client['phone']): ?>
        <div class="client-detail">
            <strong>📱 Phone:</strong>
            <span><?= htmlspecialchars($client['phone']) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($client['email']): ?>
        <div class="client-detail">
            <strong>✉️ Email:</strong>
            <span><?= htmlspecialchars($client['email']) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($client['address']): ?>
        <div class="client-detail">
            <strong>📍 Address:</strong>
            <span><?= htmlspecialchars($client['address']) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($client['additional_info']): ?>
        <div class="client-detail">
            <strong>📝 Notes:</strong>
            <span><?= htmlspecialchars($client['additional_info']) ?></span>
        </div>
    <?php endif; ?>
    
    <div class="action-buttons">
        <a href="<?= BASE_URL ?>/modules/Clients/" class="page-action-btn back">
            ← Back to Clients
        </a>
        <a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=<?= $client_id ?>" class="page-action-btn save">
            ✏️ Edit Client
        </a>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= $client_id ?>&contact_name=<?= urlencode($client['name']) ?>" class="page-action-btn primary">
            + New Booking
        </a>
    </div>
</div>

<?php if (!empty($linkedContacts)): ?>
<div class="menu-section">
    <h3>🔗 Linked Contacts</h3>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Bookings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($linkedContacts as $lc): ?>
            <tr>
                <td data-label="Name"><?= htmlspecialchars($lc['name']) ?></td>
                <td data-label="Phone"><?= htmlspecialchars($lc['phone'] ?? '—') ?></td>
                <td data-label="Bookings"><?= (int)$lc['booking_count'] ?></td>
                <td data-label="Actions">
                    <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=<?= (int)$lc['id'] ?>" class="action-btn view-details-btn">View Bookings</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h2>📅 Bookings (<?= count($bookings) ?>)</h2>

<?php if (empty($bookings)): ?>
    <div class="no-bookings">
        <h3>📋 No bookings found</h3>
        <p>This client doesn't have any bookings yet.</p>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= $client_id ?>&contact_name=<?= urlencode($client['name']) ?>" class="page-action-btn primary">
            + Create First Booking
        </a>
    </div>
<?php else: ?>
    <?php
    $clientWhatsAppPhone = formatPhoneNumberForWhatsApp($client['phone'] ?? '');
    // The combined message only needs a phone number on file, not a number that passes the formatter
    $clientHasPhone = trim((string) ($client['phone'] ?? '')) !== '';
    ?>
    <div class="at-group-wa-bar">
        <button type="button" id="groupWaBtn" class="page-action-btn whatsapp" disabled
            <?= !$clientHasPhone ? 'title="No phone number on file"' : '' ?>>
            💬 Send combined message (<span id="groupWaCount">0</span>)
        </button>
        <span class="client-subline">Tick two or more bookings to send them as one WhatsApp message.</span>
    </div>
    <div id="groupWaNotice"></div>
    <table class="bookings-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="groupWaAll" class="at-group-wa-check" aria-label="Tick all bookings"<?= !$clientHasPhone ? ' disabled' : '' ?>></th>
                <th>Date</th>
                <th>Time</th>
                <th>Pickup</th>
                <th>Destination</th>
                <th>Cost</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $booking): ?>
                <?php
                $pickup = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
                $destination = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];

                // Same WhatsApp menu as the booking page: confirmation, bank details, pickup message, ...
                $waBooking = $booking + [
                    'client_name'     => $client['name'] ?? '',
                    'client_phone'    => $client['phone'] ?? '',
                    'client_pickup_lat' => $client['pickup_lat'] ?? null,
                    'client_pickup_lng' => $client['pickup_lng'] ?? null,
                    'pickup_location' => $pickup,
                    'destination'     => $destination,
                ];
                ?>
                <tr>
                    <td data-label="Select"><input type="checkbox" class="at-group-wa-check at-group-wa-row" value="<?= (int) $booking['id'] ?>" aria-label="Tick booking of <?= e(date('d/m/Y', strtotime($booking['trip_date']))) ?>"<?= !$clientHasPhone ? ' disabled' : '' ?>></td>
                    <td data-label="Date"><?= date('d/m/Y', strtotime($booking['trip_date'])) ?></td>
                    <td data-label="Time"><?= date('H:i', strtotime($booking['start_time'])) ?></td>
                    <td data-label="Pickup"><?= htmlspecialchars($pickup) ?></td>
                    <td data-label="Destination"><?= htmlspecialchars($destination) ?></td>
                    <td data-label="Cost">R<?= number_format($booking['cost'], 2) ?></td>
                    <td data-label="Payment"><?= $booking['payment_method'] === 'eft' ? '🏦 EFT' : '💵 Cash' ?></td>
                    <td data-label="Status"><?= $booking['status_display'] ?></td>
                    <td data-label="Actions">
                        <div class="actions-container">
                            <a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=<?= $booking['id'] ?>" class="action-btn view-details-btn">View</a>
                            <a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=<?= $booking['id'] ?>" class="action-btn edit-btn">Edit</a>
                            <?php if ($clientWhatsAppPhone): ?>
                            <?= renderWhatsAppMenu($clientWhatsAppPhone, getWhatsAppBookingMessages($pdo, $waBooking), (int) $booking['id'], (int) $client_id, 'action-btn whatsapp-btn', 'WhatsApp ▾') ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Message History -->
<div class="menu-section" style="margin-top:20px;">
    <h3 class="menu-toggle" data-target="client-msg-history-section" style="cursor:pointer;">📨 Message History</h3>
    <div id="client-msg-history-section" style="display:none; padding:10px 0;">
        <div id="client-msg-history-loading" style="color:#666;">Loading...</div>
        <div id="client-msg-history-list"></div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var clientId = <?= (int) $client_id ?>;

        // Combined WhatsApp message: tick two or more bookings, then send them as one message
        var groupBtn = $('#groupWaBtn');
        var groupRows = $('.at-group-wa-row');

        function updateGroupButton() {
            var ticked = groupRows.filter(':checked').length;
            $('#groupWaCount').text(ticked);
            groupBtn.prop('disabled', ticked < 2 || groupRows.first().prop('disabled'));
            $('#groupWaAll').prop('checked', ticked > 0 && ticked === groupRows.length);
        }

        $('#groupWaAll').on('change', function () {
            groupRows.prop('checked', this.checked);
            updateGroupButton();
        });
        groupRows.on('change', updateGroupButton);

        groupBtn.on('click', function () {
            var ids = groupRows.filter(':checked').map(function () { return this.value; }).get();
            if (ids.length < 2) {
                return;
            }
            // Open the tab straight from the click so the browser does not block it; it is pointed at WhatsApp once the message is ready.
            var waTab = window.open('', '_blank');
            groupBtn.prop('disabled', true);
            $('#groupWaNotice').empty();

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php',
                data: { action: 'group_message', contact_id: clientId, booking_ids: ids },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.url) {
                        if (waTab) {
                            waTab.location.href = res.url;
                        } else {
                            window.location.href = res.url;
                        }
                    } else {
                        if (waTab) { waTab.close(); }
                        $('#groupWaNotice').html($('<div>').addClass('error-message').text(res.message || 'Could not build the message.'));
                    }
                },
                error: function (xhr) {
                    if (waTab) { waTab.close(); }
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not build the message. Please try again.';
                    $('#groupWaNotice').html($('<div>').addClass('error-message').text(msg));
                },
                complete: updateGroupButton
            });
        });

        // Message history section toggle
        $('.menu-toggle[data-target="client-msg-history-section"]').on('click', function () {
            var section = $('#client-msg-history-section');
            if (section.is(':hidden')) {
                section.slideDown(200);
                loadClientMessageHistory();
            } else {
                section.slideUp(200);
            }
        });

        function loadClientMessageHistory() {
            $('#client-msg-history-loading').show().text('Loading...');
            $('#client-msg-history-list').empty();
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: { action: 'get_whatsapp_log', contact_id: clientId },
                dataType: 'json',
                success: function (res) {
                    $('#client-msg-history-loading').hide();
                    if (!res.success || res.logs.length === 0) {
                        $('#client-msg-history-list').html('<p style="color:#999; font-style:italic;">No messages logged yet.</p>');
                        return;
                    }
                    var html = '';
                    $.each(res.logs, function (i, log) {
                        var preview = log.message_content.substring(0, 80) + (log.message_content.length > 80 ? '…' : '');
                        html += '<div style="border-bottom:1px solid #eee; padding:8px 0;">' +
                                '<span style="color:#888; font-size:0.85em;">' + escapeHtml(log.sent_at) + '</span> ' +
                                '<span style="background:#3498db; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.8em;">' + escapeHtml(log.message_type) + '</span>' +
                                (log.booking_id ? ' <span style="font-size:0.8em; color:#666;">Booking #' + parseInt(log.booking_id) + '</span>' : '') +
                                '<div class="msg-preview" style="margin-top:4px; font-size:0.9em;">' + escapeHtml(preview) + '</div>' +
                                (log.message_content.length > 80
                                    ? '<a href="#" class="view-full-msg" style="font-size:0.8em;" data-full="' + escapeHtmlAttr(log.message_content) + '">View Full ▼</a>'
                                    : '') +
                                '</div>';
                    });
                    $('#client-msg-history-list').html(html);
                },
                error: function () {
                    $('#client-msg-history-loading').hide();
                    $('#client-msg-history-list').html('<p style="color:#e74c3c;">Failed to load message history.</p>');
                }
            });
        }

        // View full message toggle
        $('#client-msg-history-list').on('click', '.view-full-msg', function (e) {
            e.preventDefault();
            var fullText = $(this).data('full');
            var previewEl = $(this).prev('.msg-preview');
            if ($(this).text().indexOf('▼') > -1) {
                previewEl.text(fullText);
                $(this).text('Show Less ▲');
            } else {
                var preview = fullText.substring(0, 80) + (fullText.length > 80 ? '…' : '');
                previewEl.text(preview);
                $(this).text('View Full ▼');
            }
        });

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeHtmlAttr(text) {
            return (text || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
