<?php
// modules/Itineraries/edit.php
$page_title    = 'Edit Itinerary';
$page_subtitle = 'Edit Itinerary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Itineraries', 'url' => BASE_URL . '/modules/Itineraries/'],
    ['label' => 'Edit Itinerary'],
]);
include ROOT_DIR . '/includes/header.php';

if (!itineraryMigrationReady($pdo)) {
    echo itineraryMigrationNotice();
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="error-message">Invalid itinerary ID.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$itn = fetchItinerary($pdo, $id);
if (!$itn) {
    echo '<div class="error-message">Itinerary not found.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$stopsData    = fetchItineraryStops($pdo, $id);
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();

$isConfirmed = $itn['status'] === 'confirmed';
$linked      = fetchLinkedBooking($pdo, (int) ($itn['booking_id'] ?? 0));
?>

<div class="page-actions">
    <?php if ($isConfirmed): ?>
        <button type="button" class="page-action-btn toggle" id="reopenBtn">↩️ Reopen for Planning</button>
    <?php else: ?>
        <button type="button" class="page-action-btn confirm" id="confirmBtn">✅ Confirm Itinerary</button>
    <?php endif; ?>

    <?php if ($linked): ?>
        <button type="button" class="page-action-btn rebook" id="updateBookingBtn">🔄 Update Booking #<?= (int) $linked['id'] ?></button>
        <a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=<?= (int) $linked['id'] ?>" class="page-action-btn primary">📅 View Booking #<?= (int) $linked['id'] ?></a>
    <?php else: ?>
        <a href="<?= e(itineraryConvertUrl($itn)) ?>" class="page-action-btn rebook" id="convertBtn">🚗 Convert to Booking</a>
    <?php endif; ?>
</div>

<?php if ($isConfirmed): ?>
    <div class="at-itn-confirmed-banner">✅ Confirmed. Reopen it for planning to make changes.</div>
<?php endif; ?>
<div id="actionResult"></div>

<?php
include __DIR__ . '/form.php';
?>

<script>
    $(document).ready(function () {
        var api   = '<?= BASE_URL ?>/modules/Itineraries/api/index.php';
        var id    = <?= (int) $itn['id'] ?>;
        var dirty = false;

        // Confirm, Convert and Update Booking all work on the SAVED itinerary, so unsaved edits must be saved first
        $('#itineraryForm').on('input change', function () { dirty = true; });
        $(document).on('click', '#addStopBtn, .at-itn-up, .at-itn-down, .at-itn-remove', function () { dirty = true; });

        function blockIfDirty() {
            if (dirty) {
                alert('You have unsaved changes. Click "Save Changes" first.');
                return true;
            }
            return false;
        }

        function call(action, btn, busyText, done) {
            var original = btn.text();
            btn.prop('disabled', true).text(busyText);
            $.ajax({
                type: 'POST',
                url: api + '?action=' + action,
                data: { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        done(res);
                    } else {
                        alert('❌ ' + (res.message || 'Something went wrong.'));
                        btn.prop('disabled', false).text(original);
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    btn.prop('disabled', false).text(original);
                }
            });
        }

        $('#confirmBtn').on('click', function () {
            if (blockIfDirty()) { return; }
            call('confirm', $(this), 'Confirming…', function () { location.reload(); });
        });

        $('#reopenBtn').on('click', function () {
            call('reopen', $(this), 'Reopening…', function () { location.reload(); });
        });

        $('#convertBtn').on('click', function (e) {
            if (blockIfDirty()) { e.preventDefault(); }
        });

        $('#updateBookingBtn').on('click', function () {
            if (blockIfDirty()) { return; }
            if (!confirm('This will update booking #<?= $linked ? (int) $linked['id'] : 0 ?> with this itinerary\'s date, time, pickup, destination, passenger, cost, notes and stops.\n\nThe driver, payment details and the booking\'s calendar event are not changed. Continue?')) {
                return;
            }
            var btn = $(this);
            var label = btn.text();
            call('update_booking', btn, 'Updating…', function (res) {
                $('#actionResult').html('<div class="success-message">✅ ' + $('<div>').text(res.message).html() + '</div>');
                btn.prop('disabled', false).text(label);
            });
        });
    });
</script>

<?php
include ROOT_DIR . '/includes/footer.php';
