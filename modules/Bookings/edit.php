<?php
// modules/Bookings/edit.php
//booking edit
// Shared includes
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$page_title = 'Edit Booking';
$page_subtitle = 'Edit Booking';
$show_breadcrumb = true;
$breadcrumb = buildBreadcrumb([
    ['label' => 'Bookings', 'url' => BASE_URL . '/modules/Bookings/'],
    ['label' => 'Edit Booking'],
]);
include ROOT_DIR . '/includes/header.php';

$booking = null;
$error_message = '';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    if ($bookingId > 0) {
        try {
            $booking = getBookingById($pdo, $bookingId);

            if ($booking) {
                $start_t = new DateTime($booking['start_time']);
                $end_t = new DateTime($booking['end_time']);
                $interval = $start_t->diff($end_t);
                $booking['duration'] = $interval->h + ($interval->i / 60);
            } else {
                $error_message = "Booking not found.";
            }

            // Fetch dropdowns
            $contacts = fetchData($pdo, 'contacts', 'name ASC');
            $destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
            $costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
            $durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
            $timeOptions = generateTimeOptions();

            // Fetch active drivers for allocation dropdown
            $driversStmt = $pdo->query("SELECT id, name, phone FROM drivers WHERE active = 1 ORDER BY name ASC");
            $drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

            // Booking fee percentage for JS calculation
            $booking_fee_pct = (float) getSystemVariable($pdo, 'apex_booking_fee_pct');

            // Cape Town Airport pickup reminder — shown as a live hint only; never saved into description
            $airport_pickup_notice = (string) getSystemVariable($pdo, 'airport_pickup_notice');
        } catch (PDOException $e) {
            error_log('Edit form DB error: ' . $e->getMessage());
            $error_message = "Failed to load data.";
        }
    } else {
        $error_message = "Invalid booking ID.";
    }
} else {
    $error_message = "No booking ID provided.";
}
?>

