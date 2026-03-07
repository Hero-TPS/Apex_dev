<?php
$page_title = 'Log Uber Income';
$page_subtitle = 'Log Weekly Uber Earnings';
$show_breadcrumb = true;
$breadcrumb = ' > Uber > Add';

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';

// Generate last 8 Mondays (2 months back)
$mondays = [];
$today = new DateTime('now', new DateTimeZone(TIME_ZONE));
for ($i = 0; $i < 8; $i++) {
    $monday = clone $today;
    $monday->modify("monday last week -{$i} weeks");
    $mondays[] = $monday->format('Y-m-d');
}
$default_monday = $mondays[0];

// Get existing weeks as Y-m-d strings
$stmt = $pdo->query("SELECT week_start FROM uber_income");
$existing_weeks_ymd = [];
while ($row = $stmt->fetch()) {
    $dt = new DateTime();
    $dt->setTimestamp($row['week_start']);
    $dt->setTimezone(new DateTimeZone(TIME_ZONE));
    $existing_weeks_ymd[] = $dt->format('Y-m-d');
}

// Fetch cost reasons for dropdown
$cost_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');
?>

<div class="form-container">
    <h2>🚗 Log Uber Income</h2>
    <form id="uberForm">
        <div class="form-group">
            <label>Week Start (Monday)</label>
            <select id="week_monday" name="week_monday" required>
                <?php foreach ($mondays as $monday): ?>
                    <?php
                    $dt = new DateTime($monday);
                    $display = $dt->format('d M Y');
                    $exists = in_array($monday, $existing_weeks_ymd) ? ' (Exists)' : '';
                    $selected = ($monday == $default_monday) ? 'selected' : '';
                    ?>
                    <option value="<?= $monday ?>" <?= $selected ?>><?= $display ?><?= $exists ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="total_income">Total Uber Income (R) <span class="required">*</span></label>
            <input type="number" id="total_income" name="total_income" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="cash_received">Cash Received (R) <span class="required">*</span></label>
            <input type="number" id="cash_received" name="cash_received" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="total_trips">Total Trips <span class="required">*</span></label>
            <input type="number" id="total_trips" name="total_trips" min="0" required>
        </div>

        <div class="form-group">
            <label for="total_time_online">Total Time Online (hours) <span class="required">*</span></label>
            <input type="number" id="total_time_online" name="total_time_online" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>Additional Costs</label>
            <div id="additional-costs-container">
                <!-- Cost rows added dynamically -->
            </div>
            <button type="button" class="btn" id="addCostBtn" style="margin-top: 8px; width: auto;">+ Add Cost</button>
        </div>

        <button type="submit" class="btn" id="submitBtn">💾 Save Income</button>
    </form>
    <div id="result"></div>
</div>

<script>
    const costReasons = <?= json_encode($cost_reasons) ?>;

    function buildReasonOptions(selected = '') {
        let options = '<option value="">Select reason</option>';
        costReasons.forEach(r => {
            options += `<option value="${r}" ${r === selected ? 'selected' : ''}>${r}</option>`;
        });
        return options;
    }

    function addCostRow(reason = '', amount = '') {
        const row = $(`
            <div class="cost-row" style="display: flex; gap: 8px; margin-bottom: 6px; align-items: center;">
                <select name="cost_reasons[]" style="flex: 2;">
                    ${buildReasonOptions(reason)}
                </select>
                <input type="number" name="cost_amounts[]" step="0.01" min="0" placeholder="Amount (R)"
                    value="${amount}" style="flex: 1;">
                <button type="button" class="remove-cost-btn action-btn delete-btn" style="width: auto;">✕</button>
            </div>
        `);
        $('#additional-costs-container').append(row);
    }

    $(document).ready(function () {

        $('#addCostBtn').on('click', function () {
            addCostRow();
        });

        $(document).on('click', '.remove-cost-btn', function () {
            $(this).closest('.cost-row').remove();
        });

        $('#uberForm').on('submit', function (e) {
            e.preventDefault();

            const submitBtn = $('#submitBtn');
            const result = $('#result');

            submitBtn.prop('disabled', true).text('Saving...');

            const formData = {
                action: 'add',
                week_monday: $('#week_monday').val(),
                total_income: $('#total_income').val(),
                cash_received: $('#cash_received').val(),
                total_trips: $('#total_trips').val(),
                total_time_online: $('#total_time_online').val(),
                'cost_reasons[]': $('select[name="cost_reasons[]"]').map(function () { return $(this).val(); }).get(),
                'cost_amounts[]': $('input[name="cost_amounts[]"]').map(function () { return $(this).val(); }).get()
            };

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        result.html('<div class="success-message">✓ ' + response.message + '</div>');
                        $('#uberForm')[0].reset();
                        $('#additional-costs-container').empty();
                        setTimeout(() => {
                            window.location.href = '<?= BASE_URL ?>/modules/Uber/';
                        }, 1500);
                    } else {
                        result.html('<div class="error-message">✗ ' + response.message + '</div>');
                    }
                },
                error: function (xhr) {
                    let errorMsg = 'Failed to save Uber income';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) errorMsg = response.message;
                    } catch (e) {
                        errorMsg = xhr.responseText || 'Unknown error occurred';
                    }
                    result.html('<div class="error-message">✗ ' + errorMsg + '</div>');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).text('💾 Save Income');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>