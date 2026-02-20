<?php
$page_title = 'Edit Uber Income';
$page_subtitle = 'Update weekly Uber earnings';
$show_breadcrumb = true;
$breadcrumb = ' > Uber > Edit';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';

$record_id = intval($_GET['id'] ?? 0);

if ($record_id <= 0) {
    echo '<div class="error-message">Invalid record ID</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}
?>

<div id="loading" class="loading" style="display: block;">Loading Uber income record...</div>

<div class="form-container" id="editForm" style="display: none;">
    <h2>✏️ Edit Uber Income</h2>
    <form id="uberEditForm">
        <input type="hidden" id="record_id" name="id" value="<?= $record_id ?>">
        
        <div class="form-group">
            <label>Week Period</label>
            <input type="text" id="week_display" readonly style="background: #f5f5f5;">
        </div>

        <div class="form-group">
            <label for="total_income">Total Uber Income (R) <span class="required">*</span></label>
            <input type="number" id="total_income" name="total_income" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="cash_received">Cash Received (R) <span class="required">*</span></label>
            <input type="number" id="cash_received" name="cash_received" step="0.01" required>
            <small id="card_income_display" style="color: #666; display: block; margin-top: 5px;"></small>
        </div>

        <div class="form-group">
            <label for="mobile_data_cost">Mobile Data Cost (R)</label>
            <input type="number" id="mobile_data_cost" name="mobile_data_cost" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label for="total_trips">Total Trips <span class="required">*</span></label>
            <input type="number" id="total_trips" name="total_trips" min="0" required>
        </div>

        <div class="form-group">
            <label for="total_time_online">Total Time Online (hours) <span class="required">*</span></label>
            <input type="number" id="total_time_online" name="total_time_online" step="0.01" min="0" required>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn" id="submitBtn">💾 Update Income</button>
            <a href="<?= BASE_URL ?>/modules/Uber/" class="btn" style="background: #95a5a6; width: auto;">
                ← Back to Uber Reports
            </a>
        </div>
    </form>
    <div id="result"></div>
</div>

<script>
$(document).ready(function() {
    const recordId = <?= $record_id ?>;
    const loading = $('#loading');
    const form = $('#editForm');
    const result = $('#result');

    // Load Uber income record
    $.ajax({
        url: '<?= BASE_URL ?>/modules/Uber/api/index.php?action=get_single&id=' + recordId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            loading.hide();
            if (response.success && response.record) {
                const record = response.record;
                
                // Format week display
                const weekStart = new Date(record.week_start * 1000);
                const weekEnd = new Date(record.week_end * 1000);
                const options = { day: 'numeric', month: 'short', year: 'numeric', timeZone: '<?= TIME_ZONE ?>' };
                const weekDisplay = weekStart.toLocaleDateString('en-GB', options) + ' – ' + weekEnd.toLocaleDateString('en-GB', options);
                
                $('#week_display').val(weekDisplay);
                $('#total_income').val(record.total_income);
                $('#cash_received').val(record.cash_received);
                $('#mobile_data_cost').val(record.mobile_data_cost);
                $('#total_trips').val(record.total_trips);
                $('#total_time_online').val(record.total_time_online);
                
                updateCardIncome();
                form.show();
            } else {
                result.html('<div class="error-message">Uber income record not found</div>');
            }
        },
        error: function() {
            loading.hide();
            result.html('<div class="error-message">Failed to load Uber income record</div>');
        }
    });

    // Calculate and display card income
    function updateCardIncome() {
        const total = parseFloat($('#total_income').val()) || 0;
        const cash = parseFloat($('#cash_received').val()) || 0;
        const card = total - cash;
        
        if (card >= 0) {
            $('#card_income_display').text('Card income: R' + card.toFixed(2));
            $('#cash_received').css('border-color', '');
        } else {
            $('#card_income_display').text('⚠️ Cash exceeds total income!');
            $('#cash_received').css('border-color', 'red');
        }
    }

    $('#total_income, #cash_received').on('input', updateCardIncome);

    // Handle form submission
    $('#uberEditForm').on('submit', function(e) {
        e.preventDefault();
        
        const totalIncome = parseFloat($('#total_income').val());
        const cashReceived = parseFloat($('#cash_received').val());
        const mobileDataCost = parseFloat($('#mobile_data_cost').val());
        const totalTrips = parseInt($('#total_trips').val());
        const totalTimeOnline = parseFloat($('#total_time_online').val());

        // Validate cash doesn't exceed total
        if (cashReceived > totalIncome) {
            result.html('<div class="error-message">Cash received cannot exceed total income</div>');
            return;
        }

        const submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).text('Updating...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
            data: {
                action: 'update',
                id: recordId,
                total_income: totalIncome,
                cash_received: cashReceived,
                mobile_data_cost: mobileDataCost,
                total_trips: totalTrips,
                total_time_online: totalTimeOnline
            },
            dataType: 'json',
            success: function(response) {
                submitBtn.prop('disabled', false).text('💾 Update Income');
                if (response.success) {
                    result.html('<div class="success-message">' + response.message + '. Redirecting...</div>');
                    setTimeout(function() {
                        window.location.href = '<?= BASE_URL ?>/modules/Uber/';
                    }, 2000);
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text('💾 Update Income');
                result.html('<div class="error-message">❌ An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>