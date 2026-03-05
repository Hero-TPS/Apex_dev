<?php
// modules/Bookings/view.php

$page_title = 'Booking Details';
$page_subtitle = 'View Booking';
$show_breadcrumb = true;

// ✅ Load config FIRST (defines BASE_URL)
require_once __DIR__ . '/../../config.php';

// ✅ NOW we can use BASE_URL in breadcrumb
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
    // Determine date context
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
            $timezone = new DateTimeZone(TIME_ZONE);
            $createdDate = new DateTime($booking['date_created'], new DateTimeZone('UTC'));
            echo $createdDate->format('d/m/Y H:i');
            ?>
        </div>
        <?php if (!empty($booking['updated_at'])): ?>
            <div class="detail-item">
                <strong>Last Updated:</strong>
                <?php
                $updatedDate = new DateTime($booking['updated_at'], new DateTimeZone('UTC'));
                echo $updatedDate->format('d/m/Y H:i');
                ?>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="gate_code">Gate code</label>
            <textarea id="gate_code" name="gate_code"></textarea>
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
        target="_blank" class="page-action-btn whatsapp">💬 Send Confirmation</a>
    <a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=<?= (int) $booking['id'] ?>" class="page-action-btn edit">✏️
        Edit Booking</a>
    <a href="javascript:void(0)" id="deleteBookingBtn" class="page-action-btn delete">🗑️ Delete Booking</a>
    <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=<?= (int) $booking['contact_id'] ?>" 
        class="page-action-btn view-details-btn">📅 View All Client Bookings</a>
    <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= (int) $booking['contact_id'] ?>&contact_name=<?= urlencode($booking['client_name']) ?>"
        class="page-action-btn rebook"> ➕ Book again</a>
    <a href="<?= BASE_URL ?>/modules/Bookings/invoice.php?id=<?= (int) $booking['id'] ?>" target="_blank"
        class="page-action-btn invoice">📄 View
        Invoice</a>
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
                    data: {
                        action: 'delete',
                        id: bookingId
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            var notif = $('<div class="success-message">' + response.message + '</div>');
                            $('#notification-area').html(notif);
                            setTimeout(function () {
                                window.location.href = '<?= BASE_URL ?>/modules/Bookings/';
                            }, 2000);
                        } else {
                            var notif = $('<div class="error-message">' + response.message + '</div>');
                            $('#notification-area').html(notif);
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
                            // Update button
                            if (newStatus === 'completed') {
                                button.text('Undo Done').data('status', 'completed').removeClass('toggle').addClass('save');
                            } else {
                                button.text('Mark Done').data('status', 'confirmed').removeClass('save').addClass('toggle');
                            }

                            // ✅ Update status display
                            if (newStatus === 'completed') {
                                $('#status-display').html('<strong>Status:</strong> ✅ Completed');
                            } else {
                                $('#status-display').html('<strong>Status:</strong> ⏳ Confirmed');
                            }
                        }
                    }
                });
            });
        });
    </script>

<?php else: ?>
    <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>