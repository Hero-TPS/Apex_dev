<?php
//booking edit
// Shared includes
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$page_title = 'Edit Booking';
$page_subtitle = 'Edit Booking';
$show_breadcrumb = true;
$breadcrumb = ' > <a href="' . BASE_URL . '/modules/Bookings/">Bookings</a> > Edit Booking';
include ROOT_DIR . '/includes/header.php';

$booking = null;
$error_message = '';

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    if ($bookingId > 0) {
        try {
            // Fetch booking
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

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
            <div class="form-group">
                <label>
                    <input type="checkbox" id="swapLocations" name="swap_locations" <?php if ($booking['was_swapped']) echo 'checked'; ?>>
                    🔄 Swap pickup and destination locations
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

            <div class="form-group">
                <label for="flightNumber">Flight Number (if applicable)</label>
                <input type="text" id="flightNumber" name="flight_number" value="<?php echo htmlspecialchars($booking['flight_number']); ?>">
            </div>
            <div class="form-group">
                <label for="description">Additional Notes</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($booking['description']); ?></textarea>
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
            $('#otherPickupGroup').hide();
            $('#otherPickup').prop('required', false);
        } else {
            pickupSelect.val('other');
            $('#otherPickup').val(initialPickupValue);
            $('#otherPickupGroup').show();
            $('#otherPickup').prop('required', true);
        }
    }

    $('#contact').on('change', updatePickupOptions);
    updatePickupOptions();

    // ✅ ADD: Handle pickup "other" option toggle
    $('#pickup').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherPickupGroup').show();
            $('#otherPickup').prop('required', true);
            if ($('#otherPickup').val() === '') {
                $('#otherPickup').val(initialPickupValue);
            }
        } else {
            $('#otherPickupGroup').hide();
            $('#otherPickup').prop('required', false);
        }
    });

    // ✅ Handle destination "other" option toggle
    $('#destination').on('change', function () {
        if ($(this).val() === 'other') {
            $('#otherDestinationGroup').show();
            $('#otherDestination').prop('required', true);
            if ($('#otherDestination').val() === '') {
                $('#otherDestination').val(initialDestinationValue);
            }
        } else {
            $('#otherDestinationGroup').hide();
            $('#otherDestination').prop('required', false);
        }
    });

    // Initialize destination on page load
    if ($('#destination').val() === 'other') {
        $('#otherDestination').val(initialDestinationValue);
        $('#otherDestinationGroup').show();
        $('#otherDestination').prop('required', true);
    }

    // ✅ Handle cost "other" option toggle
    $('#cost').on('change', function () {
        var selected = $(this).val();
        if (selected === 'other') {
            $('#otherCostGroup').show();
            $('#otherCost').prop('required', true);
            if ($('#otherCost').val() === '' || $('#otherCost').val() === '0') {
                $('#otherCost').val(<?php echo floatval($booking['cost']); ?>);
            }
        } else {
            $('#otherCostGroup').hide();
            $('#otherCost').prop('required', false);
        }
    });

    // Initialize cost on page load
    if ($('#cost').val() === 'other') {
        $('#otherCost').val(<?php echo floatval($booking['cost']); ?>);
        $('#otherCostGroup').show();
        $('#otherCost').prop('required', true);
    } else {
        $('#otherCostGroup').hide();
        $('#otherCost').prop('required', false);
    }

    // Form submission
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
        var formData = $(this).serialize() + '&payment_method=' + paymentMethod;

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
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>