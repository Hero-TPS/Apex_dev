<?php
$page_title = 'Edit Fuel Log';
$page_subtitle = 'Update Fuel Entry';
$show_breadcrumb = true;
$breadcrumb = ' > Fuel Log > Edit';
include 'includes/header.php';

$id = $_GET['id'] ?? null;
$log = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM fuel_logs WHERE id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch();
}
if (!$log)
    die('<div class="error-message">Log not found.</div>');

// Convert timestamp to datetime-local format
$dt = new DateTime();
$dt->setTimestamp($log['log_timestamp']);
$dt->setTimezone(new DateTimeZone(TIME_ZONE));
$datetime_local = $dt->format('Y-m-d\TH:i');
?>

<div class="form-container">
    <h2>✏️ Edit Fuel Log</h2>
    <form id="editForm">
        <input type="hidden" name="id" value="<?= $log['id'] ?>">
        <div class="form-group">
            <label>Date & Time</label>
            <input type="datetime-local" name="log_datetime" value="<?= $datetime_local ?>" required>
        </div>
        <div class="form-group">
            <label>Fuel Price (R/liter)</label>
            <input type="number" name="fuel_price" value="<?= $log['fuel_price'] ?>" step="0.01" required>
        </div>
        <div class="form-group">
            <label>Total Cost (R)</label>
            <input type="number" name="total_cost" value="<?= $log['total_cost'] ?>" step="0.01" required>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="payment_method" value="eft" <?= $log['payment_method'] === 'eft' ? 'checked' : '' ?>>
                EFT Payment
            </label>
        </div>
        <button type="submit" class="page-action-btn save">💾 Update Log</button>
    </form>
</div>

<script>
    $(document).ready(function () {
        $('#editForm').on('submit', function (e) {
            e.preventDefault();
            const datetimeLocal = $('input[name="log_datetime"]').val();
            const timestamp = Math.floor(new Date(datetimeLocal).getTime() / 1000);

            $.ajax({
                url: 'api/fuel_logs.php',
                type: 'POST',
                // ✅ 'data' is explicitly present
                data: {
                    action: 'update',
                    id: $('input[name="id"]').val(),
                    log_timestamp: timestamp,
                    fuel_price: $('input[name="fuel_price"]').val(),
                    total_cost: $('input[name="total_cost"]').val(),
                    payment_method: $('input[name="payment_method"]').is(':checked') ? 'eft' : 'cash'
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#result').html('<div class="success-message">✅ Log updated! Redirecting...</div>');
                        setTimeout(() => window.location.href = 'FuelReports.php', 1000);
                    } else {
                        $('#result').html('<div class="error-message">' + res.message + '</div>');
                    }
                },
                error: function () {
                    alert('❌ Failed to update fuel log.');
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>