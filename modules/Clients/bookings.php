<?php
$page_title = 'Client Bookings';
$page_subtitle = 'View Client Bookings';
$show_breadcrumb = true;
$breadcrumb = ' > Clients > Bookings';

require_once __DIR__ . '/../../config.php';
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
            CASE 
                WHEN b.status = 'completed' THEN '✓ Completed'
                WHEN b.trip_date < CURDATE() THEN '⚠ Overdue'
                WHEN b.trip_date = CURDATE() THEN '📍 Today'
                ELSE '📅 Upcoming'
            END as status_display
        FROM bookings b
        WHERE b.contact_id = ?
        ORDER BY b.trip_date DESC, b.start_time DESC
    ");
    $stmt->execute([$client_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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

<style>
.client-info-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.client-info-card h3 {
    margin-top: 0;
    color: #2c3e50;
}

.client-detail {
    margin: 10px 0;
    display: flex;
    gap: 10px;
}

.client-detail strong {
    min-width: 120px;
    color: #666;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    flex-wrap: wrap;
}
</style>

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
        <a href="<?= BASE_URL ?>/modules/Clients/" class="btn" style="width: auto; background: #95a5a6;">
            ← Back to Clients
        </a>
        <a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=<?= $client_id ?>" class="btn" style="width: auto; background: #2ecc71;">
            ✏️ Edit Client
        </a>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= $client_id ?>&contact_name=<?= urlencode($client['name']) ?>" class="btn" style="width: auto;">
            + New Booking
        </a>
    </div>
</div>

<h2>📅 Bookings (<?= count($bookings) ?>)</h2>

<?php if (empty($bookings)): ?>
    <div class="no-bookings">
        <h3>📋 No bookings found</h3>
        <p>This client doesn't have any bookings yet.</p>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=<?= $client_id ?>&contact_name=<?= urlencode($client['name']) ?>" class="btn" style="width: auto; padding: 10px 20px;">
            + Create First Booking
        </a>
    </div>
<?php else: ?>
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
            <?php foreach ($bookings as $booking): ?>
                <?php
                $pickup = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
                $destination = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];
                ?>
                <tr>
                    <td data-label="Date"><?= date('d/m/Y', strtotime($booking['trip_date'])) ?></td>
                    <td data-label="Time"><?= date('H:i', strtotime($booking['start_time'])) ?></td>
                    <td data-label="Pickup"><?= htmlspecialchars($pickup) ?></td>
                    <td data-label="Destination"><?= htmlspecialchars($destination) ?></td>
                    <td data-label="Cost">R<?= number_format($booking['cost'], 2) ?></td>
                    <td data-label="Status"><?= $booking['status_display'] ?></td>
                    <td data-label="Actions">
                        <div class="actions-container">
                            <a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=<?= $booking['id'] ?>" class="action-btn view-details-btn">View</a>
                            <a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=<?= $booking['id'] ?>" class="action-btn edit-btn">Edit</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>