<div class="container">
    <?php if ($booking): ?>
        <form id="editBookingForm">
            <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking['id']); ?>">
            <div class="form-group">
                <label for="contact">Client <span class="required">*</span></label>
                <select id="contact" name="contact_id" required>
                    <?php foreach ($contacts as $contact): ?>
                        <option value="<?php echo $contact['id']; ?>" 
                                data-phone="<?php echo htmlspecialchars($contact['phone']); ?>" 
                                data-address="<?php echo htmlspecialchars($contact['address']); ?>"
                                <?php if ($contact['id'] == $booking['contact_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($contact['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" id="phone" name="phone" value="">
            <div class="form-row">
                <div class="form-group">
                    <label for="date">Trip Date <span class="required">*</span></label>
                    <input type="date" id="date" name="trip_date" required value="<?php echo htmlspecialchars($booking['trip_date']); ?>">
                </div>
                <div class="form-group">
                    <label for="startTime">Start Time <span class="required">*</span></label>
                    <select id="startTime" name="start_time" required>
                        <?php
                        foreach ($timeOptions as $time):
                            $time_24h = date("H:i", strtotime($booking['start_time']));
                            ?>
                            <option value="<?php echo $time; ?>" <?php if ($time == $time_24h) echo 'selected'; ?>>
                                <?php echo $time; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Duration -->
            <div class="form-group">
                <label for="duration">Duration (hours) <span class="required">*</span></label>
                <select id="duration" name="duration" required>
                    <?php foreach ($durations as $dur): ?>
                        <option value="<?= htmlspecialchars($dur) ?>" 
                                <?= (abs((float) $dur - (float) $booking['duration']) < 0.01) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dur) ?> hour<?= ($dur != '1' && $dur != '1.0') ? 's' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Overlap Warning (soft — never blocks saving) -->
            <div class="form-group hidden" id="overlapWarningGroup">
                <div class="at-overlap-warning" id="overlapWarningBox"></div>
            </div>

            <div class="form-group">
                <label for="pickup">Pickup Location <span class="required">*</span></label>
                <select id="pickup" name="original_pickup" required></select>
            </div>
            <div class="form-group hidden" id="otherPickupGroup">
                <label for="otherPickup">Specify Other Pickup Location</label>
                <input type="text" id="otherPickup" name="other_pickup_location">
            </div>
            <div class="form-group">
                <label for="destination">Destination <span class="required">*</span></label>
                <select id="destination" name="original_destination" required>
                    <?php
                    $isStandardDestination = in_array($booking['original_destination'], $destinations);
                    foreach ($destinations as $dest):
                        ?>
                        <option value="<?php echo htmlspecialchars($dest); ?>" <?php if ($dest == $booking['original_destination']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($dest); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other" <?php if (!$isStandardDestination) echo 'selected'; ?>>Other</option>
                </select>
            </div>
            <div class="form-group <?php if ($isStandardDestination) echo 'hidden'; ?>" id="otherDestinationGroup">
                <label for="otherDestination">Specify Other Destination</label>
                <input type="text" id="otherDestination" name="other_destination">
            </div>
            <!-- ✅ ADD: Add to destinations list checkbox -->
            <div class="form-group hidden" id="addToDestinationGroup">
                <label>
                    <input type="checkbox" id="addToDestinations" name="add_to_destinations">
                    🔄 Add new destination to list
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="swapLocations" name="swap_locations" <?php if ($booking['was_swapped']) echo 'checked'; ?>>
                    🔄 Swap pickup and destination locations
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="roundTrip" name="round_trip" <?php if (!empty($booking['is_round_trip'])) echo 'checked'; ?>>
                    🔁 Round trip (double the calculated distance)
                </label>
            </div>
            <div class="form-group">
                <label for="cost">Cost <span class="required">*</span></label>
                <select id="cost" name="cost" required>
                    <?php
                    $isStandardCost = in_array($booking['cost'], $costs);
                    foreach ($costs as $c):
                        ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php if ($c == $booking['cost']) echo 'selected'; ?>>
                            R<?php echo htmlspecialchars(number_format($c, 2)); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other" <?php if (!$isStandardCost) echo 'selected'; ?>>Other</option>
                </select>
            </div>
            <div class="form-group <?php if ($isStandardCost) echo 'hidden'; ?>" id="otherCostGroup">
                <label for="otherCost">Specify Other Cost</label>
                <input type="number" id="otherCost" name="other_cost">
            </div>

            <!-- ✅ Payment Method: EFT Checkbox -->
            <div class="form-group">
                <label>
                    <input type="checkbox" id="payment_method" name="payment_method" value="eft"
                           <?= $booking['payment_method'] === 'eft' ? 'checked' : '' ?>>
                    EFT Payment
                </label>
            </div>

            <!-- Payment Received -->
            <div class="form-group">
                <label>
                    <input type="checkbox" id="payment_received" name="payment_received" value="1"
                           <?= !empty($booking['payment_received']) ? 'checked' : '' ?>>
                    Payment Received
                </label>
            </div>

            <div class="form-group">
                <label for="flightNumber">Flight Number (if applicable)</label>
                <input type="text" id="flightNumber" name="flight_number" value="<?php echo htmlspecialchars($booking['flight_number']); ?>">
            </div>
            <div class="form-group">
                <label for="description">Additional Notes</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($booking['description']); ?></textarea>
                <small id="airportNoticeHint" class="at-help-text hidden">✈️ Airport pickup detected — this will be added to the notes automatically: "<?= htmlspecialchars($airport_pickup_notice ?? '') ?>"</small>
            </div>

            <!-- Driver Allocation -->
            <div class="form-group">
                <label for="driver_id">Allocate Driver</label>
                <select id="driver_id" name="driver_id">
                    <option value="">— No driver —</option>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?= htmlspecialchars($driver['id']) ?>"
                            <?= ($booking['driver_id'] == $driver['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($driver['name']) ?>
                            <?= $driver['phone'] ? ' (' . htmlspecialchars($driver['phone']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Optional — assign a driver to this booking</small>
            </div>

            <!-- Budget Earmark -->
            <div class="form-group">
                <label>Earmark Income For</label>
                <div class="at-earmark-row">
                    <label class="at-earmark-checkbox">
                        <input type="checkbox" id="earmark_rent_check"
                            <?= !empty($booking['earmarked_rent']) ? 'checked' : '' ?>>
                        Rent
                    </label>
                    <input type="number" id="earmark_rent_amount" step="0.01" min="0"
                        value="<?= htmlspecialchars($booking['earmarked_rent'] ?? '') ?>"
                        style="<?= empty($booking['earmarked_rent']) ? 'display:none;' : '' ?>">
                </div>
                <div class="at-earmark-row">
                    <label class="at-earmark-checkbox">
                        <input type="checkbox" id="earmark_debt_check"
                            <?= !empty($booking['earmarked_debt']) ? 'checked' : '' ?>>
                        Debt
                    </label>
                    <input type="number" id="earmark_debt_amount" step="0.01" min="0"
                        value="<?= htmlspecialchars($booking['earmarked_debt'] ?? '') ?>"
                        style="<?= empty($booking['earmarked_debt']) ? 'display:none;' : '' ?>">
                </div>
                <small>A booking can partially or fully fund rent, debt, or both</small>
            </div>

            <?php if ($booking_fee_pct > 0): ?>
            <div class="form-group<?= empty($booking['driver_id']) ? ' hidden' : '' ?>" id="bookingFeeGroup">
                <label>Apex Booking Fee (<?= htmlspecialchars($booking_fee_pct) ?>%)</label>
                <div id="bookingFeeDisplay">
                    <?php
                    $currentFee = !empty($booking['booking_fee']) ? (float) $booking['booking_fee'] : calculateBookingFee((float) $booking['cost'], $booking_fee_pct);
                    echo 'R' . number_format($currentFee, 2);
                    ?>
                </div>
                <small>Recalculated from cost × <?= htmlspecialchars($booking_fee_pct) ?>% when saved</small>
            </div>
            <?php endif; ?>

            <!-- No Booking Fee -->
            <div class="form-group<?= empty($booking['driver_id']) ? ' hidden' : '' ?>" id="noBookingFeeGroup">
                <label>
                    <input type="checkbox" id="no_booking_fee" name="no_booking_fee" value="1"
                           <?= !empty($booking['no_booking_fee']) ? 'checked' : '' ?>>
                    No Booking Fee (full amount goes to driver)
                </label>
            </div>

            <!-- Driver Notes -->
            <div class="form-group<?= empty($booking['driver_id']) ? ' hidden' : '' ?>" id="driverNotesGroup">
                <label for="driver_notes">Driver Notes</label>
                <textarea id="driver_notes" name="driver_notes" placeholder="Instructions for the driver..."><?= htmlspecialchars($booking['driver_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="page-action-btn save" id="submitBtn">💾 Update Booking</button>
        </form>
    <?php else: ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
$(document).ready(function () {
    var bookingCost = <?= (float) $booking['cost'] ?>;

    // Earmark auto-saves independently of the main form (dedicated lightweight endpoint)
    function saveEarmark() {
        $.post('<?= BASE_URL ?>/modules/Bookings/api/index.php', {
            action: 'set_earmark',
            booking_id: <?= (int) $booking['id'] ?>,
            earmarked_rent: $('#earmark_rent_check').is(':checked') ? $('#earmark_rent_amount').val() : '',
            earmarked_debt: $('#earmark_debt_check').is(':checked') ? $('#earmark_debt_amount').val() : ''
        }, function (response) {
            if (!response.success) {
                alert('Could not save earmark: ' + response.message);
            }
        }, 'json');
    }

    $('#earmark_rent_check').on('change', function () {
        var amountField = $('#earmark_rent_amount');
        if ($(this).is(':checked')) {
            amountField.val(bookingCost.toFixed(2)).show();
        } else {
            amountField.hide();
        }
        saveEarmark();
    });
    $('#earmark_debt_check').on('change', function () {
        var amountField = $('#earmark_debt_amount');
        if ($(this).is(':checked')) {
            amountField.val(bookingCost.toFixed(2)).show();
        } else {
            amountField.hide();
        }
        saveEarmark();
    });
    $('#earmark_rent_amount, #earmark_debt_amount').on('change', saveEarmark);

    // ⚠️ Overlap check — soft warning only, never blocks saving; excludes this booking's own slot
    var overlapCheckTimer = null;
    var overlapExcludeId = <?= (int) $booking['id'] ?>;
    function checkOverlap() {
        var date = $('#date').val();
        var time = $('#startTime').val();
        var duration = $('#duration').val();
        var warningGroup = $('#overlapWarningGroup');
        var warningBox = $('#overlapWarningBox');

        if (!date || !time || !duration) {
            warningGroup.addClass('hidden');
            warningBox.html('');
            return;
        }

        clearTimeout(overlapCheckTimer);
        overlapCheckTimer = setTimeout(function () {
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: { action: 'check_overlap', trip_date: date, start_time: time, duration: duration, exclude_id: overlapExcludeId },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.overlaps && response.overlaps.length > 0) {
                        var items = response.overlaps.map(function (o) {
                            var driverText = o.driver_name ? ('Driver: ' + escapeHtml(o.driver_name)) : 'No driver assigned';
                            return '<div class="at-overlap-item">' + o.start_time + '–' + o.end_time +
                                ' — ' + escapeHtml(o.client_name) + ' (' + driverText + ')</div>';
                        }).join('');
                        warningBox.html('⚠️ Overlaps with existing booking(s):' + items);
                        warningGroup.removeClass('hidden');
                    } else {
                        warningGroup.addClass('hidden');
                        warningBox.html('');
                    }
                },
                error: function () {
                    warningGroup.addClass('hidden');
                    warningBox.html('');
                }
            });
        }, 250);
    }
    $('#date, #startTime, #duration').on('change', checkOverlap);
    checkOverlap();

    var initialPickupValue = "<?php echo addslashes(htmlspecialchars($booking['original_pickup'], ENT_QUOTES)); ?>";
    var initialDestinationValue = "<?php echo addslashes(htmlspecialchars($booking['original_destination'], ENT_QUOTES)); ?>";

    function updatePickupOptions() {
        var selectedContact = $('#contact').find('option:selected');
        var address = selectedContact.data('address');
        var phone = selectedContact.data('phone');
        var pickupSelect = $('#pickup');
        $('#phone').val(phone);

        var options = '';
        var isStandardPickup = false;

        if (address) {
            options += '<option value="' + escapeHtml(address) + '">' + escapeHtml(address) + '</option>';
            if (initialPickupValue === address) {
                isStandardPickup = true;
            }
        }
        options += '<option value="other">Other</option>';
        pickupSelect.html(options);

        if (isStandardPickup) {
            pickupSelect.val(initialPickupValue);
            $('#otherPickupGroup').addClass('hidden');
            $('#otherPickup').prop('required', false);
        } else {
            pickupSelect.val('other');
            $('#otherPickup').val(initialPickupValue);
            $('#otherPickupGroup').removeClass('hidden');
            $('#otherPickup').prop('required', true);
        }
    }

    $('#contact').on('change', updatePickupOptions);
    updatePickupOptions();

    // ✅ Handle pickup "other" option toggle
    $('#pickup').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherPickupGroup').removeClass('hidden');
            $('#otherPickup').prop('required', true);
        } else {
            $('#otherPickupGroup').addClass('hidden');
            $('#otherPickup').prop('required', false);
        }
    });

    // ✅ Handle destination "other" option toggle
    $('#destination').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherDestinationGroup').removeClass('hidden');
            $('#addToDestinationGroup').removeClass('hidden');
            $('#otherDestination').prop('required', true);
        } else {
            $('#otherDestinationGroup').addClass('hidden');
            $('#addToDestinationGroup').addClass('hidden');
            $('#otherDestination').prop('required', false);
        }
    });

    // Initialize destination on page load
    if ($('#destination').val() === 'other') {
        $('#otherDestination').val(initialDestinationValue);
        $('#otherDestinationGroup').removeClass('hidden');
        $('#addToDestinationGroup').removeClass('hidden');
        $('#otherDestination').prop('required', true);
    }

    // ✈️ Airport pickup notice hint (preview only — never written into #description)
    function updateAirportNoticeHint() {
        var swapped = $('#swapLocations').is(':checked');
        var pickupVal = $('#pickup').val() === 'other' ? $('#otherPickup').val() : $('#pickup').val();
        var destVal = $('#destination').val() === 'other' ? $('#otherDestination').val() : $('#destination').val();
        var effectivePickup = swapped ? destVal : pickupVal;
        var pickupLower = (effectivePickup || '').toLowerCase();
        var isAirport = pickupLower.indexOf('cape town') !== -1
            && pickupLower.indexOf('airport') !== -1
            && pickupLower.indexOf('pickup') !== -1;
        $('#airportNoticeHint').toggleClass('hidden', !isAirport);
    }
    $('#pickup, #destination, #swapLocations').on('change', updateAirportNoticeHint);
    $('#otherPickup, #otherDestination').on('input', updateAirportNoticeHint);
    updateAirportNoticeHint();

    // ✅ Handle cost "other" option toggle
    $('#cost').on('change', function () {
        var selected = $(this).val();
        if (selected === 'other') {
            $('#otherCostGroup').removeClass('hidden');
            $('#otherCost').prop('required', true);
        } else {
            $('#otherCostGroup').addClass('hidden');
            $('#otherCost').prop('required', false);
        }
        updateBookingFee();
    });

    $('#otherCost').on('input', function () {
        updateBookingFee();
    });

    $('#driver_id').on('change', function () {
        updateBookingFee();
        updateDriverSections();
    });

    $('#no_booking_fee').on('change', function () {
        updateBookingFee();
    });

    function updateDriverSections() {
        var driverSelected = $('#driver_id').val() !== '';
        $('#noBookingFeeGroup').toggle(driverSelected);
        $('#driverNotesGroup').toggle(driverSelected);
        if (!driverSelected) {
            $('#driver_notes').val('');
            $('#no_booking_fee').prop('checked', false);
        }
    }

    var bookingFeePct = <?= (float) ($booking_fee_pct ?? 0) ?>;
    function updateBookingFee() {
        var driverSelected = $('#driver_id').val() !== '';
        var noFee = $('#no_booking_fee').is(':checked');
        var costVal = $('#cost').val() === 'other'
            ? parseFloat($('#otherCost').val()) || 0
            : parseFloat($('#cost').val()) || 0;
        var fee = parseFloat((costVal * bookingFeePct / 100).toFixed(2));
        if (bookingFeePct > 0) {
            $('#bookingFeeGroup').toggle(driverSelected && !noFee);
            $('#bookingFeeDisplay').text('R' + fee.toFixed(2));
        }
    }

    // Initialize cost on page load
    if ($('#cost').val() === 'other') {
        $('#otherCost').val(<?php echo floatval($booking['cost']); ?>);
        $('#otherCostGroup').removeClass('hidden');
        $('#otherCost').prop('required', true);
    } else {
        $('#otherCostGroup').addClass('hidden');
        $('#otherCost').prop('required', false);
    }

    // Initialize driver-related sections on page load
    updateDriverSections();
    updateBookingFee();
    $('#editBookingForm').on('submit', function (e) {
        e.preventDefault();
        var submitBtn = $('#submitBtn');
        var loading = $('#loading');
        var result = $('#result');
        submitBtn.hide();
        loading.show();
        result.html('');

        // ✅ Get payment method
        var paymentMethod = $('#payment_method').is(':checked') ? 'eft' : 'cash';
        var paymentReceived = $('#payment_received').is(':checked') ? '1' : '0';
        var formData = $(this).serialize() + '&payment_method=' + paymentMethod + '&payment_received=' + paymentReceived;

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Bookings/api/index.php?action=update',
            data: formData,
            dataType: 'json',
            success: function (response) {
                loading.hide();
                if (response.success) {
                    result.html('<div class="success-message">' + response.message + '</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/Bookings/view.php?id=' + <?= (int) $booking['id'] ?>;
                    }, 1000);
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                    submitBtn.show();
                }
            },
            error: function () {
                loading.hide();
                submitBtn.show();
                result.html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }
});

$('#contact').on('change', function () {
    var selected = $(this).find('option:selected');
    var newAddress = selected.data('address') || '';

    // Reset pickup to client's address
    $('#pickup_location').val(newAddress);

    // If pickup was 'other', clear it back to default
    if ($('#pickup_location').val() === 'other') {
        $('#pickup_location').val('');
    }

    // Update phone
    $('#phone').val(selected.data('phone') || '');
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
