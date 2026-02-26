<?php
$page_title = 'Edit Uber Income';
$page_subtitle = 'Update weekly Uber earnings';
$show_breadcrumb = true;
$breadcrumb = ' > Uber > Edit';

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
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
            <label for="additional_cost">Additional Costs (R)</label>
            <input type="number" id="additional_cost" name="additional_cost" step="0.01" min="0">
            <small>Extra expenses like parking, tolls, car wash, etc.</small>
        </div>

        <div class="form-group">
            <label for="cost_reason">Cost Reason</label>
            <select id="cost_reason" name="cost_reason">
                <option value="">Select reason (optional)</option>
                <?php
                $cost_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');
                foreach ($cost_reasons as $reason):
                    ?>
                    <option value="<?= htmlspecialchars($reason) ?>"><?= htmlspecialchars($reason) ?></option>
                <?php endforeach; ?>
            </select>
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
    $(document).ready(function () {
        const recordId = <?= $record_id ?>;
        const loading = $('#loading');
        const form = $('#editForm');
        const result = $('#result');

        // Load Uber income record
        $.ajax({
            url: '<?= BASE_URL ?>/modules/Uber/api/index.php?action=get_single&id=' + recordId,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.record) {
                    const record = response.record;

                    // Format week display
                    const startDate = new Date(record.week_start * 1000);
                    const endDate = new Date(record.week_end * 1000);
                    const weekDisplay = startDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
                        ' – ' + endDate.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

                    // Populate form fields
                    $('#week_display').val(weekDisplay);
                    $('#total_income').val(parseFloat(record.total_income).toFixed(2));
                    $('#cash_received').val(parseFloat(record.cash_received).toFixed(2));
                    $('#mobile_data_cost').val(parseFloat(record.mobile_data_cost).toFixed(2));
                    $('#additional_cost').val(parseFloat(record.additional_cost || 0).toFixed(2));
                    $('#cost_reason').val(record.cost_reason || '');
                    $('#total_trips').val(record.total_trips);
                    $('#total_time_online').val(parseFloat(record.total_time_online).toFixed(1));

                    updateCardIncome();
                    loading.hide();
                    form.show();
                } else {
                    loading.html('<div class="error-message">✗ Record not found</div>');
                }
            },
            error: function () {
                loading.html('<div class="error-message">✗ Failed to load record</div>');
            }
        });

        // Update card income display
        function updateCardIncome() {
            const totalIncome = parseFloat($('#total_income').val()) || 0;
            const cashReceived = parseFloat($('#cash_received').val()) || 0;
            const cardIncome = totalIncome - cashReceived;
            $('#card_income_display').text('Card income: R' + cardIncome.toFixed(2));
        }

        $('#total_income, #cash_received').on('input', updateCardIncome);

        // Form submission
        $('#uberEditForm').on('submit', function (e) {
            e.preventDefault();

            const submitBtn = $('#submitBtn');

            submitBtn.prop('disabled', true).text('Updating...');

            const formData = {
                action: 'update',
                id: $('#record_id').val(),
                total_income: $('#total_income').val(),
                cash_received: $('#cash_received').val(),
                mobile_data_cost: $('#mobile_data_cost').val(),
                additional_cost: $('#additional_cost').val() || 0,
                cost_reason: $('#cost_reason').val(),
                total_trips: $('#total_trips').val(),
                total_time_online: $('#total_time_online').val()
            };

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        result.html('<div class="success-message">✓ ' + response.message + '</div>');
                        setTimeout(() => {
                            window.location.href = '<?= BASE_URL ?>/modules/Uber/';
                        }, 1500);
                    } else {
                        result.html('<div class="error-message">✗ ' + response.message + '</div>');
                    }
                },
                error: function (xhr) {
                    let errorMsg = 'Failed to update Uber income';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        errorMsg = xhr.responseText || 'Unknown error occurred';
                    }
                    result.html('<div class="error-message">✗ ' + errorMsg + '</div>');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).text('💾 Update Income');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>