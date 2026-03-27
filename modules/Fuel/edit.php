<?php
$page_title = 'Edit Fuel Log';
$page_subtitle = 'Update fuel log entry';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Fuel', 'url' => BASE_URL . '/modules/Fuel/'],
    ['label' => 'Edit'],
]);
$page_path = '/modules/Fuel/edit.php';
include ROOT_DIR . '/includes/header.php';

$log_id = intval($_GET['id'] ?? 0);

if ($log_id <= 0) {
    echo '<div class="error-message">Invalid fuel log ID</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}
?>

<div id="loading" class="loading" style="display: block;">Loading fuel log...</div>

<div class="form-container" id="editForm" style="display: none;">
    <h2>✏️ Edit Fuel Log</h2>
    <form id="fuelEditForm">
        <input type="hidden" id="log_id" name="id" value="<?= $log_id ?>">
        
        <div class="form-group">
            <label>Date & Time</label>
            <input type="datetime-local" id="log_datetime" name="log_datetime" required>
        </div>

        <div class="form-group">
            <label for="fuel_price">Fuel Price (R/liter)</label>
            <input type="number" id="fuel_price" name="fuel_price" step="0.01" min="0" required>
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

        <div class="form-group">
            <p style="color: #666; font-size: 14px;">
                <strong>Note:</strong> Odometer and trip values cannot be edited to maintain data integrity.
            </p>
        </div>

        <div class="action-buttons">
            <button type="submit" class="btn" id="submitBtn">💾 Update Log</button>
            <a href="<?= BASE_URL ?>/modules/Fuel/" class="page-action-btn back">
                ← Back to Fuel Logs
            </a>
        </div>
    </form>
    <div id="result"></div>
</div>

<script>
$(document).ready(function() {
    const logId = <?= $log_id ?>;
    const loading = $('#loading');
    const form = $('#editForm');
    const result = $('#result');

    // Load fuel log data
    $.ajax({
        url: '<?= BASE_URL ?>/modules/Fuel/api/index.php?action=get_single&id=' + logId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            loading.hide();
            if (response.success && response.log) {
                const log = response.log;
                
                // Convert timestamp to datetime-local format
                const dt = new Date(log.log_timestamp * 1000);
                const offset = dt.getTimezoneOffset() * 60000;
                const localDate = new Date(dt.getTime() - offset);
                const dateTimeLocal = localDate.toISOString().slice(0, 16);
                
                $('#log_datetime').val(dateTimeLocal);
                $('#fuel_price').val(log.fuel_price);
                $('#total_cost').val(log.total_cost);
                $('#payment_method').prop('checked', log.payment_method === 'eft');
                
                form.show();
            } else {
                result.html('<div class="error-message">Fuel log not found</div>');
            }
        },
        error: function() {
            loading.hide();
            result.html('<div class="error-message">Failed to load fuel log</div>');
        }
    });

    // Handle form submission
    $('#fuelEditForm').on('submit', function(e) {
        e.preventDefault();
        
        const datetimeLocal = $('#log_datetime').val();
        const localDate = new Date(datetimeLocal);
        const timestamp = Math.floor(localDate.getTime() / 1000);
        const fuelPrice = parseFloat($('#fuel_price').val());
        const totalCost = parseFloat($('#total_cost').val());
        const paymentMethod = $('#payment_method').is(':checked') ? 'eft' : 'cash';

        const submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).text('Updating...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Fuel/api/index.php',
            data: {
                action: 'update',
                id: logId,
                log_timestamp: timestamp,
                fuel_price: fuelPrice,
                total_cost: totalCost,
                payment_method: paymentMethod
            },
            dataType: 'json',
            success: function(response) {
                submitBtn.prop('disabled', false).text('💾 Update Log');
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
                submitBtn.prop('disabled', false).text('💾 Update Log');
                result.html('<div class="error-message">❌ An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>