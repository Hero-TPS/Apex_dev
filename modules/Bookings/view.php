<?php
// modules/Bookings/detail.php
$page_title = 'Booking Details';
$page_subtitle = 'View Booking';
$show_breadcrumb = true;
$breadcrumb = ' > <a href="' . (defined('BASE_URL') ? BASE_URL : '') . '/modules/Bookings/">Bookings View</a> > Booking Detail';

// Bootstrap (two levels up from modules/Bookings/)
require_once __DIR__ . '/../../config.php';

// Shared includes
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';

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
            <a href="https://waze.com/ul?q=<?= urlencode($booking['pickup_location']) ?>" target="_blank" class="map-link">Waze</a>
        </div>
        <div class="detail-item full-width">
            <strong>Destination:</strong> <?= htmlspecialchars($booking['destination']) ?>
            <a href="https://waze.com/ul?q=<?= urlencode($booking['destination']) ?>" target="_blank" class="map-link">Waze</a>
        </div>
        <div class="detail-item">
            <strong>Cost:</strong> R<?= number_format((float)$booking['cost'], 2) ?>
        </div>
        <div class="detail-item">
            <strong>Status:</strong> <?= htmlspecialchars($booking['status']) ?>
        </div>

        <?php if ($showStatusButton): ?>
            <div class="detail-item full-width">
                <form id="statusToggleForm" method="post" action="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/Bookings/api/index.php?action=update_status">
                    <input type="hidden" name="id" value="<?= (int)$booking['id'] ?>">
                    <input type="hidden" name="status" value="<?= $booking['status'] === 'completed' ? 'confirmed' : 'completed' ?>">
                    <button type="submit" class="btn"><?= htmlspecialchars($statusButtonText) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <div class="detail-actions full-width">
            <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/Bookings/edit.php?id=<?= (int)$booking['id'] ?>" class="btn">Edit</a>
            <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/Bookings/invoice.php?id=<?= (int)$booking['id'] ?>" class="btn" target="_blank">Invoice</a>
            <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/Bookings/" class="btn">⬅️ Back to Bookings</a>
        </div>
    </div>

    <script>
    (function () {
        // Optional: AJAX status toggle for better UX
        document.getElementById('statusToggleForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            var form = e.target;
            var fd = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(function (resp) {
                if (resp.success) {
                    location.reload();
                } else {
                    alert(resp.message || 'Failed to update status');
                }
            })
            .catch(function () {
                alert('Network error while updating status');
            });
        });
    })();
    </script>

<?php else: ?>
    <div class="error-message">
        <?= htmlspecialchars($error_message) ?>
    </div>
    <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/Bookings/" class="btn">⬅️ Back to Bookings</a>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>