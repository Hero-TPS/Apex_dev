<?php
// modules/Bookings/add.php
$page_title = 'Add Booking';
$page_subtitle = 'Add Booking';
$show_breadcrumb = true;
$breadcrumb = ' > Add Booking';

// Bootstrap config (two levels up from modules/Bookings/)
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/bookings.php';
include ROOT_DIR . '/includes/header.php';

// Fetch data
$contacts = fetchData($pdo, 'contacts', 'name ASC');
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs = fetchData($pdo, 'costs', 'amount ASC');
$durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
$timeOptions = generateTimeOptions();

// Prefill contact if passed via URL
$prefill_contact_id = null;
$prefill_contact_name = '';
if (isset($_GET['contact_id']) && isset($_GET['contact_name'])) {
    $prefill_contact_id = (int) $_GET['contact_id'];
    $prefill_contact_name = urldecode($_GET['contact_name']);
}
?>

<div class="form-container">
    <form id="bookingForm">
        <div class="form-group">
            <label for="contactSearch">Select Contact <span class="required">*</span></label>
            <input 
                type="text" 
                id="contactSearch" 
                placeholder="Search client..." 
                value="<?= $prefill_contact_name ? htmlspecialchars($prefill_contact_name) : '' ?>"
                required>
            <div id="contactSuggestions" class="suggestions-box"></div>
            <input type="hidden" id="contact_id" name="contact_id" value="<?= $prefill_contact_id ?? '' ?>" required>
            <input type="hidden" id="contact_name" name="contact_name" value="<?= htmlspecialchars($prefill_contact_name) ?>">
        </div>

        <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date">Trip Date <span class="required">*</span></label>
                <input type="date" id="date" name="trip_date" required>
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
            <input type="text" id="otherDestination" name="other_original_destination" placeholder="Enter destination address">
        </div>

        <div class="form-group hidden" id="addToDestinationGroup">
            <label>
                <input type="checkbox" id="addToDestinations" name="add_to_destinations">
                🔄 Add new destination to list
            </label>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="swapLocations" name="swap_locations">
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
            <textarea id="description" name="description" placeholder="Any special instructions..."></textarea>
        </div>

        <button type="submit" class="btn" id="submitBtn">🚗 Create Booking</button>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
$(document).ready(function () {
    document.getElementById('date').min = new Date().toISOString().split('T')[0];

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
    });

    // Trigger initial state
    $('#pickup').trigger('change');
    $('#destination').trigger('change');
    $('#cost').trigger('change');

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
                        window.location.href = '<?= BASE_URL ?>/modules/Bookings/detail.php?id=' + response.booking_id;
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