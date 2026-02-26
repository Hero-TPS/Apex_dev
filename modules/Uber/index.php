<?php
$page_title = 'Uber Reports';
$page_subtitle = 'Weekly Income Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Uber';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';
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
                <th>Additional Costs (R)</th> <!-- ✅ NEW -->
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="uber-report-body">
            <tr>
                <td colspan="8" style="text-align:center;">Loading...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function () {
        loadUberReports();

        function loadUberReports() {
            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php?action=get_all',
                dataType: 'json',
                success: function (response) {
                    const body = $('#uber-report-body');
                    if (response.success && response.data.length > 0) {
                        body.empty();
                        response.data.forEach(log => {
                            const cardIncome = (parseFloat(log.total_income) - parseFloat(log.cash_received)).toFixed(2);
                            const additionalCost = parseFloat(log.additional_cost || 0).toFixed(2); // ✅ NEW
                            const costReasonText = log.cost_reason ? ` (${log.cost_reason})` : ''; // ✅ NEW

                            body.append(`
                                <tr data-log-id="${log.id}">
                                    <td data-label="Week">${log.week_display}</td>
                                    <td data-label="Total Income">R ${parseFloat(log.total_income).toFixed(2)}</td>
                                    <td data-label="Cash Received">R ${parseFloat(log.cash_received).toFixed(2)}</td>
                                    <td data-label="Trips">${log.total_trips}</td>
                                    <td data-label="Time Online">${parseFloat(log.total_time_online).toFixed(1)} hrs</td>
                                    <td data-label="Card Income">R ${cardIncome}</td>
                                    <td data-label="Mobile Data">R ${parseFloat(log.mobile_data_cost).toFixed(2)}</td>
                                    <td data-label="Additional Costs">R ${additionalCost}${costReasonText}</td>
                                    <td data-label="Actions">
                                        <div class="actions-container">
                                            <a href="<?= BASE_URL ?>/modules/Uber/edit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
                                            <button class="action-btn delete-btn" data-id="${log.id}">🗑️ Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            `);
                        });
                    } else {
                        body.html('<tr><td colspan="9" class="error-message">No Uber income records found.</td></tr>');
                    }
                },
                error: function () {
                    $('#uber-report-body').html('<tr><td colspan="9" class="error-message">Failed to load Uber income reports.</td></tr>');
                }
            });
        }

        // Delete button
        $(document).on('click', '.delete-btn', function () {
            if (!confirm('Delete this week\'s income?')) return;
            const id = $(this).data('id');

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('tr[data-log-id="' + id + '"]').fadeOut(function () {
                            $(this).remove();
                            if ($('#uber-report-body tr').length === 0) {
                                loadUberReports();
                            }
                        });
                        showNotification('✓ ' + res.message, 'success');
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to delete record', 'error');
                }
            });
        });

        function showNotification(message, type) {
            const className = type === 'success' ? 'success-message' : 'error-message';
            const notification = $('<div class="' + className + '">' + message + '</div>');
            $('.content').prepend(notification);
            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>