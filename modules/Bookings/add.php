<?php
// modules/Bookings/add.php
$page_title = 'Add Booking';
$page_subtitle = 'Add Booking';
$show_breadcrumb = true;

// Bootstrap config (two levels up from modules/Bookings/)
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Bookings', 'url' => BASE_URL . '/modules/Bookings/'],
    ['label' => 'Add Booking'],
]);
include ROOT_DIR . '/includes/header.php';

// Fetch data
$contacts = fetchData($pdo, 'contacts', 'name ASC');
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs = fetchData($pdo, 'costs', 'amount ASC');
$durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
$timeOptions = generateTimeOptions();

// Fetch active drivers for allocation dropdown
$driversStmt = $pdo->query("SELECT id, name, phone FROM drivers WHERE active = 1 ORDER BY name ASC");
$drivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

// Booking fee percentage for JS calculation
$booking_fee_pct = (float) getSystemVariable($pdo, 'apex_booking_fee_pct');

// Prefill contact if passed via URL
$prefill_contact_id = null;
$prefill_contact_name = '';
if (isset($_GET['contact_id']) && isset($_GET['contact_name'])) {
    $prefill_contact_id = (int) $_GET['contact_id'];
    $prefill_contact_name = urldecode($_GET['contact_name']);
}

// Prefill other fields from prebooking conversion
$prefill_trip_date    = isset($_GET['trip_date'])      ? htmlspecialchars(urldecode($_GET['trip_date']))      : '';
$prefill_start_time   = isset($_GET['start_time'])     ? htmlspecialchars(urldecode($_GET['start_time']))     : '';
$prefill_pickup       = isset($_GET['pickup'])         ? htmlspecialchars(urldecode($_GET['pickup']))         : '';
$prefill_destination  = isset($_GET['destination'])    ? htmlspecialchars(urldecode($_GET['destination']))    : '';
$prefill_swap         = isset($_GET['swap_locations']) && $_GET['swap_locations'] === '1';
$prefill_cost         = isset($_GET['cost'])           ? htmlspecialchars(urldecode($_GET['cost']))           : '';
$prefill_description  = isset($_GET['description'])    ? htmlspecialchars(urldecode($_GET['description']))    : '';
$from_prebooking      = isset($_GET['from_prebooking']) ? (int) $_GET['from_prebooking'] : 0;
?>

