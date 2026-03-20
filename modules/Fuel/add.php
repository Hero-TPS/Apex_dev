<?php
$page_title = 'Log Fuel';
$page_subtitle = 'Track fuel expenses';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Fuel', 'url' => BASE_URL . '/modules/Fuel/'],
    ['label' => 'Add'],
]);
include ROOT_DIR . '/includes/header.php';

// Get last fuel price for default
$last_price = 25.00;
$stmt = $pdo->query("SELECT fuel_price FROM fuel_logs ORDER BY id DESC LIMIT 1");
if ($row = $stmt->fetch()) {
    $last_price = $row['fuel_price'];
}

// Get last odometer for trip calculation
$last_odo = 0;
$stmt = $pdo->query("SELECT odo_km FROM fuel_logs ORDER BY id DESC LIMIT 1");
if ($row = $stmt->fetch()) {
    $last_odo = $row['odo_km'];
}
?>

<div class="form-container">
    <h2>⛽ Log Fuel Fill-Up</h2>
    <form id="fuelLogForm">
        <div class="form-group">
            <label>Date & Time</label>
            <input type="datetime-local" id="log_datetime" name="log_datetime" 
                   value="<?= (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d\TH:i') ?>" 
                   required>
        </div>

        <div class="form-group">
            <label>
                <input type="radio" name="meter_type" value="trip" checked> Trip Meter (km since last fill)
            </label>
            <label style="margin-left: 20px;">
                <input type="radio" name="meter_type" value="odo"> Odometer (total km)
            </label>
        </div>

        <div class="form-group">
            <label for="km_value">Kilometers</label>
            <input type="number" id="km_value" name="km_value" step="0.01" min="0" required>
            <small id="km_helper" style="color: #666; display: block; margin-top: 5px;"></small>
        </div>

        <div class="form-group">
            <label for="fuel_price">Fuel Price (R/liter)</label>
            <input type="number" id="fuel_price" name="fuel_price" step="0.01" min="0" 
                   value="<?= $last_price ?>" required>
        </div>

        <div class="form-group">
            <label for="total_cost">Total Cost (R)</label>
            <input type="number" id="total_cost" name="total_cost" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="payment_method" name="payment_method" value="eft">
                EFT Payment
            </label>
        </div>

        <button type="submit" class="btn" id="submitBtn">💾 Save Log</button>
    </form>
    <div id="result"></div>
</div>

<script>
$(document).ready(function() {
    const lastOdo = <?= $last_odo ?>;

    // Update helper text based on meter type
    function updateHelper() {
        const meterType = $('input[name="meter_type"]:checked').val();
        const kmValue = parseFloat($('#km_value').val()) || 0;
        
        if (meterType === 'trip') {
            const newOdo = lastOdo + kmValue;
            $('#km_helper').text(`Last odometer: ${lastOdo.toFixed(1)} km. New will be: ${newOdo.toFixed(1)} km`);
        } else {
            if (kmValue > lastOdo) {
                const trip = kmValue - lastOdo;
                $('#km_helper').text(`Last odometer: ${lastOdo.toFixed(1)} km. Trip: ${trip.toFixed(1)} km`);
            } else {
                $('#km_helper').text(`Last odometer: ${lastOdo.toFixed(1)} km`);
            }
        }
    }

    $('input[name="meter_type"]').on('change', updateHelper);
    $('#km_value').on('input', updateHelper);
    updateHelper();

    $('#fuelLogForm').on('submit', function(e) {
        e.preventDefault();
        
        const datetimeLocal = $('#log_datetime').val();
        // Convert to Unix timestamp
        const localDate = new Date(datetimeLocal);
        const timestamp = Math.floor(localDate.getTime() / 1000);

        const meterType = $('input[name="meter_type"]:checked').val();
        const kmValue = parseFloat($('#km_value').val());
        const fuelPrice = parseFloat($('#fuel_price').val());
        const totalCost = parseFloat($('#total_cost').val());
        const paymentMethod = $('#payment_method').is(':checked') ? 'eft' : 'cash';

        let calculatedTrip = 0;
        if (meterType === 'odo' && kmValue > lastOdo) {
            calculatedTrip = kmValue - lastOdo;
        }

        const submitBtn = $('#submitBtn');
        const result = $('#result');
        submitBtn.prop('disabled', true).text('Saving...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Fuel/api/index.php',
            data: {
                action: 'add',
                log_timestamp: timestamp,
                meter_type: meterType,
                km_value: kmValue,
                calculated_trip: calculatedTrip,
                fuel_price: fuelPrice,
                total_cost: totalCost,
                payment_method: paymentMethod
            },
            dataType: 'json',
            success: function(response) {
                submitBtn.prop('disabled', false).text('💾 Save Log');
                if (response.success) {
                    result.html('<div class="success-message">' + response.message + '. Redirecting...</div>');
                    setTimeout(function() {
                        window.location.href = '<?= BASE_URL ?>/modules/Fuel/';
                    }, 2000);
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text('💾 Save Log');
                result.html('<div class="error-message">❌ An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>