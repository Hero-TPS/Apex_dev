<?php
// modules/Prebookings/add.php
$page_title    = 'Add Prebooking';
$page_subtitle = 'Add Prebooking';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Bookings/helpers.php'; // booking fee dropdown options
$breadcrumb = buildBreadcrumb([
    ['label' => 'Prebookings', 'url' => BASE_URL . '/modules/Prebookings/'],
    ['label' => 'Add Prebooking'],
]);
include ROOT_DIR . '/includes/header.php';

$contacts     = fetchData($pdo, 'contacts', 'name ASC');
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();

// Driver allocation (same data and panel as the booking form)
$drivers       = $pdo->query("SELECT id, name, phone FROM drivers WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$feePctOptions = getBookingFeePctOptions();
$savedFeePct   = getDefaultBookingFeePct($pdo);
$savedDriverId = 0;
$savedDriverName = '';
$savedDriverNotes = '';
$savedPassengerName = '';
$savedPassengerPhone = '';
$passengerOpen = false;
$hasDriver = false;
?>

<div class="form-container">
    <p class="form-intro">Use this form to record a tentative booking when the client knows their date but not all details yet. Time, destination, and cost are optional.</p>

    <form id="prebookingForm">
        <div class="form-group">
            <label for="contactSearch">Client Name <span class="required">*</span></label>
            <input type="text" id="contactSearch" placeholder="Search client..." required>
            <div id="contactSuggestions" class="suggestions-box"></div>
            <input type="hidden" id="contact_id" name="contact_id" required>
        </div>

        <div class="form-group">
            <label for="trip_date">Date <span class="required">*</span></label>
            <input type="date" id="trip_date" name="trip_date" required>
        </div>

        <div class="form-group">
            <label for="start_time">Time <small>(optional)</small></label>
            <select id="start_time" name="start_time">
                <option value="">— Not known yet —</option>
                <?php foreach ($timeOptions as $time): ?>
                    <option value="<?= htmlspecialchars($time) ?>"><?= htmlspecialchars($time) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="original_pickup">Pickup Location <small>(optional)</small></label>
            <select id="original_pickup" name="original_pickup">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= htmlspecialchars($dest['name']) ?>"><?= htmlspecialchars($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherPickupGroup">
            <label for="otherPickup">Specify Other Pickup</label>
            <input type="text" id="otherPickup" name="other_original_pickup" placeholder="Enter pickup address">
        </div>

        <div class="form-group">
            <label for="original_destination">Destination <small>(optional)</small></label>
            <select id="original_destination" name="original_destination">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= htmlspecialchars($dest['name']) ?>"><?= htmlspecialchars($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination</label>
            <input type="text" id="otherDestination" name="other_original_destination" placeholder="Enter destination">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="swapLocations" name="swap_locations">
                🔄 Swap pickup and destination locations
            </label>
        </div>

        <div class="form-group">
            <label for="cost">Cost <small>(optional)</small></label>
            <select id="cost" name="cost">
                <option value="">— Not known yet —</option>
                <?php foreach ($costs as $c): ?>
                    <option value="<?= htmlspecialchars($c['amount']) ?>">R<?= htmlspecialchars(number_format($c['amount'], 2)) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherCostGroup">
            <label for="otherCost">Specify Other Cost</label>
            <input type="number" id="otherCost" name="other_cost" step="0.01" min="0" placeholder="0.00">
        </div>

        <div class="form-group">
            <label for="description">Notes / Description <small>(optional)</small></label>
            <textarea id="description" name="description" placeholder="Any relevant info about this booking..."></textarea>
        </div>

        <!-- Passenger details (same collapsible panel as on the booking form; collapsed unless filled in) -->
        <div class="menu-section">
            <h3 class="menu-toggle" data-target="passenger-section">👤 Passenger Details</h3>
            <div id="passenger-section" class="section-body<?= $passengerOpen ? '' : ' hidden' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label for="passengerName" class="manage-driver-label">Passenger Name</label>
                        <input type="text" id="passengerName" name="passenger_name"
                            value="<?= e($savedPassengerName) ?>">
                    </div>
                    <div class="form-group">
                        <label for="passengerPhone" class="manage-driver-label">Passenger Phone</label>
                        <input type="tel" id="passengerPhone" name="passenger_phone"
                            value="<?= e($savedPassengerPhone) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Driver allocation (same panel as on the booking form; open when a driver is already allocated) -->
        <div class="menu-section">
            <h3 class="menu-toggle" data-target="manage-driver-section">🚗 Allocate Driver<span class="driver-allocated-badge<?= $hasDriver ? '' : ' hidden' ?>" id="driverBadge"><?= e($savedDriverName) ?></span></h3>
            <div id="manage-driver-section" class="section-body<?= $hasDriver ? '' : ' hidden' ?>">
                <div class="manage-driver-fields">
                    <div>
                        <label for="driver-select" class="manage-driver-label">Driver</label>
                        <select id="driver-select" name="driver_id">
                            <option value="">— No driver —</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?= e($driver['id']) ?>"<?= (int) $driver['id'] === $savedDriverId ? ' selected' : '' ?>>
                                    <?= e($driver['name']) ?>
                                    <?= $driver['phone'] ? ' (' . e($driver['phone']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="bookingFeePctGroup" class="hidden">
                        <label for="booking-fee-pct-select" class="manage-driver-label">Booking Fee</label>
                        <select id="booking-fee-pct-select" name="booking_fee_pct">
                            <?php foreach ($feePctOptions as $opt): ?>
                                <option value="<?= $opt ?>" <?= ($opt === $savedFeePct) ? 'selected' : '' ?>>
                                    <?= $opt === 0 ? '0% — No booking fee' : $opt . '%' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group hidden" id="bookingFeeNote">
                    <small id="bookingFeeDisplay"></small>
                </div>
                <div class="form-group hidden" id="driverNotesGroup">
                    <label for="driver-notes-input" class="manage-driver-label">Driver Notes</label>
                    <textarea id="driver-notes-input" name="driver_notes" rows="3"
                        placeholder="Instructions for the driver..."><?= e($savedDriverNotes) ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn">📋 Save Prebooking</button>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
$(document).ready(function () {
    document.getElementById('trip_date').min = new Date().toISOString().split('T')[0];

    const contactSearch   = $('#contactSearch');
    const suggestionsBox  = $('#contactSuggestions');
    const contactIdInput  = $('#contact_id');
    let clients = <?= json_encode($contacts ?: []) ?>;
    let selected = -1;

    contactSearch.on('input focus', function () {
        const query = $(this).val().trim().toLowerCase();
        if (query.length === 0) { suggestionsBox.hide(); return; }
        const filtered = clients.filter(c =>
            (c.name  && c.name.toLowerCase().includes(query)) ||
            (c.phone && c.phone.toLowerCase().includes(query))
        );
        suggestionsBox.empty();
        if (filtered.length > 0) {
            selected = -1;
            filtered.forEach(function (client) {
                const item = $('<div class="suggestion-item">' +
                    escapeHtml(client.name) +
                    '<br><small>' + escapeHtml(client.phone || '') + '</small>' +
                    '<br><small>' + escapeHtml(client.address || '') + '</small></div>');
                item.data('client', client);
                item.on('click', function () { selectClient($(this).data('client')); });
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
            items.removeClass('active');
            if (selected >= 0) { items.eq(selected).addClass('active'); }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selected = Math.max(selected - 1, -1);
            items.removeClass('active');
            if (selected >= 0) { items.eq(selected).addClass('active'); }
        } else if (e.key === 'Enter' && selected >= 0) {
            e.preventDefault();
            selectClient(items.eq(selected).data('client'));
        }
    });

    function selectClient(client) {
        contactSearch.val(client.name);
        contactIdInput.val(client.id);
        suggestionsBox.hide();

        const pickupSelect = $('#original_pickup');
        pickupSelect.empty();
        if (client.address) {
            pickupSelect.append(
                $('<option>', { value: client.address, text: client.address, selected: true })
            );
        } else {
            pickupSelect.append(
                $('<option>', { value: '', text: '— Not known yet —', selected: true })
            );
        }
        <?php foreach ($destinations as $dest): ?>
        pickupSelect.append(
            $('<option>', { value: <?= json_encode($dest['name']) ?>, text: <?= json_encode($dest['name']) ?> })
        );
        <?php endforeach; ?>
        pickupSelect.append($('<option>', { value: 'other', text: 'Other (specify below)' }));
        pickupSelect.trigger('change');
    }

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#contactSearch, #contactSuggestions').length) {
            suggestionsBox.hide();
        }
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { suggestionsBox.hide(); }
    });

    // Other pickup toggle
    $('#original_pickup').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherPickupGroup').removeClass('hidden');
        } else {
            $('#otherPickupGroup').addClass('hidden');
            $('#otherPickup').val('');
        }
    });

    // Other destination toggle
    $('#original_destination').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherDestinationGroup').removeClass('hidden');
        } else {
            $('#otherDestinationGroup').addClass('hidden');
            $('#otherDestination').val('');
        }
    });

    // Other cost toggle
    $('#cost').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherCostGroup').removeClass('hidden');
        } else {
            $('#otherCostGroup').addClass('hidden');
            $('#otherCost').val('');
        }
    });

    // Passenger Details / Allocate Driver panels: headers toggle them (fields stay in the form and submit even when collapsed)
    $('.menu-toggle[data-target="passenger-section"]').on('click', function () {
        $('#passenger-section').slideToggle(200);
    });
    $('.menu-toggle[data-target="manage-driver-section"]').on('click', function () {
        $('#manage-driver-section').slideToggle(200);
    });

    $('#driver-select').on('change', function () {
        updateBookingFee();
        updateDriverSections();
    });
    $('#booking-fee-pct-select').on('change', updateBookingFee);
    $('#cost').on('change', updateBookingFee);
    $('#otherCost').on('input', updateBookingFee);

    function updateDriverSections() {
        var driverSelected = $('#driver-select').val() !== '';
        $('#bookingFeePctGroup').toggleClass('hidden', !driverSelected);
        $('#driverNotesGroup').toggleClass('hidden', !driverSelected);
        $('#bookingFeeNote').toggleClass('hidden', !driverSelected);
        var badge = $('#driverBadge');
        if (driverSelected) {
            badge.text($('#driver-select option:selected').text().replace(/\s*\(.*\)\s*$/, '').trim()).removeClass('hidden');
        } else {
            badge.addClass('hidden').text('');
            $('#driver-notes-input').val('');
        }
    }

    // Fee preview: cost x the chosen percentage (0% = no booking fee, full amount to driver)
    function updateBookingFee() {
        var pct = parseInt($('#booking-fee-pct-select').val(), 10) || 0;
        var costVal = $('#cost').val() === 'other'
            ? parseFloat($('#otherCost').val()) || 0
            : parseFloat($('#cost').val()) || 0;
        if (pct === 0) {
            $('#bookingFeeDisplay').text('No booking fee — the full amount goes to the driver.');
        } else if (costVal <= 0) {
            $('#bookingFeeDisplay').text('Booking fee: ' + pct + '% of the cost, worked out when the booking is created.');
        } else {
            var fee = parseFloat((costVal * pct / 100).toFixed(2));
            $('#bookingFeeDisplay').text('Booking fee: R' + fee.toFixed(2) + ' (cost × ' + pct + '%), stored when the booking is created.');
        }
    }

    updateDriverSections();
    updateBookingFee();

    // Form submit
    $('#prebookingForm').on('submit', function (e) {
        e.preventDefault();

        if (!contactIdInput.val()) {
            $('#result').html('<div class="error-message">⚠️ Please select a client from the list.</div>');
            return;
        }

        var submitBtn = $('#submitBtn');
        var loading   = $('#loading');
        submitBtn.hide();
        loading.show();
        $('#result').html('');

        var destSelect   = $('#original_destination');
        var destVal      = destSelect.val() === 'other' ? $('#otherDestination').val().trim() : destSelect.val();

        var pickupSelect = $('#original_pickup');
        var pickupVal    = pickupSelect.val() === 'other' ? $('#otherPickup').val().trim() : pickupSelect.val();

        var costSelect   = $('#cost');
        var costVal      = costSelect.val() === 'other' ? $('#otherCost').val().trim() : costSelect.val();

        var data = {
            contact_id:           contactIdInput.val(),
            trip_date:            $('#trip_date').val(),
            start_time:           $('#start_time').val(),
            original_pickup:      pickupVal,
            original_destination: destVal,
            swap_locations:       $('#swapLocations').is(':checked') ? '1' : '',
            cost:                 costVal,
            description:          $('#description').val().trim(),
            passenger_name:       $('#passengerName').val().trim(),
            passenger_phone:      $('#passengerPhone').val().trim(),
            driver_id:            $('#driver-select').val(),
            booking_fee_pct:      $('#booking-fee-pct-select').val(),
            driver_notes:         $('#driver-notes-input').val().trim(),
        };

        $.ajax({
            type:     'POST',
            url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=add',
            data:     data,
            dataType: 'json',
            success: function (res) {
                loading.hide();
                submitBtn.show();
                if (res.success) {
                    $('#result').html('<div class="success-message">' + res.message + '</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/Prebookings/';
                    }, 600);
                } else {
                    $('#result').html('<div class="error-message">' + (res.message || 'Failed to save.') + '</div>');
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
