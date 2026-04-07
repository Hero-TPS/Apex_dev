<?php
// modules/Prebookings/index.php
$page_title    = 'Prebookings';
$page_subtitle = 'Tentative Bookings';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Prebookings']]);
include ROOT_DIR . '/includes/header.php';

$stmt = $pdo->query("
    SELECT p.*, c.name AS client_name, c.phone AS client_phone
    FROM prebookings p
    JOIN contacts c ON p.contact_id = c.id
    WHERE p.converted_booking_id IS NULL
    ORDER BY p.trip_date ASC, p.start_time ASC
");
$prebookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-actions">
    <a href="<?= BASE_URL ?>/modules/Prebookings/add.php" class="page-action-btn add">📋 Add Prebooking</a>
</div>

<?php if (empty($prebookings)): ?>
    <p class="empty-state">No open prebookings. <a href="<?= BASE_URL ?>/modules/Prebookings/add.php">Add one</a>.</p>
<?php else: ?>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Date</th>
                <th>Time</th>
                <th>Pickup</th>
                <th>Destination</th>
                <th>Cost</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prebookings as $p):
                $tz      = new DateTimeZone(TIME_ZONE);
                $dateObj = new DateTime($p['trip_date'], $tz);
                $isPast  = $dateObj < new DateTime('today', $tz);

                $effPickup = !empty($p['was_swapped'])
                    ? ($p['original_destination'] ?? '')
                    : ($p['original_pickup'] ?? '');
                $effDest   = !empty($p['was_swapped'])
                    ? ($p['original_pickup'] ?? '')
                    : ($p['original_destination'] ?? '');

                $waPhone = formatPhoneNumberForWhatsApp($p['client_phone'] ?? '');
                $waMsg   = createPrebookingWhatsAppMessage([
                    'client_name'          => $p['client_name'],
                    'trip_date'            => $p['trip_date'],
                    'start_time'           => $p['start_time'] ?? '',
                    'original_pickup'      => $p['original_pickup'] ?? '',
                    'original_destination' => $p['original_destination'] ?? '',
                    'was_swapped'          => $p['was_swapped'] ?? 0,
                    'cost'                 => $p['cost'] ?? '',
                    'description'          => $p['description'] ?? '',
                ]);
                $waUrl = $waPhone ? buildWhatsAppUrl($p['client_phone'], $waMsg) : '#';
            ?>
            <tr class="prebooking-row<?= $isPast ? ' past-booking' : '' ?>" id="pre-row-<?= (int)$p['id'] ?>">
                <td data-label="Client"><?= e($p['client_name']) ?></td>
                <td data-label="Date"><?= e($dateObj->format('d/m/y')) ?></td>
                <td data-label="Time"><?= $p['start_time'] ? e(substr($p['start_time'], 0, 5)) : '<em>TBC</em>' ?></td>
                <td data-label="Pickup"><?= $effPickup !== '' ? e($effPickup) : '<em>TBC</em>' ?></td>
                <td data-label="Destination"><?= $effDest !== '' ? e($effDest) : '<em>TBC</em>' ?></td>
                <td data-label="Cost"><?= $p['cost'] ? 'R' . e(number_format((float)$p['cost'], 2)) : '<em>TBC</em>' ?></td>
                <td data-label="Notes"><?= $p['description'] ? e($p['description']) : '' ?></td>
                <td data-label="Actions">
                    <div class="actions-container">
                        <?php if ($waPhone): ?>
                            <a href="<?= htmlspecialchars($waUrl, ENT_QUOTES) ?>"
                               target="_blank" rel="noopener"
                               class="action-btn whatsapp-btn">💬 Send Reminder</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/modules/Prebookings/edit.php?id=<?= (int)$p['id'] ?>"
                           class="action-btn edit-btn">✏️ Edit</a>
                        <button class="action-btn convert-btn" data-id="<?= (int)$p['id'] ?>">🚗 Convert</button>
                        <button class="action-btn delete-btn"  data-id="<?= (int)$p['id'] ?>">🗑️ Delete</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
    $(document).ready(function () {

        // Convert prebooking → full booking form
        $(document).on('click', '.convert-btn', function () {
            if (!confirm('This will delete the tentative calendar event and open the booking form with the details prefilled. Continue?')) {
                return;
            }
            var id  = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Converting…');

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=convert',
                data:     { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.redirect_url) {
                        window.location.href = res.redirect_url;
                    } else {
                        alert('❌ ' + (res.message || 'Could not convert prebooking.'));
                        btn.prop('disabled', false).text('🚗 Convert');
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    btn.prop('disabled', false).text('🚗 Convert');
                }
            });
        });

        // Delete prebooking
        $(document).on('click', '.delete-btn', function () {
            if (!confirm('Delete this prebooking and remove it from Google Calendar?')) return;
            var id  = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Deleting…');

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=delete',
                data:     { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#pre-row-' + id).fadeOut(400, function () { $(this).remove(); });
                    } else {
                        alert('❌ ' + (res.message || 'Could not delete prebooking.'));
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