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
            <label for="mobile_data_cost">Mobile Data Cost (R)</label>
            <input type="number" id="mobile_data_cost" name="mobile_data_cost" step="0.01" min="0" value="0" required>
        </div>

        <div class="form-group">
            <label for="additional_cost">Additional Costs (R)</label>
            <input type="number" id="additional_cost" name="additional_cost" step="0.01" min="0" value="0">
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

        <button type="submit" class="btn" id="submitBtn">💾 Save Income</button>
    </form>
    <div id="result"></div>
</div>

<script>
    $(document).ready(function () {
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
                        $('#uberForm')[0].reset();
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
                        if (response.message) {
                            errorMsg = response.message;
                        }
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