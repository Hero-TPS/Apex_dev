<?php
// modules/Bookings/view.php

$page_title = 'Booking Details';
$page_subtitle = 'View Booking';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
$breadcrumb = ' > <a href="' . BASE_URL . '/modules/Bookings/">Bookings</a> > Booking Details';

include ROOT_DIR . '/includes/header.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$booking = null;
$error_message = '';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    if ($bookingId > 0) {
        try {
            $sql = "SELECT b.*, c.name AS client_name, c.phone AS client_phone 
                    FROM bookings b 
                    JOIN contacts c ON b.contact_id = c.id 
                    WHERE b.id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if ($booking) {
                $booking['pickup_location'] = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
                $booking['destination'] = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];
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
            <div style="display: flex; gap: 8px; align-items: flex-start; margin-top: 4px;">
                <textarea id="gate_code" name="gate_code" rows="2"
                    style="flex: 1; resize: vertical;"><?= htmlspecialchars($booking['gate_code'] ?? '') ?></textarea>
                <button id="saveGateCodeBtn" class="page-action-btn save" style="width: auto; white-space: nowrap;">
                    💾 Save
                </button>
            </div>
            <div id="gate-code-result" style="margin-top: 4px;"></div>
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
        onclick="logWhatsAppSend(<?= (int)$booking['id'] ?>, <?= (int)$booking['contact_id'] ?>, <?= json_encode(createWhatsAppMessage($booking)) ?>)">💬 Send Confirmation</a>
    <button class="page-action-btn whatsapp" onclick="openCustomWhatsApp(<?= json_encode($booking['client_name']) ?>, <?= json_encode($booking['client_phone']) ?>, <?= json_encode('Hi ' . $booking['client_name'] . ",\n") ?>)">💬 Send Message</button>
    <a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=<?= (int) $booking['id'] ?>" class="page-action-btn edit">✏️
        Edit Booking</a>
    <a href="javascript:void(0)" id="deleteBookingBtn" class="page-action-btn delete">🗑️ Delete Booking</a>
    <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=<?= (int) $booking['contact_id'] ?>"
        class="page-action-btn view-details-btn">📅 View All Client Bookings</a>
    <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= (int) $booking['contact_id'] ?>&contact_name=<?= urlencode($booking['client_name']) ?>"
        class="page-action-btn rebook"> ➕ Book again</a>
    <a href="<?= BASE_URL ?>/modules/Bookings/invoice.php?id=<?= (int) $booking['id'] ?>" target="_blank"
        class="page-action-btn invoice">📄 View Invoice</a>
</div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="modal-overlay" style="display: none;">
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

    <!-- Custom WhatsApp Modal -->
    <div id="customWhatsAppModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:480px; text-align:left;">
            <h3>💬 Send Message to <span id="waModalClientName"></span></h3>
            <p style="color:#666; font-size:0.9em;">📱 <span id="waModalPhone"></span></p>
            <div class="form-group" style="margin-top:15px;">
                <label for="waModalMessage">Message:</label>
                <textarea id="waModalMessage" rows="6" placeholder="Type your message here..." style="width:100%; box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:15px;">
                <button id="waModalCancelBtn" class="page-action-btn delete" style="min-width:80px;">Cancel</button>
                <a id="waModalSendBtn" href="#" target="_blank" class="page-action-btn whatsapp" style="min-width:180px;" onclick="return onWaModalSend()">Send via WhatsApp 💬</a>
            </div>
        </div>
    </div>

    <!-- Message History -->
    <div class="menu-section" style="margin-top:20px;">
        <h3 class="menu-toggle" data-target="msg-history-section" style="cursor:pointer;">📨 Message History</h3>
        <div id="msg-history-section" style="display:none; padding:10px 0;">
            <div id="msg-history-loading" style="color:#666;">Loading...</div>
            <div id="msg-history-list"></div>
            <button id="logCurrentSendBtn" class="page-action-btn whatsapp" style="margin-top:10px;">📋 Log Current Send</button>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            var bookingId = <?= (int) $booking['id'] ?>;
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
                            resultArea.html('<span class="success-message" style="font-size:0.85em;">✓ Saved</span>');
                        } else {
                            resultArea.html('<span class="error-message" style="font-size:0.85em;">✗ ' + res.message + '</span>');
                        }
                        setTimeout(function () { resultArea.html(''); }, 3000);
                    },
                    error: function () {
                        resultArea.html('<span class="error-message" style="font-size:0.85em;">✗ Failed to save</span>');
                    },
                    complete: function () {
                        btn.prop('disabled', false).text('💾 Save');
                    }
                });
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
                            $('#msg-history-list').html('<p style="color:#999; font-style:italic;">No messages logged yet.</p>');
                            return;
                        }
                        var html = '';
                        $.each(res.logs, function (i, log) {
                            var preview = log.message_content.substring(0, 80) + (log.message_content.length > 80 ? '…' : '');
                            html += '<div style="border-bottom:1px solid #eee; padding:8px 0;">' +
                                    '<span style="color:#888; font-size:0.85em;">' + escapeHtml(log.sent_at) + '</span> ' +
                                    '<span class="badge-type" style="background:#3498db; color:#fff; border-radius:3px; padding:1px 6px; font-size:0.8em;">' + escapeHtml(log.message_type) + '</span>' +
                                    '<div class="msg-preview" style="margin-top:4px; font-size:0.9em;">' + escapeHtml(preview) + '</div>' +
                                    (log.message_content.length > 80
                                        ? '<a href="#" class="view-full-msg" style="font-size:0.8em;" data-full="' + escapeHtmlAttr(log.message_content) + '">View Full ▼</a>'
                                        : '') +
                                    '</div>';
                        });
                        $('#msg-history-list').html(html);
                    },
                    error: function () {
                        $('#msg-history-loading').hide();
                        $('#msg-history-list').html('<p style="color:#e74c3c;">Failed to load message history.</p>');
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

            // Log current send button
            $('#logCurrentSendBtn').on('click', function () {
                logWhatsAppSend(<?= (int)$booking['id'] ?>, <?= (int)$booking['contact_id'] ?>, <?= json_encode(createWhatsAppMessage($booking)) ?>);
                setTimeout(loadMessageHistory, 500);
            });

            // Custom WhatsApp modal – cancel button
            $('#waModalCancelBtn').on('click', function () {
                $('#customWhatsAppModal').hide();
            });

            // Escape key closes modal
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $('#customWhatsAppModal').hide();
                }
            });
        });

        function openCustomWhatsApp(name, phone, prefill) {
            $('#waModalClientName').text(name);
            $('#waModalPhone').text(phone);
            $('#waModalMessage').val(prefill);
            var cleanPhone = phone.replace(/\D/g, '');
            if (cleanPhone.charAt(0) === '0') { cleanPhone = '27' + cleanPhone.substring(1); }
            $('#waModalSendBtn').attr('href', 'https://wa.me/' + cleanPhone + '?text=');
            $('#customWhatsAppModal').css('display', 'flex');
            $('#waModalMessage').focus();
        }

        function onWaModalSend() {
            var msg = $('#waModalMessage').val();
            var currentHref = $('#waModalSendBtn').attr('href');
            // Strip any previous text param and rebuild
            var base = currentHref.split('?text=')[0];
            $('#waModalSendBtn').attr('href', base + '?text=' + encodeURIComponent(msg));
            return true;
        }

        function logWhatsAppSend(bookingId, contactId, messageContent) {
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: {
                    action: 'log_whatsapp',
                    booking_id: bookingId,
                    contact_id: contactId,
                    message_type: 'confirmation',
                    message_content: messageContent,
                    sent_by: 'user'
                },
                dataType: 'json'
                // fire-and-forget; no callbacks needed
            });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeHtmlAttr(text) {
            return (text || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }
    </script>

<?php else: ?>
    <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>