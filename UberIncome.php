<?php
$page_title = 'Uber Income';
$page_subtitle = 'Log Weekly Uber Earnings';
$show_breadcrumb = true;
$breadcrumb = ' > Uber Income';

require_once __DIR__ . '/config.php';
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
            <label>Total Uber Income (R)</label>
            <input type="number" name="total_income" step="0.01" required>
        </div>

        <div class="form-group">
            <label>Cash Received (R)</label>
            <input type="number" name="cash_received" step="0.01" required>
        </div>

        <div class="form-group">
            <label>Mobile Data Cost (R)</label>
            <input type="number" name="mobile_data_cost" step="0.01" min="0" value="0" required>
        </div>

        <div class="form-group">
            <label>Total Trips</label>
            <input type="number" name="total_trips" min="0" required>
        </div>

        <div class="form-group">
            <label>Total Time Online (hours)</label>
            <input type="number" name="total_time_online" step="0.01" min="0" required>
        </div>

        <button type="submit" class="page-action-btn save">💾 Save Income</button>
    </form>
    <div id="result"></div>
</div>

<script>
$(document).ready(function() {
    $('#uberForm').on('submit', function(e) {
        e.preventDefault();
        const mondayStr = $('#week_monday').val(); // Y-m-d

        // ✅ Generate timestamp in LOCAL (SAST) time
        const [y, m, d] = mondayStr.split('-');
        const localMonday = new Date(y, m - 1, d);
        const mondayUnix = Math.floor(localMonday.getTime() / 1000);

        const sundayLocal = new Date(localMonday);
        sundayLocal.setDate(sundayLocal.getDate() + 6);
        sundayLocal.setHours(23, 59, 59, 999);
        const sundayUnix = Math.floor(sundayLocal.getTime() / 1000);

        $.ajax({
            url: 'api/uber_income.php?action=check_exists',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: { week_monday_unix: mondayUnix },
            dataType: 'json',
            success: function(res) {
                if (res.exists && !confirm('Entry for this week already exists. Overwrite it?')) return;
                const formData = new FormData();
                formData.append('week_monday_unix', mondayUnix);
                formData.append('week_sunday_unix', sundayUnix);
                formData.append('total_income', $('input[name="total_income"]').val());
                formData.append('cash_received', $('input[name="cash_received"]').val());
                formData.append('mobile_data_cost', $('input[name="mobile_data_cost"]').val());
                formData.append('total_trips', $('input[name="total_trips"]').val());
                formData.append('total_time_online', $('input[name="total_time_online"]').val());

                $.ajax({
                    url: 'api/uber_income.php?action=add',
                    type: 'POST',
                    // ✅ 'data' is explicitly present
                     data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            window.location.href = 'UberReports.php';
                        } else {
                            $('#result').html('<div class="error-message">' + res.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#result').html('<div class="error-message">❌ Failed to save.</div>');
                    }
                });
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>