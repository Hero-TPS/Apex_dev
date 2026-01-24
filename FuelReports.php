<?php
$page_title = 'Fuel Reports';
$page_subtitle = 'Fuel Log Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Fuel Reports';
include 'includes/header.php';
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
    $.ajax({
        url: 'api/fuel_logs.php?action=get_all',
        dataType: 'json',
        success: function(response) {
            const body = $('#fuel-report-body');
            if (response.success && response.data.length > 0) {
                body.empty();
                response.data.forEach(log => {
                    const paymentDisplay = log.payment_method === 'eft' ? 'EFT' : 'Cash';
                    body.append(`
                        <tr>
                            <td data-label="Date">${log.log_datetime}</td>
                            <td data-label="Odo">${parseFloat(log.odo_km).toFixed(1)}</td>
                            <td data-label="Trip">${parseFloat(log.trip_km).toFixed(1)}</td>
                            <td data-label="Price">R ${parseFloat(log.fuel_price).toFixed(2)}</td>
                            <td data-label="Cost">R ${parseFloat(log.total_cost).toFixed(2)}</td>
                            <td data-label="Payment">${paymentDisplay}</td>
                            <td data-label="Actions">
                                <div class="actions-container">
                                    <a href="FuelLogEdit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
                                    <button class="action-btn delete-btn" data-id="${log.id}">🗑️ Delete</button>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            } else {
                body.html('<tr><td colspan="7" class="error-message">No logs found.</td></tr>');
            }
        },
        error: function() {
            $('#fuel-report-body').html('<tr><td colspan="7" class="error-message">Failed to load.</td></tr>');
        }
    });

    // Delete handler
    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Delete this fuel log?')) return;
        const id = $(this).data('id');
        $.ajax({
            url: 'api/fuel_logs.php',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success) location.reload();
                else alert(res.message);
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>