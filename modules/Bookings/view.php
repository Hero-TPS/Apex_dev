<?php
// modules/Bookings/view.php

$page_title = 'Booking Details';
$page_subtitle = 'View Booking';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Bookings', 'url' => BASE_URL . '/modules/Bookings/'],
    ['label' => 'Booking Details'],
]);

include ROOT_DIR . '/includes/header.php';

$booking = null;
$error_message = '';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    if ($bookingId > 0) {
        try {
            $booking = getBookingById($pdo, $bookingId);

            if ($booking) {
                $booking['pickup_location'] = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
                $booking['destination'] = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];

                // Fetch drivers and booking fee for the Manage Driver section
                $driversStmt = $pdo->query("SELECT id, name, phone FROM drivers WHERE active = 1 ORDER BY name ASC");
                $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);
                $booking_fee_pct = (float) getSystemVariable($pdo, 'apex_booking_fee_pct');
            } else {
                $error_message = "Booking not found.";
            }
        } catch (PDOException $e) {
            error_log('BookingDetail error: ' . $e->getMessage());
            $error_message = "Failed to load booking.";
        }
    } else {
        $error_message = "Invalid booking ID.";
    }
} else {
    $error_message = "No booking ID provided.";
}
?>

<?php if ($booking): ?>
    <?php
    $now = new DateTime('now', new DateTimeZone(TIME_ZONE));
    $tripDate = new DateTime($booking['trip_date']);
    $today = $now->format('Y-m-d');
    $isToday = ($booking['trip_date'] === $today);
    $isPast = ($tripDate < $now && !$isToday);

    $showStatusButton = false;
    $statusButtonText = '';

    if ($booking['status'] === 'completed') {
        if ($isToday) {
            $showStatusButton = true;
            $statusButtonText = 'Undo Done';
        }
    } else {
        if ($isToday || $isPast) {
            $showStatusButton = true;
            $statusButtonText = 'Mark as Done';
        }
    }
    ?>

    <div class="booking-detail-grid">
        <div class="detail-item">
            <strong>Client:</strong> <?= htmlspecialchars($booking['client_name']) ?>
        </div>
        <div class="detail-item">
            <strong>Phone:</strong> <?= htmlspecialchars($booking['client_phone']) ?>
        </div>
        <div class="detail-item">
            <strong>Date:</strong> <?= date('d M Y', strtotime($booking['trip_date'])) ?>
        </div>
        <div class="detail-item">
            <strong>Time:</strong> <?= date('H:i', strtotime($booking['start_time'])) ?>
        </div>
        <div class="detail-item full-width">
            <strong>Pickup:</strong> <?= htmlspecialchars($booking['pickup_location']) ?>
            <a href="https://waze.com/ul?q=<?= urlencode($booking['pickup_location']) ?>" target="_blank"
                class="map-link">Waze</a>
        </div>
        <div class="detail-item full-width">
            <strong>Destination:</strong> <?= htmlspecialchars($booking['destination']) ?>
            <a href="https://waze.com/ul?q=<?= urlencode($booking['destination']) ?>" target="_blank"
                class="map-link">Waze</a>
        </div>
        <div class="detail-item">
            <strong>Cost:</strong> R <?= number_format((float) $booking['cost'], 2) ?>
        </div>
        <div class="detail-item">
            <strong>Payment Method:</strong>
            <?= $booking['payment_method'] === 'eft' ? 'EFT' : 'Cash' ?>
        </div>
        <div class="detail-item">
            <strong>Flight:</strong>
            <?php
            if (!empty($booking['flight_number'])):
                $flight_number_clean = preg_replace('/\s+/', '', $booking['flight_number']);
                $flightradar_link = "https://www.flightradar24.com/data/flights/" . strtolower($flight_number_clean);
                ?>
                <?= htmlspecialchars($booking['flight_number']) ?>
                <a href="<?= $flightradar_link ?>" target="_blank" class="map-link">Track Flight</a>
            <?php endif; ?>
        </div>
        <div class="detail-item full-width">
            <strong>Notes:</strong> <?= htmlspecialchars($booking['description'] ?: 'None') ?>
        </div>
        <div class="detail-item" id="status-display">
            <strong>Status:</strong>
            <?= $booking['status'] === 'completed' ? '✅ Completed' : '⏳ Confirmed' ?>
        </div>
        <div class="detail-item">
            <strong>Created:</strong>
            <?php
            $createdDate = new DateTime($booking['date_created'], new DateTimeZone(TIME_ZONE));
            echo $createdDate->format('d/m/Y H:i');
            ?>
        </div>
        <?php if (!empty($booking['updated_at'])): ?>
            <div class="detail-item">
                <strong>Last Updated:</strong>
                <?php
                $updatedDate = new DateTime($booking['updated_at'], new DateTimeZone(TIME_ZONE));
                echo $updatedDate->format('d/m/Y H:i');
                ?>
            </div>
        <?php endif; ?>

        <!-- Gate Code -->
        <div class="detail-item full-width">
            <strong>Gate Code:</strong>
            <div class="gate-code-row">
                <textarea id="gate_code" name="gate_code" rows="2"
                    class="gate-code-textarea"><?= htmlspecialchars($booking['gate_code'] ?? '') ?></textarea>
                <button id="saveGateCodeBtn" class="page-action-btn save">
                    💾 Save
                </button>
            </div>
            <div id="gate-code-result"></div>
        </div>
    </div>

    <!-- Manage Driver -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="manage-driver-section">🚗 Manage Driver</h3>
        <div id="manage-driver-section" class="section-body hidden">
            <div id="manage-driver-content">
                <div class="manage-driver-fields">
                    <div>
                        <label for="driver-select" class="manage-driver-label">Driver</label>
                        <select id="driver-select">
                            <option value="">— No driver —</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= htmlspecialchars($driver['id']) ?>"
                                    <?= ((int)$booking['driver_id'] === (int)$driver['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($driver['name']) ?>
                                    <?= $driver['phone'] ? ' (' . htmlspecialchars($driver['phone']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="booking-fee-input" class="manage-driver-label">Booking Fee (R)</label>
                        <input type="number" id="booking-fee-input" step="0.01" min="0"
                            value="<?= number_format((float)($booking['booking_fee'] ?? 0), 2) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="no-booking-fee-check"
                            <?= !empty($booking['no_booking_fee']) ? 'checked' : '' ?>>
                        No Booking Fee (full amount goes to driver)
                    </label>
                </div>
                <div class="form-group">
                    <label for="driver-notes-input" class="manage-driver-label">Driver Notes</label>
                    <textarea id="driver-notes-input" rows="3"
                        placeholder="Instructions for the driver..."><?= htmlspecialchars($booking['driver_notes'] ?? '') ?></textarea>
                </div>
                <div class="manage-driver-actions">
                    <button id="assign-driver-btn" class="page-action-btn save"><?= !empty($booking['driver_id']) ? '🔄 Update Driver' : '✅ Assign Driver' ?></button>
                    <button id="remove-driver-btn" class="page-action-btn delete">❌ Remove Driver</button>
                </div>
                <div id="manage-driver-result"></div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="invoice-actions">
        <?php if ($showStatusButton): ?>
            <button id="toggleStatusBtn" class="page-action-btn <?= $booking['status'] === 'completed' ? 'save' : 'toggle' ?>"
                data-status="<?= htmlspecialchars($booking['status']) ?>">
                <?= $booking['status'] === 'completed' ? 'Undo Done' : 'Mark Done' ?>
            </button>
        <?php endif; ?>
        <a href="https://wa.me/<?= formatPhoneNumberForWhatsApp($booking['client_phone']) ?>?text=<?= urlencode(createWhatsAppMessage($booking)) ?>"
            target="_blank" class="page-action-btn whatsapp"
            onclick="logWhatsAppSend(<?= (int) $booking['id'] ?>, <?= (int) $booking['contact_id'] ?>, <?= htmlspecialchars(json_encode(createWhatsAppMessage($booking)), ENT_QUOTES) ?>, 'confirmation')">💬
            Send Confirmation</a>
        <a href="https://wa.me/<?= formatPhoneNumberForWhatsApp($booking['client_phone']) ?>?text=<?= urlencode('Hi ' . $booking['client_name']."\n") ?>"
            target="_blank" class="page-action-btn whatsapp"
            onclick="logWhatsAppSend(<?= (int) $booking['id'] ?>, <?= (int) $booking['contact_id'] ?>, <?= htmlspecialchars(json_encode('Hi ' . $booking['client_name']), ENT_QUOTES) ?>, 'message')">💬
            Send Message</a>
        <?php if (!empty($booking['driver_name']) && !empty($booking['driver_phone'])): ?>
        <?php
            $driverMsg = createDriverBookingMessage($booking);
            $driverPhone = formatPhoneNumberForWhatsApp($booking['driver_phone']);
        ?>
        <a href="https://wa.me/<?= $driverPhone ?>?text=<?= urlencode($driverMsg) ?>"
            target="_blank" class="page-action-btn whatsapp"
            onclick="logWhatsAppSend(<?= (int) $booking['id'] ?>, <?= (int) $booking['contact_id'] ?>, <?= htmlspecialchars(json_encode($driverMsg), ENT_QUOTES) ?>, 'driver_notification')">🚗
            Message Driver</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=<?= (int) $booking['id'] ?>" class="page-action-btn edit">✏️
            Edit Booking</a>
    </div>

    <!-- More Actions (collapsible) -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="more-actions-section">⚙️ More Actions</h3>
        <div id="more-actions-section" class="section-content--padded">
            <div class="more-actions-row">
                <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=<?= (int) $booking['contact_id'] ?>"
                    class="page-action-btn view-details-btn">📅 All Client Bookings</a>
                <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= (int) $booking['contact_id'] ?>&contact_name=<?= urlencode($booking['client_name']) ?>"
                    class="page-action-btn rebook">➕ Book Again</a>
                <a href="<?= BASE_URL ?>/modules/Bookings/invoice.php?id=<?= (int) $booking['id'] ?>" target="_blank"
                    class="page-action-btn invoice">📄 View Invoice</a>
                <a href="javascript:void(0)" id="deleteBookingBtn" class="page-action-btn delete">🗑️ Delete Booking</a>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Are you sure?</h3>
            <p>This will permanently delete the booking and its Google Calendar event. This action cannot be undone.</p>
            <div class="modal-buttons">
                <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
                <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="notification-area"></div>

    <!-- Message History -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="msg-history-section">📨 Message History</h3>
        <div id="msg-history-section" class="section-content">
            <div id="msg-history-loading">Loading...</div>
            <div id="msg-history-list"></div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            var bookingId = <?= (int) $booking['id'] ?>;
            var hasDriver = <?= !empty($booking['driver_id']) ? 'true' : 'false' ?>;
            var modal = $('#deleteConfirmationModal');

            $('#deleteBookingBtn').on('click', function () {
                modal.show();
            });

            $('#cancelDeleteBtn').on('click', function () {
                modal.hide();
            });

            $('#confirmDeleteBtn').on('click', function () {
                var btn = $(this);
                btn.text('Deleting...').prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    data: { action: 'delete', id: bookingId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#notification-area').html('<div class="success-message">' + response.message + '</div>');
                            setTimeout(function () {
                                window.location.href = '<?= BASE_URL ?>/modules/Bookings/';
                            }, 2000);
                        } else {
                            $('#notification-area').html('<div class="error-message">' + response.message + '</div>');
                        }
                    },
                    error: function () {
                        $('#notification-area').html('<div class="error-message">❌ Delete request failed.</div>');
                    },
                    complete: function () {
                        btn.text('Yes, Delete').prop('disabled', false);
                        modal.hide();
                    }
                });
            });

            // Status toggle
            $('#toggleStatusBtn').on('click', function () {
                var currentStatus = $(this).data('status');
                var newStatus = (currentStatus === 'completed') ? 'confirmed' : 'completed';
                var button = $(this);

                $.ajax({
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    type: 'POST',
                    data: {
                        action: 'update_status',
                        id: <?= (int) $booking['id'] ?>,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            if (newStatus === 'completed') {
                                button.text('Undo Done').data('status', 'completed').removeClass('toggle').addClass('save');
                                $('#status-display').html('<strong>Status:</strong> ✅ Completed');
                            } else {
                                button.text('Mark Done').data('status', 'confirmed').removeClass('save').addClass('toggle');
                                $('#status-display').html('<strong>Status:</strong> ⏳ Confirmed');
                            }
                        }
                    }
                });
            });

            // Gate code save
            $('#saveGateCodeBtn').on('click', function () {
                var btn = $(this);
                var resultArea = $('#gate-code-result');
                btn.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    type: 'POST',
                    data: {
                        action: 'update_gate_code',
                        id: bookingId,
                        gate_code: $('#gate_code').val()
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            resultArea.html('<span class="success-message result-sm">✓ Saved</span>');
                        } else {
                            resultArea.html('<span class="error-message result-sm">✗ ' + res.message + '</span>');
                        }
                        setTimeout(function () { resultArea.html(''); }, 3000);
                    },
                    error: function () {
                        resultArea.html('<span class="error-message result-sm">✗ Failed to save</span>');
                    },
                    complete: function () {
                        btn.prop('disabled', false).text('💾 Save');
                    }
                });
            });

            // More Actions section toggle
            $('.menu-toggle[data-target="more-actions-section"]').on('click', function () {
                var section = $('#more-actions-section');
                section.slideToggle(200);
            });

            // Manage Driver section toggle
            $('.menu-toggle[data-target="manage-driver-section"]').on('click', function () {
                $('#manage-driver-section').slideToggle(200);
            });

            var apexFeePct = <?= (float) $booking_fee_pct ?>;
            var bookingCost = <?= (float) $booking['cost'] ?>;

            $('#driver-select').on('change', autoCalcFee);

            function autoCalcFee() {
                var noFee = $('#no-booking-fee-check').is(':checked');
                $('#booking-fee-input').prop('disabled', noFee);
                if (noFee) {
                    $('#booking-fee-input').val('0.00');
                } else if (apexFeePct > 0 && bookingCost > 0 && $('#driver-select').val()) {
                    var fee = Math.round(bookingCost * apexFeePct / 100 * 100) / 100;
                    $('#booking-fee-input').val(fee.toFixed(2));
                } else if (!$('#driver-select').val()) {
                    $('#booking-fee-input').val('0.00');
                }
            }

            $('#no-booking-fee-check').on('change', autoCalcFee);
            autoCalcFee();

            function doRemoveDriver() {
                var resultArea = $('#manage-driver-result');
                $.ajax({
                    type: 'POST',
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    data: { action: 'assign_driver', booking_id: bookingId, driver_id: '', booking_fee: 0, no_booking_fee: 0, driver_notes: '' },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            resultArea.html('<span class="success-message result-xs">✓ Driver removed.</span>');
                            setTimeout(function () { location.reload(); }, 1200);
                        } else {
                            resultArea.html('<span class="error-message result-xs">✗ ' + escapeHtml(res.message) + '</span>');
                        }
                    },
                    error: function () {
                        resultArea.html('<span class="error-message result-xs">✗ Request failed.</span>');
                    }
                });
            }

            $('#assign-driver-btn').on('click', function () {
                var driverId = $('#driver-select').val();
                var fee = parseFloat($('#booking-fee-input').val()) || 0;
                var noFee = $('#no-booking-fee-check').is(':checked') ? 1 : 0;
                var driverNotes = $('#driver-notes-input').val();
                var resultArea = $('#manage-driver-result');

                if (!driverId) {
                    if (!confirm('No driver selected — this will remove the currently assigned driver. Continue?')) {
                        return;
                    }
                    doRemoveDriver();
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    data: { action: 'assign_driver', booking_id: bookingId, driver_id: driverId, booking_fee: fee, no_booking_fee: noFee, driver_notes: driverNotes },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var msg = hasDriver ? '✓ Driver updated.' : '✓ Driver assigned.';
                            resultArea.html('<span class="success-message result-xs">' + msg + '</span>');
                            setTimeout(function () { location.reload(); }, 1200);
                        } else {
                            resultArea.html('<span class="error-message result-xs">✗ ' + escapeHtml(res.message) + '</span>');
                        }
                    },
                    error: function () {
                        resultArea.html('<span class="error-message result-xs">✗ Request failed.</span>');
                    }
                });
            });

            $('#remove-driver-btn').on('click', function () {
                if (!confirm('Remove the currently assigned driver from this booking?')) {
                    return;
                }
                doRemoveDriver();
            });

            // Message history section toggle
            $('.menu-toggle[data-target="msg-history-section"]').on('click', function () {
                var section = $('#msg-history-section');
                if (section.is(':hidden')) {
                    section.slideDown(200);
                    loadMessageHistory();
                } else {
                    section.slideUp(200);
                }
            });

            function loadMessageHistory() {
                $('#msg-history-loading').show().text('Loading...');
                $('#msg-history-list').empty();
                $.ajax({
                    type: 'GET',
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                    data: { action: 'get_whatsapp_log', booking_id: bookingId },
                    dataType: 'json',
                    success: function (res) {
                        $('#msg-history-loading').hide();
                        if (!res.success || res.logs.length === 0) {
                            $('#msg-history-list').html('<p class="msg-history-empty">No messages logged yet.</p>');
                            return;
                        }
                        var html = '';
                        $.each(res.logs, function (i, log) {
                            var preview = log.message_content.substring(0, 80) + (log.message_content.length > 80 ? '…' : '');
                            html += '<div class="msg-history-entry">' +
                                '<span class="msg-history-time">' + escapeHtml(log.sent_at) + '</span> ' +
                                '<span class="msg-history-type">' + escapeHtml(log.message_type) + '</span>' +
                                '<div class="msg-history-preview">' + escapeHtml(preview) + '</div>' +
                                (log.message_content.length > 80
                                    ? '<a href="#" class="view-full-msg msg-history-expand" data-full="' + escapeHtmlAttr(log.message_content) + '">View Full ▼</a>'
                                    : '') +
                                '</div>';
                        });
                        $('#msg-history-list').html(html);
                    },
                    error: function () {
                        $('#msg-history-loading').hide();
                        $('#msg-history-list').html('<p class="msg-history-error">Failed to load message history.</p>');
                    }
                });
            }

            // View full message toggle
            $('#msg-history-list').on('click', '.view-full-msg', function (e) {
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
        });

        function logWhatsAppSend(bookingId, contactId, messageContent, messageType) {
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: {
                    action: 'log_whatsapp',
                    booking_id: bookingId,
                    contact_id: contactId,
                    message_type: messageType || 'confirmation',
                    message_content: messageContent,
                    sent_by: 'user'
                },
                dataType: 'json'
            });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeHtmlAttr(text) {
            return (text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>

<?php else: ?>
    <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>