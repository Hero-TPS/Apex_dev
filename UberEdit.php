<?php
$page_title = 'Edit Uber Income';
$page_subtitle = 'Update Weekly Data';
$show_breadcrumb = true;
$breadcrumb = ' > <a href="UberReports.php">Uber Reports</a> > Edit';

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';

$id = $_GET['id'] ?? null;
$record = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM uber_income WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
}
if (!$record) die('<div class="error-message">Record not found.</div>');

// Convert Unix → d-m-Y for display
$dt_start = new DateTime();
$dt_start->setTimestamp($record['week_start']);
$dt_start->setTimezone(new DateTimeZone(TIME_ZONE));
$dt_end = new DateTime();
$dt_end->setTimestamp($record['week_end'] ?? ($record['week_start'] + 604799)); // fallback
$dt_end->setTimezone(new DateTimeZone(TIME_ZONE));
$week_display = $dt_start->format('d-m-Y') . ' to ' . $dt_end->format('d-m-Y');
?>

<div class="form-container">
    <h2>✏️ Edit Uber Income</h2>
    <p><strong>Week:</strong> <?= htmlspecialchars($week_display) ?></p>
    <form id="editForm">
        <input type="hidden" name="id" value="<?= $record['id'] ?>">
        <div class="form-group">
            <label>Total Uber Income (R)</label>
            <input type="number" name="total_income" value="<?= $record['total_income'] ?>" step="0.01" required>
        </div>
        <div class="form-group">
            <label>Cash Received (R)</label>
            <input type="number" name="cash_received" value="<?= $record['cash_received'] ?>" step="0.01" required>
        </div>
        <div class="form-group">
            <label>Mobile Data Cost (R)</label>
            <input type="number" name="mobile_data_cost" value="<?= $record['mobile_data_cost'] ?? '0' ?>" step="0.01" min="0" required>
        </div>
        <div class="form-group">
            <label>Total Trips</label>
            <input type="number" name="total_trips" value="<?= $record['total_trips'] ?>" min="0" required>
        </div>
        <div class="form-group">
            <label>Total Time Online (hours)</label>
            <input type="number" name="total_time_online" value="<?= $record['total_time_online'] ?>" step="0.1" min="0" required>
        </div>
        <button type="submit" class="page-action-btn save">💾 Update</button>
    </form>
</div>

<script>
$(document).ready(function() {
    $('#editForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('id', $('input[name="id"]').val());
        formData.append('total_income', $('input[name="total_income"]').val());
        formData.append('cash_received', $('input[name="cash_received"]').val());
        formData.append('mobile_data_cost', $('input[name="mobile_data_cost"]').val());
        formData.append('total_trips', $('input[name="total_trips"]').val());
        formData.append('total_time_online', $('input[name="total_time_online"]').val());

        $.ajax({
            url: 'api/uber_income.php?action=update',
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
                    $('#result').html('<div class="error-message">⚠️ ' + res.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#result').html('<div class="error-message">⚠️ Network error: ' + error + '</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>