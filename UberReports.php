<?php
$page_title = 'Uber Reports';
$page_subtitle = 'Weekly Income Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Uber Reports';
include 'includes/header.php';
?>

<div class="content">
    <h2>🚗 Uber Income Report</h2>
    <table class="bookings-table">
        <thead>
            <tr>
                <th>Week</th>
                <th>Total Income (R)</th>
                <th>Cash Received (R)</th>
                <th>Total Trips</th>
                <th>Time Online</th>
                <th>Card Income (R)</th>
                <th>Mobile Data (R)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="uber-report-body">
            <tr><td colspan="7" style="text-align:center;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    $.ajax({
        url: 'api/uber_income.php?action=get_all',
        dataType: 'json',
        success: function(response) {
            const body = $('#uber-report-body');
            if (response.success && response.data.length > 0) {
                body.empty();
                response.data.forEach(log => {
                    const cardIncome = (parseFloat(log.total_income) - parseFloat(log.cash_received)).toFixed(2);
                    body.append(`
                        <tr>
                            <td data-label="Week">${log.week_display}</td>
                            <td data-label="Total Income">R ${parseFloat(log.total_income).toFixed(2)}</td>
                            <td data-label="Cash Received">R ${parseFloat(log.cash_received).toFixed(2)}</td>
                            <td data-label="Trips">${log.total_trips}</td>
                            <td data-label="Time Online">${parseFloat(log.total_time_online).toFixed(1)} hrs</td>
                            <td data-label="Card Income">R ${cardIncome}</td>
                            <td data-label="Mobile Data">R ${parseFloat(log.mobile_data_cost).toFixed(2)}</td>
                            <td data-label="Actions">
                                <div class="actions-container">
                                    <a href="UberEdit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
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
            $('#uber-report-body').html('<tr><td colspan="7" class="error-message">Failed to load.</td></tr>');
        }
    });

    // Delete button
    $(document).on('click', '.delete-btn', function() {
        if (!confirm('Delete this week\'s income?')) return;
        const id = $(this).data('id');
        $.ajax({
            url: 'api/uber_income.php?action=delete',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: { id: id },
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