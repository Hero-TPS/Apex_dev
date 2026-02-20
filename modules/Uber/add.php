<?php
$page_title = 'Log Uber Income';
$page_subtitle = 'Log Weekly Uber Earnings';
$show_breadcrumb = true;
$breadcrumb = ' > Uber > Add';

require_once __DIR__ . '/../../config.php';
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
$(document).ready(function() {
    // Calculate card income automatically
    function updateCardIncome() {
        const total = parseFloat($('#total_income').val()) || 0;
        const cash = parseFloat($('#cash_received').val()) || 0;
        const card = total - cash;
        
        if (card >= 0) {
            $('#cash_received').css('border-color', '');
        } else {
            $('#cash_received').css('border-color', 'red');
        }
    }

    $('#total_income, #cash_received').on('input', updateCardIncome);

    $('#uberForm').on('submit', function(e) {
        e.preventDefault();
        
        const weekMonday = $('#week_monday').val();
        const totalIncome = parseFloat($('#total_income').val());
        const cashReceived = parseFloat($('#cash_received').val());
        const mobileDataCost = parseFloat($('#mobile_data_cost').val());
        const totalTrips = parseInt($('#total_trips').val());
        const totalTimeOnline = parseFloat($('#total_time_online').val());

        // Validate cash doesn't exceed total
        if (cashReceived > totalIncome) {
            $('#result').html('<div class="error-message">Cash received cannot exceed total income</div>');
            return;
        }

        // Convert Monday date to Unix timestamp
        const mondayDate = new Date(weekMonday + 'T00:00:00');
        const mondayUnix = Math.floor(mondayDate.getTime() / 1000);
        
        // Calculate Sunday (6 days later)
        const sundayUnix = mondayUnix + (6 * 24 * 60 * 60);

        const submitBtn = $('#submitBtn');
        const result = $('#result');
        submitBtn.prop('disabled', true).text('Saving...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
            data: {
                action: 'add',
                week_monday_unix: mondayUnix,
                week_sunday_unix: sundayUnix,
                total_income: totalIncome,
                cash_received: cashReceived,
                mobile_data_cost: mobileDataCost,
                total_trips: totalTrips,
                total_time_online: totalTimeOnline
            },
            dataType: 'json',
            success: function(response) {
                submitBtn.prop('disabled', false).text('💾 Save Income');
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
                submitBtn.prop('disabled', false).text('💾 Save Income');
                result.html('<div class="error-message">❌ An error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>