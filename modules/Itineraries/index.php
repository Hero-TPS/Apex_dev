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

// b.id is NULL when the itinerary is not linked, or its booking has since been deleted
$stmt = $pdo->query("
    SELECT i.*, c.name AS client_name,
           (SELECT COUNT(*) FROM itinerary_stops s WHERE s.itinerary_id = i.id) AS stop_count,
           b.id AS linked_booking_id, b.trip_date AS linked_booking_date,
           GREATEST(
               COALESCE((SELECT MAX(s2.distance_km) FROM itinerary_stops s2 WHERE s2.itinerary_id = i.id), 0),
               COALESCE(i.final_leg_km, 0), COALESCE(i.return_leg_km, 0)
           ) AS longest_leg_km
    FROM itineraries i
    JOIN contacts c ON i.contact_id = c.id
    LEFT JOIN bookings b ON b.id = i.booking_id
    ORDER BY i.trip_date ASC, i.start_time ASC
");
$itineraries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$distanceLimit = getDistanceWarningKm($pdo);
?>

<div class="page-actions">
    <a href="<?= BASE_URL ?>/modules/Itineraries/add.php" class="page-action-btn add">🗺️ Add Itinerary</a>
</div>

<div id="listResult"></div>

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
                <th>Km</th>
                <th>Status</th>
                <th>Booking</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itineraries as $i):
                $tz      = new DateTimeZone(TIME_ZONE);
                $dateObj = new DateTime($i['trip_date'], $tz);
                $isPast  = $dateObj < new DateTime('today', $tz);
                $hasBooking = !empty($i['linked_booking_id']);
                $linkedRow  = $hasBooking ? ['id' => $i['linked_booking_id']] : null;
                $outOfDate  = itineraryBookingOutOfDate($i, $linkedRow);
            ?>
            <tr class="itinerary-row<?= $isPast ? ' past-booking' : '' ?>" id="itn-row-<?= (int)$i['id'] ?>">
                <td data-label="Client"><?= e($i['client_name']) ?></td>
                <td data-label="Date"><?= e($dateObj->format('d/m/y')) ?></td>
                <td data-label="Time"><?= $i['start_time'] ? e(substr($i['start_time'], 0, 5)) : '<em>TBC</em>' ?></td>
                <td data-label="Pickup"><?= !empty($i['pickup']) ? e($i['pickup']) : '<em>TBC</em>' ?></td>
                <td data-label="Destination"><?= !empty($i['destination']) ? e($i['destination']) : '<em>TBC</em>' ?></td>
                <td data-label="Stops"><?= (int)$i['stop_count'] ?></td>
                <td data-label="Km">
                    <?php if ($i['total_km'] !== null): ?>
                        <?= e(number_format((float)$i['total_km'], 1)) ?><?= (int)$i['unknown_legs'] > 0 ? '+' : '' ?>
                        <?php if ((float)$i['longest_leg_km'] > $distanceLimit): ?>
                            <span title="A leg is over <?= e((string)$distanceLimit) ?> km. Open the itinerary and check the addresses.">⚠️</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <em>—</em>
                    <?php endif; ?>
                </td>
                <td data-label="Status">
                    <span class="at-itn-status at-itn-status-<?= $hasBooking ? 'booked' : 'planning' ?>"><?= e(itineraryStatusLabel($linkedRow)) ?></span>
                    <?php if ($outOfDate): ?><span class="at-itn-stale-tag" title="Changed since the booking was last updated">⚠️ Changed</span><?php endif; ?>
                </td>
                <td data-label="Booking">
                    <?php if ($hasBooking): ?>
                        <a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=<?= (int)$i['linked_booking_id'] ?>">#<?= (int)$i['linked_booking_id'] ?></a>
                    <?php else: ?>
                        <em>—</em>
                    <?php endif; ?>
                </td>
                <td data-label="Actions">
                    <div class="actions-container">
                        <a href="<?= BASE_URL ?>/modules/Itineraries/edit.php?id=<?= (int)$i['id'] ?>"
                           class="action-btn edit-btn">✏️ Edit</a>
                        <?php if ($hasBooking): ?>
                            <button class="action-btn convert-btn at-itn-update-booking-btn"
                                    data-id="<?= (int)$i['id'] ?>"
                                    data-booking="<?= (int)$i['linked_booking_id'] ?>">🔄 Update Booking</button>
                        <?php else: ?>
                            <a href="<?= e(itineraryConvertUrl($i)) ?>" class="action-btn convert-btn">🚗 Convert</a>
                        <?php endif; ?>
                        <button class="action-btn delete-btn"
                                data-id="<?= (int)$i['id'] ?>"
                                data-booking="<?= $hasBooking ? (int)$i['linked_booking_id'] : 0 ?>">🗑️ Delete</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteItnModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Delete this itinerary?</h3>
        <p>The itinerary and all its stops will be deleted. This cannot be undone.</p>
        <label id="deleteBookingRow" class="at-itn-modal-check hidden">
            <input type="checkbox" id="deleteBookingCheck">
            <span>Also delete the linked booking <strong id="deleteBookingLabel"></strong> and its calendar event</span>
        </label>
        <div class="modal-buttons">
            <button id="confirmDeleteItnBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteItnBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var api = '<?= BASE_URL ?>/modules/Itineraries/api/index.php';

        function post(action, data, btn, busyText, done) {
            var original = btn ? btn.text() : '';
            if (btn) { btn.prop('disabled', true).text(busyText); }
            $.ajax({
                type: 'POST',
                url: api + '?action=' + action,
                data: data,
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        done(res);
                    } else {
                        alert('❌ ' + (res.message || 'Something went wrong.'));
                        if (btn) { btn.prop('disabled', false).text(original); }
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    if (btn) { btn.prop('disabled', false).text(original); }
                }
            });
        }

        $(document).on('click', '.at-itn-update-booking-btn', function () {
            var btn = $(this);
            if (!confirm('This will update booking #' + btn.data('booking') + ' with this itinerary\'s date, time, pickup, destination, passenger, cost, notes and stops.\n\nThe driver, payment details and the booking\'s calendar event are not changed. Continue?')) {
                return;
            }
            post('update_booking', { id: btn.data('id') }, btn, 'Updating…', function (res) {
                $('#listResult').html('<div class="success-message">✅ ' + $('<div>').text(res.message).html() + '</div>');
                btn.prop('disabled', false).text('🔄 Update Booking');
                btn.closest('tr').find('.at-itn-stale-tag').remove();
            });
        });

        // Delete (with the option to take the linked booking along)
        var modal = $('#deleteItnModal');
        var idToDelete = null;

        $(document).on('click', '.delete-btn', function () {
            idToDelete = $(this).data('id');
            var bookingId = parseInt($(this).data('booking'), 10) || 0;
            $('#deleteBookingCheck').prop('checked', false);
            if (bookingId > 0) {
                $('#deleteBookingLabel').text('#' + bookingId);
                $('#deleteBookingRow').removeClass('hidden');
            } else {
                $('#deleteBookingRow').addClass('hidden');
            }
            modal.css('display', 'flex');
        });

        $('#cancelDeleteItnBtn').on('click', function () {
            modal.hide();
            idToDelete = null;
        });

        $('#confirmDeleteItnBtn').on('click', function () {
            if (!idToDelete) { return; }
            var btn = $(this);
            var id = idToDelete;
            var alsoBooking = $('#deleteBookingCheck').is(':checked') && !$('#deleteBookingRow').hasClass('hidden');
            post('delete', { id: id, delete_booking: alsoBooking ? '1' : '' }, btn, 'Deleting…', function (res) {
                modal.hide();
                btn.prop('disabled', false).text('Yes, Delete');
                $('#itn-row-' + id).fadeOut(400, function () { $(this).remove(); });
                $('#listResult').html('<div class="success-message">✅ ' + $('<div>').text(res.message).html() + '</div>');
                idToDelete = null;
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
