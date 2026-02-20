<?php
$page_title = 'Fuel Reports';
$page_subtitle = 'Fuel Log Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Fuel';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';
?>

<div class="content">
    <h2>⛽ Fuel Log Report</h2>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Odometer (km)</th>
                <th>Trip (km)</th>
                <th>Price (R/l)</th>
                <th>Total Cost (R)</th>
                <th>Payment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="fuel-report-body">
            <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    loadFuelLogs();

    function loadFuelLogs() {
        $.ajax({
            url: '<?= BASE_URL ?>/modules/Fuel/api/index.php?action=get_all',
            dataType: 'json',
            success: function(response) {
                const body = $('#fuel-report-body');
                if (response.success && response.data.length > 0) {
                    body.empty();
                    response.data.forEach(log => {
                        const paymentDisplay = log.payment_method === 'eft' ? 'EFT' : 'Cash';
                        body.append(`
                            <tr data-log-id="${log.id}">
                                <td data-label="Date">${log.log_datetime}</td>
                                <td data-label="Odo">${parseFloat(log.odo_km).toFixed(1)}</td>
                                <td data-label="Trip">${parseFloat(log.trip_km).toFixed(1)}</td>
                                <td data-label="Price">R ${parseFloat(log.fuel_price).toFixed(2)}</td>
                                <td data-label="Cost">R ${parseFloat(log.total_cost).toFixed(2)}</td>
                                <td data-label="Payment">${paymentDisplay}</td>
                                <td data-label="Actions">
                                    <div class="actions-container">
                                        <a href="<?= BASE_URL ?>/modules/Fuel/edit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
                                        <button class="action-btn delete-btn" data-id="${log.id}">🗑️ Delete</button>
                                    </div>
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    body.html('<tr><td colspan="7" class="error-message">No fuel logs found.</td></tr>');
                }
            },
            error: function() {
                $('#fuel-report-body').html('<tr><td colspan="7" class="error-message">Failed to load fuel logs.</td></tr>');
            }
        });
    }

    // Delete handler
    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Delete this fuel log?')) return;
        const id = $(this).data('id');
        
        $.ajax({
            url: '<?= BASE_URL ?>/modules/Fuel/api/index.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('tr[data-log-id="' + id + '"]').fadeOut(function() {
                        $(this).remove();
                        if ($('#fuel-report-body tr').length === 0) {
                            loadFuelLogs();
                        }
                    });
                    showNotification('✓ ' + res.message, 'success');
                } else {
                    showNotification('✗ ' + res.message, 'error');
                }
            },
            error: function() {
                showNotification('❌ Failed to delete fuel log', 'error');
            }
        });
    });

    function showNotification(message, type) {
        const className = type === 'success' ? 'success-message' : 'error-message';
        const notification = $('<div class="' + className + '">' + message + '</div>');
        $('.content').prepend(notification);
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>