<?php
// modules/Itineraries/index.php
$page_title    = 'Itineraries';
$page_subtitle = 'Trip Itineraries';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Itineraries']]);
include ROOT_DIR . '/includes/header.php';

if (!itineraryMigrationReady($pdo)) {
    echo itineraryMigrationNotice();
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$stmt = $pdo->query("
    SELECT i.*, c.name AS client_name,
           (SELECT COUNT(*) FROM itinerary_stops s WHERE s.itinerary_id = i.id) AS stop_count
    FROM itineraries i
    JOIN contacts c ON i.contact_id = c.id
    ORDER BY i.trip_date ASC, i.start_time ASC
");
$itineraries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-actions">
    <a href="<?= BASE_URL ?>/modules/Itineraries/add.php" class="page-action-btn add">🗺️ Add Itinerary</a>
</div>

<?php if (empty($itineraries)): ?>
    <p class="empty-state">No itineraries yet. <a href="<?= BASE_URL ?>/modules/Itineraries/add.php">Add one</a>.</p>
<?php else: ?>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Date</th>
                <th>Time</th>
                <th>Pickup</th>
                <th>Destination</th>
                <th>Stops</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itineraries as $i):
                $tz      = new DateTimeZone(TIME_ZONE);
                $dateObj = new DateTime($i['trip_date'], $tz);
                $isPast  = $dateObj < new DateTime('today', $tz);
            ?>
            <tr class="itinerary-row<?= $isPast ? ' past-booking' : '' ?>" id="itn-row-<?= (int)$i['id'] ?>">
                <td data-label="Client"><?= e($i['client_name']) ?></td>
                <td data-label="Date"><?= e($dateObj->format('d/m/y')) ?></td>
                <td data-label="Time"><?= $i['start_time'] ? e(substr($i['start_time'], 0, 5)) : '<em>TBC</em>' ?></td>
                <td data-label="Pickup"><?= !empty($i['pickup']) ? e($i['pickup']) : '<em>TBC</em>' ?></td>
                <td data-label="Destination"><?= !empty($i['destination']) ? e($i['destination']) : '<em>TBC</em>' ?></td>
                <td data-label="Stops"><?= (int)$i['stop_count'] ?></td>
                <td data-label="Status">
                    <span class="at-itn-status at-itn-status-<?= e($i['status']) ?>"><?= $i['status'] === 'confirmed' ? 'Confirmed' : 'Planning' ?></span>
                </td>
                <td data-label="Actions">
                    <div class="actions-container">
                        <a href="<?= BASE_URL ?>/modules/Itineraries/edit.php?id=<?= (int)$i['id'] ?>"
                           class="action-btn edit-btn">✏️ Edit</a>
                        <button class="action-btn delete-btn" data-id="<?= (int)$i['id'] ?>">🗑️ Delete</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
    $(document).ready(function () {
        $(document).on('click', '.delete-btn', function () {
            if (!confirm('Delete this itinerary and all its stops?')) return;
            var id  = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Deleting…');

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Itineraries/api/index.php?action=delete',
                data:     { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#itn-row-' + id).fadeOut(400, function () { $(this).remove(); });
                    } else {
                        alert('❌ ' + (res.message || 'Could not delete itinerary.'));
                        btn.prop('disabled', false).text('🗑️ Delete');
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    btn.prop('disabled', false).text('🗑️ Delete');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
