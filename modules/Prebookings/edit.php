<?php
// modules/Prebookings/edit.php
$page_title    = 'Edit Prebooking';
$page_subtitle = 'Edit Prebooking';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Prebookings', 'url' => BASE_URL . '/modules/Prebookings/'],
    ['label' => 'Edit Prebooking'],
]);
include ROOT_DIR . '/includes/header.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="error-message">Invalid prebooking ID.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS client_name
    FROM prebookings p
    JOIN contacts c ON p.contact_id = c.id
    WHERE p.id = ? AND p.converted_booking_id IS NULL
    LIMIT 1
");
$stmt->execute([$id]);
$prebooking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prebooking) {
    echo '<div class="error-message">Prebooking not found or already converted.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();

$savedPickup   = $prebooking['original_pickup'] ?? '';
$savedDest     = $prebooking['original_destination'] ?? '';
$savedCost     = $prebooking['cost'] ?? '';
$pickupInList  = $savedPickup !== '' && in_array($savedPickup, array_column($destinations, 'name'), true);
$destInList    = in_array($savedDest, array_column($destinations, 'name'), true);
$costInList    = $savedCost !== '' && in_array(number_format((float)$savedCost, 2), array_map(fn($c) => number_format((float)$c['amount'], 2), $costs), true);
$showOtherPickup = $savedPickup !== '' && !$pickupInList;
$showOtherDest = $savedDest !== '' && !$destInList;
$showOtherCost = $savedCost !== '' && !$costInList;
?>

<div class="form-container">
    <form id="editPrebookingForm">
        <input type="hidden" id="prebooking_id" name="id" value="<?= (int)$prebooking['id'] ?>">

        <div class="form-group">
            <label>Client</label>
            <input type="text" value="<?= e($prebooking['client_name']) ?>" disabled>
        </div>

        <div class="form-group">
            <label for="trip_date">Date <span class="required">*</span></label>
            <input type="date" id="trip_date" name="trip_date"
                   value="<?= e($prebooking['trip_date']) ?>" required>
        </div>

        <div class="form-group">
            <label for="start_time">Time <small>(optional)</small></label>
            <select id="start_time" name="start_time">
                <option value="">— Not known yet —</option>
                <?php foreach ($timeOptions as $time):
                    $savedHHMM = $prebooking['start_time'] ? substr($prebooking['start_time'], 0, 5) : '';
                    $sel = ($savedHHMM === $time) ? 'selected' : '';
                ?>
                    <option value="<?= e($time) ?>" <?= $sel ?>><?= e($time) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="original_pickup">Pickup Location <small>(optional)</small></label>
            <select id="original_pickup" name="original_pickup">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest):
                    $sel = (!$showOtherPickup && $savedPickup === $dest['name']) ? 'selected' : '';
                ?>
                    <option value="<?= e($dest['name']) ?>" <?= $sel ?>><?= e($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherPickup ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>

        <div class="form-group <?= $showOtherPickup ? '' : 'hidden' ?>" id="otherPickupGroup">
            <label for="otherPickup">Specify Other Pickup</label>
            <input type="text" id="otherPickup" name="other_original_pickup"
                   placeholder="Enter pickup address"
                   value="<?= $showOtherPickup ? e($savedPickup) : '' ?>">
        </div>

        <div class="form-group">
            <label for="original_destination">Destination <small>(optional)</small></label>
            <select id="original_destination" name="original_destination">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest):
                    $sel = (!$showOtherDest && $savedDest === $dest['name']) ? 'selected' : '';
                ?>
                    <option value="<?= e($dest['name']) ?>" <?= $sel ?>><?= e($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherDest ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>

        <div class="form-group <?= $showOtherDest ? '' : 'hidden' ?>" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination</label>
            <input type="text" id="otherDestination" name="other_original_destination"
                   placeholder="Enter destination"
                   value="<?= $showOtherDest ? e($savedDest) : '' ?>">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="swapLocations" name="swap_locations"
                    <?php if ($prebooking['was_swapped']) echo 'checked'; ?>>
                🔄 Swap pickup and destination locations
            </label>
        </div>

        <div class="form-group">
            <label for="cost">Cost <small>(optional)</small></label>
            <select id="cost" name="cost">
                <option value="">— Not known yet —</option>
                <?php foreach ($costs as $c):
                    $sel = (!$showOtherCost && $savedCost !== '' && number_format((float)$savedCost, 2) === number_format((float)$c['amount'], 2)) ? 'selected' : '';
                ?>
                    <option value="<?= e($c['amount']) ?>" <?= $sel ?>>R<?= e(number_format((float)$c['amount'], 2)) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherCost ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>

        <div class="form-group <?= $showOtherCost ? '' : 'hidden' ?>" id="otherCostGroup">
            <label for="otherCost">Specify Other Cost</label>
            <input type="number" id="otherCost" name="other_cost" step="0.01" min="0"
                   placeholder="0.00"
                   value="<?= $showOtherCost ? e($savedCost) : '' ?>">
        </div>

        <div class="form-group">
            <label for="description">Notes / Description <small>(optional)</small></label>
            <textarea id="description" name="description"
                      placeholder="Any relevant info about this booking..."><?= e($prebooking['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn" id="submitBtn">💾 Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/Prebookings/" class="page-action-btn back">← Cancel</a>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
    $(document).ready(function () {

        $('#original_pickup').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherPickupGroup').removeClass('hidden');
            } else {
                $('#otherPickupGroup').addClass('hidden');
                $('#otherPickup').val('');
            }
        });

        $('#original_destination').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherDestinationGroup').removeClass('hidden');
            } else {
                $('#otherDestinationGroup').addClass('hidden');
                $('#otherDestination').val('');
            }
        });

        $('#cost').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherCostGroup').removeClass('hidden');
            } else {
                $('#otherCostGroup').addClass('hidden');
                $('#otherCost').val('');
            }
        });

        $('#editPrebookingForm').on('submit', function (e) {
            e.preventDefault();

            var submitBtn = $('#submitBtn');
            var loading   = $('#loading');
            submitBtn.hide();
            loading.show();
            $('#result').html('');

            var destSelect = $('#original_destination');
            var destVal    = destSelect.val() === 'other' ? $('#otherDestination').val().trim() : destSelect.val();

            var pickupSelect = $('#original_pickup');
            var pickupVal    = pickupSelect.val() === 'other' ? $('#otherPickup').val().trim() : pickupSelect.val();

            var costSelect = $('#cost');
            var costVal    = costSelect.val() === 'other' ? $('#otherCost').val().trim() : costSelect.val();

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=update',
                data: {
                    id:                   $('#prebooking_id').val(),
                    trip_date:            $('#trip_date').val(),
                    start_time:           $('#start_time').val(),
                    original_pickup:      pickupVal,
                    original_destination: destVal,
                    swap_locations:       $('#swapLocations').is(':checked') ? '1' : '',
                    cost:                 costVal,
                    description:          $('#description').val().trim(),
                },
                dataType: 'json',
                success: function (res) {
                    loading.hide();
                    submitBtn.show();
                    if (res.success) {
                        $('#result').html('<div class="success-message">✅ ' + res.message + '</div>');
                        setTimeout(function () {
                            window.location.href = '<?= BASE_URL ?>/modules/Prebookings/';
                        }, 600);
                    } else {
                        $('#result').html('<div class="error-message">❌ ' + (res.message || 'Failed to save.') + '</div>');
                    }
                },
                error: function (xhr) {
                    loading.hide();
                    submitBtn.show();
                    $('#result').html('<div class="error-message">❌ Error: ' + (xhr.responseText || 'Unknown error') + '</div>');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>