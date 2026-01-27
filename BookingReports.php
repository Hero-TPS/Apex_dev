<?php
$page_title = 'Booking Reports';
$page_subtitle = 'Weekly Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Reports > Weekly Bookings';

require_once __DIR__ . '/config.php'; // or '/../config.php' if in subfolder
include ROOT_DIR . '/includes/header.php';
?>

<div class="content">
    <h2>📊 Weekly Booking Report</h2>
    <p>Summary of bookings and income from Monday 00:00 to Sunday 24:00.</p>

    <div id="report-container">
        <table class="bookings-table">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Bookings</th>
                    <th>Total Income (R)</th>
                </tr>
            </thead>
            <tbody id="report-body">
                <tr>
                    <td colspan="3" style="text-align:center;">Loading report...</td>
                </tr>
            </tbody>
        </table>
    </div>


    <!-- Monthly Report -->
    <h3 style="margin-top: 40px;">📆 Monthly Report (<?= date('Y') ?>)</h3>
    <div id="monthly-report-container">
        <table class="bookings-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Bookings</th>
                    <th>Total Income (R)</th>
                </tr>
            </thead>
            <tbody id="monthly-report-body">
                <tr>
                    <td colspan="3" style="text-align:center;">Loading monthly report...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <!-- Future: Add buttons for other reports here -->
    <!--
    <div style="margin-top: 20px;">
        <a href="#" class="btn">📅 Daily Report</a>
        <a href="#" class="btn">📆 Monthly Report</a>
    </div>
    -->
</div>

<script>
    $(document).ready(function () {
        $.ajax({
            url: 'api/reports.php?action=weekly_bookings',
            dataType: 'json',
            success: function (response) {
                const body = $('#report-body');
                if (response.success && response.data.length > 0) {
                    body.empty();
                    response.data.forEach(week => {
                        body.append(`
                        <tr>
                            <td data-label="Week">${escapeHtml(week.week_label)}</td>
                            <td data-label="Bookings">${week.booking_count}</td>
                            <td data-label="Total Income (R)">R ${parseFloat(week.total_income).toFixed(2)}</td>
                        </tr>
                    `);
                    });
                } else {
                    body.html('<tr><td colspan="3" class="error-message">No data available.</td></tr>');
                }
            },
            error: function () {
                $('#report-body').html('<tr><td colspan="3" class="error-message">Failed to load report.</td></tr>');
            }
        });

        // Load monthly report
        $.ajax({
            url: 'api/reports.php?action=monthly_bookings',
            dataType: 'json',
            success: function (response) {
                const body = $('#monthly-report-body');
                if (response.success && response.data.length > 0) {
                    body.empty();
                    response.data.forEach(month => {
                        body.append(`
                    <tr>
                        <td data-label="Month">${escapeHtml(month.month_label)}</td>
                        <td data-label="Bookings">${month.booking_count}</td>
                        <td data-label="Total Income (R)">R ${parseFloat(month.total_income).toFixed(2)}</td>
                    </tr>
                `);
                    });
                } else {
                    body.html('<tr><td colspan="3" class="error-message">No data available.</td></tr>');
                }
            },
            error: function () {
                $('#monthly-report-body').html('<tr><td colspan="3" class="error-message">Failed to load monthly report.</td></tr>');
            }
        });

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>