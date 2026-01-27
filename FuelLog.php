<?php
$page_title = 'Fuel Log';
$page_subtitle = 'Track fuel expenses';
$show_breadcrumb = true;
$breadcrumb = ' > Fuel Log';

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
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

        <button type="submit" class="page-action-btn save">💾 Save Log</button>
    </form>
    <div id="result"></div>
</div>

<script>
$(document).ready(function() {
    const lastOdo = <?= $last_odo ?>;

    $('#fuelLogForm').on('submit', function(e) {
        e.preventDefault();
        
        const datetimeLocal = $('#log_datetime').val();
        // Convert to Unix timestamp (UTC)
        const localDate = new Date(datetimeLocal);
        const timestamp = Math.floor(localDate.getTime() / 1000);

        const meterType = $('input[name="meter_type"]:checked').val();
        const kmValue = parseFloat($('#km_value').val());
        const paymentMethod = $('#payment_method').is(':checked') ? 'eft' : 'cash';

        // Calculate trip value
        let tripValue = 0;
        if (meterType === 'trip') {
            tripValue = kmValue;
        } else {
            tripValue = (kmValue > lastOdo) ? (kmValue - lastOdo) : 0;
        }

        $.ajax({
            url: 'api/fuel_logs.php',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {
                action: 'add',
                log_timestamp: timestamp,
                meter_type: meterType,
                km_value: kmValue,
                calculated_trip: tripValue,
                fuel_price: $('#fuel_price').val(),
                total_cost: $('#total_cost').val(),
                payment_method: paymentMethod
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#result').html('<div class="success-message">✅ Log saved! Redirecting...</div>');
                    setTimeout(() => window.location.href = 'FuelReports.php', 1000);
                } else {
                    $('#result').html('<div class="error-message">' + res.message + '</div>');
                }
            },
            error: function() {
                $('#result').html('<div class="error-message">❌ Failed to save log.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>