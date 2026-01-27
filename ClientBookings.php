<?php
// ClientBookings.php
$page_title = 'Client Bookings';
$page_subtitle = 'Booking History';
$show_breadcrumb = true;
$breadcrumb = ' > <a href="ClientsView.php">Clients</a> > Booking History';

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';

if (!isset($_GET['contact_id'])) {
    die('Client ID required');
}

$contactId = (int) $_GET['contact_id'];

// Fetch client name
$stmt = $pdo->prepare("SELECT name FROM contacts WHERE id = ?");
$stmt->execute([$contactId]);
$clientName = $stmt->fetchColumn();

if (!$clientName) {
    die('Client not found');
}

// Fetch bookings
$stmt = $pdo->prepare("
    SELECT 
        b.id,
        b.trip_date,
        b.start_time,
        b.status,
        b.cost,
        b.original_pickup,
        b.original_destination,
        b.was_swapped
    FROM bookings b
    WHERE b.contact_id = ?
    ORDER BY b.trip_date DESC, b.start_time DESC
");
$stmt->execute([$contactId]);
$bookings = $stmt->fetchAll();
?>

<h2>📅 Booking History for <?= htmlspecialchars($clientName) ?></h2>

<?php if ($bookings): ?>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Pickup</th>
                <th>Destination</th>
                <th>Cost</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($bookings as $booking):
                $pickup = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
                $destination = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];
                $statusText = $booking['status'] === 'completed' ? '✅ Completed' : '⏳ Confirmed';
                ?>
                <tr>
                    <td data-label="Date"><?= date('d/m/y', strtotime($booking['trip_date'])) ?></td>
                    <td data-label="Time"><?= date('H:i', strtotime($booking['start_time'])) ?></td>
                    <td data-label="Pickup"><?= htmlspecialchars($pickup) ?></td>
                    <td data-label="Destination"><?= htmlspecialchars($destination) ?></td>
                    <td data-label="Cost">R<?= number_format((float) $booking['cost'], 2) ?></td>
                    <td data-label="Status" class="<?= $statusClass ?>"><?= $statusText ?></td>
                    <td data-label="Actions">
                        <a href="BookingDetail.php?id=<?= (int) $booking['id'] ?>" class="action-btn view-details-btn">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="error-message">No bookings found.</div>
<?php endif; ?>

<a href="ClientsView.php?highlight=<?= (int) $contactId ?>" class="page-action-btn back">⬅️ Back</a>

<?php include ROOT_DIR . '/includes/footer.php'; ?>