<div class="form-container">
    <form id="bookingForm">
        <?php if ($from_prebooking > 0): ?>
            <input type="hidden" name="from_prebooking" value="<?= $from_prebooking ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="contactSearch">Select Contact <span class="required">*</span></label>
            <input type="text" id="contactSearch" placeholder="Search client..."
                value="<?= $prefill_contact_name ? htmlspecialchars($prefill_contact_name) : '' ?>" required>
            <div id="contactSuggestions" class="suggestions-box"></div>
            <input type="hidden" id="contact_id" name="contact_id" value="<?= $prefill_contact_id ?? '' ?>" required>
            <input type="hidden" id="contact_name" name="contact_name"
                value="<?= htmlspecialchars($prefill_contact_name) ?>">
        </div>

        <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date">Trip Date <span class="required">*</span></label>
                <input type="date" id="date" name="trip_date" required
                    value="<?= $prefill_trip_date ?>">
            </div>
            <div class="form-group">
                <label for="startTime">Start Time <span class="required">*</span></label>
                <select id="startTime" name="start_time" required>
                    <?php foreach ($timeOptions as $time): ?>
                        <option value="<?= htmlspecialchars($time) ?>" <?= ($time == '12:00') ? 'selected' : '' ?>>
                            <?= htmlspecialchars($time) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="duration">Duration (hours) <span class="required">*</span></label>
            <select id="duration" name="duration" required>
                <?php foreach ($durations as $dur): ?>
                    <option value="<?= htmlspecialchars($dur) ?>" <?= ($dur == '1.0' || $dur == '1') ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dur) ?> hour<?= ($dur != '1' && $dur != '1.0') ? 's' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Pickup -->
        <div class="form-group">
            <label for="pickup">Pickup Location <span class="required">*</span></label>
            <select id="pickup" name="original_pickup" required>
                <option value="">Choose pickup location...</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherPickupGroup">
            <label for="otherPickup">Specify Other Pickup Location <span class="required">*</span></label>
            <input type="text" id="otherPickup" name="other_original_pickup" placeholder="Enter pickup address">
        </div>

        <!-- Destination -->
        <div class="form-group">
            <label for="destination">Destination <span class="required">*</span></label>
            <select id="destination" name="original_destination" required>
                <option value="">Choose destination...</option>
                <?php foreach ($destinations as $destination): ?>
                    <option value="<?= htmlspecialchars($destination['name']) ?>">
                        <?= htmlspecialchars($destination['name']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination <span class="required">*</span></label>
            <input type="text" id="otherDestination" name="other_original_destination"
                placeholder="Enter destination address">
        </div>

        <div class="form-group hidden" id="addToDestinationGroup">
            <label>
                <input type="checkbox" id="addToDestinations" name="add_to_destinations">
                🔄 Add new destination to list
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="swapLocations" name="swap_locations" <?php if ($prefill_swap) echo 'checked'; ?>>
                🔄 Swap pickup and destination locations
            </label>
        </div>

        <!-- Cost -->
        <div class="form-group">
            <label for="cost">Cost <span class="required">*</span></label>
            <select id="cost" name="cost" required>
                <option value="">Select cost...</option>
                <?php foreach ($costs as $cost): ?>
                    <option value="<?= htmlspecialchars($cost['amount']) ?>">
                        R<?= htmlspecialchars(number_format($cost['amount'], 2)) ?>
                    </option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherCostGroup">
            <label for="otherCost">Specify Other Cost <span class="required">*</span></label>
            <input type="number" id="otherCost" name="other_cost" step="0.01" min="0">
        </div>

        <!-- Payment Method -->
        <div class="form-group">
            <label>
                <input type="checkbox" id="payment_method" name="payment_method" value="eft">
                EFT Payment
            </label>
        </div>

        <div class="form-group">
            <label for="flightNumber">Flight Number (if applicable)</label>
            <input type="text" id="flightNumber" name="flight_number">
        </div>

        <div class="form-group">
            <label for="description">Additional Notes</label>
            <textarea id="description" name="description" placeholder="Any special instructions..."><?= $prefill_description ?></textarea>
        </div>

        <!-- Driver Allocation -->
        <div class="form-group">
            <label for="driver_id">Allocate Driver</label>
            <select id="driver_id" name="driver_id">
                <option value="">— No driver —</option>
                <?php foreach ($drivers as $driver): ?>
                    <option value="<?= htmlspecialchars($driver['id']) ?>">
                        <?= htmlspecialchars($driver['name']) ?>
                        <?= $driver['phone'] ? ' (' . htmlspecialchars($driver['phone']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Optional — assign a driver to this booking</small>
        </div>

        <?php if ($booking_fee_pct > 0): ?>
        <div class="form-group hidden" id="bookingFeeGroup">
            <label>Apex Booking Fee (<?= htmlspecialchars($booking_fee_pct) ?>%)</label>
            <div id="bookingFeeDisplay">R0.00</div>
            <small>Calculated from cost × <?= htmlspecialchars($booking_fee_pct) ?>% — stored when booking is saved</small>
        </div>
        <?php endif; ?>

        <!-- No Booking Fee -->
        <div class="form-group hidden" id="noBookingFeeGroup">
            <label>
                <input type="checkbox" id="no_booking_fee" name="no_booking_fee" value="1">
                No Booking Fee (full amount goes to driver)
            </label>
        </div>

        <!-- Driver Notes -->
        <div class="form-group hidden" id="driverNotesGroup">
            <label for="driver_notes">Driver Notes</label>
            <textarea id="driver_notes" name="driver_notes" placeholder="Instructions for the driver..."></textarea>
        </div>

        <button type="submit" class="btn" id="submitBtn">🚗 Create Booking</button>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
    $(document).ready(function () {
        document.getElementById('date').min = new Date().toISOString().split('T')[0];

        // Prefill from prebooking conversion
        var prefillTime  = <?= json_encode($prefill_start_time) ?>;
        var prefillPickup = <?= json_encode($prefill_pickup) ?>;
        var prefillDest  = <?= json_encode($prefill_destination) ?>;
        var prefillCost  = <?= json_encode($prefill_cost) ?>;

        if (prefillTime) {
            $('#startTime').val(prefillTime.substr(0, 5));
        }
        const contactSearch = $('#contactSearch');
        const suggestionsBox = $('#contactSuggestions');
        const contactIdInput = $('#contact_id');
        const contactNameInput = $('#contact_name');

        let clients = <?= json_encode($contacts ?: []) ?>;
        let selected = -1;

        const prefillId = <?= $prefill_contact_id ?: 'null' ?>;
        if (prefillId !== null) {
            const client = clients.find(c => c.id === prefillId);
            if (client) {
                contactSearch.val(client.name);
                contactIdInput.val(client.id);
                contactNameInput.val(client.name);
                $('#phone').val(client.phone || '');

                const pickupSelect = $('#pickup');
                pickupSelect.empty();
                if (client.address) {
                    pickupSelect.append(`<option value="${escapeHtml(client.address)}" selected>${escapeHtml(client.address)}</option>`);
                } else {
                    pickupSelect.append('<option value="" selected>Choose pickup location...</option>');
                }
                pickupSelect.append('<option value="other">Other</option>');
            }
        }

        contactSearch.on('input focus', function () {
            const query = $(this).val().trim().toLowerCase();
            if (query.length === 0) {
                suggestionsBox.hide();
                return;
            }
            const filtered = clients.filter(c =>
                (c.name && c.name.toLowerCase().includes(query)) ||
                (c.phone && c.phone.toLowerCase().includes(query))
            );
            suggestionsBox.empty();
            if (filtered.length > 0) {
                selected = -1;
                filtered.forEach(client => {
                    const item = $(`<div class="suggestion-item">${escapeHtml(client.name)}<br><small>${escapeHtml(client.phone || '')}</small><br><small>${escapeHtml(client.address || '')}</small></div>`);
                    item.data('client', client);
                    item.on('click', function () {
                        selectClient($(this).data('client'));
                    });
                    suggestionsBox.append(item);
                });
                suggestionsBox.show();
            } else {
                suggestionsBox.html('<div class="suggestion-item">No clients found</div>').show();
            }
        });

        contactSearch.on('keydown', function (e) {
            const items = suggestionsBox.find('.suggestion-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selected = Math.min(selected + 1, items.length - 1);
                highlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selected = Math.max(selected - 1, -1);
                highlight(items);
            } else if (e.key === 'Enter' && selected >= 0) {
                e.preventDefault();
                selectClient(items.eq(selected).data('client'));
            }
        });

        function highlight(items) {
            items.removeClass('active');
            if (selected >= 0) {
                items.eq(selected).addClass('active');
            }
        }

        function selectClient(client) {
            contactSearch.val(client.name);
            contactIdInput.val(client.id);
            contactNameInput.val(client.name);
            $('#phone').val(client.phone || '');

            const pickupSelect = $('#pickup');
            pickupSelect.empty();
            if (client.address) {
                pickupSelect.append(`<option value="${escapeHtml(client.address)}" selected>${escapeHtml(client.address)}</option>`);
            } else {
                pickupSelect.append('<option value="" selected>Choose pickup location...</option>');
            }
            pickupSelect.append('<option value="other">Other</option>');
            pickupSelect.trigger('change');

            suggestionsBox.hide();
        }

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#contactSearch, #contactSuggestions').length) {
                suggestionsBox.hide();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                suggestionsBox.hide();
            }
        });

        // Toggle "Other" fields
        $('#pickup').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherPickupGroup').removeClass('hidden');
                $('#otherPickup').prop('required', true);
            } else {
                $('#otherPickupGroup').addClass('hidden');
                $('#otherPickup').prop('required', false);
            }
        });

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

        $('#cost').on('change', function () {
            if ($(this).val() === 'other') {
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
        }

        var bookingFeePct = <?= (float) $booking_fee_pct ?>;
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

        // Trigger initial state
        $('#pickup').trigger('change');
        $('#destination').trigger('change');
        $('#cost').trigger('change');

        // Apply prebooking prefill for destination and cost after destinations list is populated
        if (prefillPickup) {
            var pickupSel = $('#pickup');
            if (pickupSel.find('option[value="' + prefillPickup + '"]').length) {
                pickupSel.val(prefillPickup).trigger('change');
            } else {
                pickupSel.val('other').trigger('change');
                $('#otherPickup').val(prefillPickup);
            }
        }
        if (prefillDest) {
            var destSelect = $('#destination');
            if (destSelect.find('option[value="' + prefillDest + '"]').length) {
                destSelect.val(prefillDest).trigger('change');
            } else {
                destSelect.val('other').trigger('change');
                $('#otherDestination').val(prefillDest);
            }
        }
        if (prefillCost) {
            var costSelect = $('#cost');
            if (costSelect.find('option[value="' + prefillCost + '"]').length) {
                costSelect.val(prefillCost).trigger('change');
            } else {
                costSelect.val('other').trigger('change');
                $('#otherCost').val(prefillCost);
            }
        }

        // Form submit
        $('#bookingForm').on('submit', function (e) {
            e.preventDefault();
            var submitBtn = $('#submitBtn');
            var loading = $('#loading');
            var result = $('#result');
            submitBtn.hide();
            loading.show();
            result.html('');

            var paymentMethod = $('#payment_method').is(':checked') ? 'eft' : 'cash';
            var formData = $(this).serialize() + '&payment_method=' + paymentMethod;

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php?action=add',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    loading.hide();
                    submitBtn.show();
                    if (response.success && response.booking_id) {
                        $('#result').html('<div class="success-message">' + response.message + '</div>');
                        setTimeout(function () {
                            window.location.href = '<?= BASE_URL ?>/modules/Bookings/view.php?id=' + response.booking_id;
                        }, 500);
                    } else {
                        $('#result').html('<div class="error-message">' + (response.message || 'Failed to create booking.') + '</div>');
                    }
                },
                error: function (xhr) {
                    loading.hide();
                    submitBtn.show();
                    $('#result').html('<div class="error-message">❌ Error: ' + (xhr.responseText || 'Unknown error') + '</div>');
                }
            });
        });

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>