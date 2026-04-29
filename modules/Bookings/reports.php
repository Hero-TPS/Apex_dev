<?php
//booking reports
$page_title = 'Booking Reports';
$page_subtitle = 'Monthly Summary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Bookings', 'url' => BASE_URL . '/modules/Bookings/'],
    ['label' => 'Reports'],
]);
include ROOT_DIR . '/includes/header.php';
$monthsBack = (int) getSystemVariable($pdo, 'financial_months_back');
if ($monthsBack < 1) {
    $monthsBack = 3;
}

$months = [];
$today        = new DateTime();
$firstOfMonth = new DateTime($today->format('Y-m-01'));
for ($i = 0; $i < $monthsBack; $i++) {
    $date  = clone $firstOfMonth;
    $date->modify("-$i months");
    $months[] = [
        'year'  => (int) $date->format('Y'),
        'month' => (int) $date->format('n'),
    ];
}
usort($months, function ($a, $b) {
    if ($a['year'] !== $b['year']) {
        return $b['year'] - $a['year'];
    }
    return $b['month'] - $a['month'];
});

// Calculate current working week boundaries (Mon–Sun)
$tz = new DateTimeZone(TIME_ZONE);
$todayDt    = new DateTime('now', $tz);
$dayOfWeek  = (int) $todayDt->format('N'); // 1=Mon, 7=Sun
$currentWeekMonday = clone $todayDt;
$currentWeekMonday->modify('-' . ($dayOfWeek - 1) . ' days');
$currentWeekMonday->setTime(0, 0, 0);
$currentWeekSunday = clone $currentWeekMonday;
$currentWeekSunday->modify('+6 days');
$currentWeekSunday->setTime(23, 59, 59);
?>

<div class="financial-dashboard">
    <h2>📊 Booking Reports (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <?php foreach ($months as $m):
        $startDate = new DateTime("{$m['year']}-{$m['month']}-01");
        $endDate   = clone $startDate;
        $endDate->modify('last day of this month');

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(cost), 0) AS total_income, COUNT(*) AS booking_count
             FROM bookings WHERE trip_date BETWEEN ? AND ?"
        );
        $stmt->execute([$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $monthLabel = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));

        $monthStart = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $monthEnd   = clone $monthStart;
        $monthEnd->modify('last day of this month');
        $monthEnd->setTime(23, 59, 59);
        $isInProgressMonth = ($currentWeekMonday <= $monthEnd && $currentWeekSunday >= $monthStart);
    ?>
        <div class="financial-month-block<?= $isInProgressMonth ? ' week-in-progress-block' : '' ?>" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?><?= $isInProgressMonth ? ' <span class="week-in-progress">⏳ In Progress</span>' : '' ?></h3>
                <span class="net-amount profit">R<?= number_format($row['total_income'], 2) ?></span>
            </div>

            <div class="metric-row"><span>Total Bookings:</span> <strong><?= (int) $row['booking_count'] ?></strong></div>
            <div class="metric-row"><span>Total Income:</span>   <strong>R<?= number_format($row['total_income'], 2) ?></strong></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>

            <button class="toggle-bookings-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>" style="margin-top:8px;">
                📋 View Bookings
            </button>
            <div class="bookings-detail-container hidden" style="margin-top:8px; overflow-x:auto;"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function buildWeekBlock(week) {
        const inProgressBadge = week.in_progress
            ? '<span class="week-in-progress">⏳ In Progress</span>'
            : '';
        return `
            <div class="weekly-block${week.in_progress ? ' week-in-progress-block' : ''}">
                <div class="week-header">
                    <strong>Week: ${week.week_label} ${inProgressBadge}</strong>
                    <span class="net-amount profit">R${parseFloat(week.total_income || 0).toFixed(2)}</span>
                </div>
                <div class="metric-row"><span>Bookings:</span>     <strong>${week.booking_count}</strong></div>
                <div class="metric-row"><span>Total Income:</span> <strong>R${parseFloat(week.total_income || 0).toFixed(2)}</strong></div>
            </div>
        `;
    }

    function buildBookingsTable(bookings) {
        if (!bookings || bookings.length === 0) {
            return '<p style="color:#999; font-style:italic; padding:8px 0;">No bookings for this month.</p>';
        }
        var rows = bookings.map(function (b) {
            var driverCell = b.driver_name
                ? '<span style="color:#27ae60;">✓ ' + escapeHtml(b.driver_name) + '</span>'
                : '<span style="color:#aaa;">—</span>';
            var feeCell = b.booking_fee !== null && b.booking_fee !== undefined
                ? 'R' + parseFloat(b.booking_fee).toFixed(2)
                : '<span style="color:#aaa;">—</span>';
            return '<tr>' +
                '<td><a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=' + b.id + '" style="text-decoration:none;">' + escapeHtml(b.trip_date) + '</a></td>' +
                '<td>' + escapeHtml(b.start_time) + '</td>' +
                '<td>' + escapeHtml(b.client_name) + '</td>' +
                '<td>' + escapeHtml(b.pickup) + '</td>' +
                '<td>' + escapeHtml(b.destination) + '</td>' +
                '<td>R' + parseFloat(b.cost).toFixed(2) + '</td>' +
                '<td>' + driverCell + '</td>' +
                '<td>' + feeCell + '</td>' +
                '</tr>';
        }).join('');
        return '<table style="width:100%; border-collapse:collapse; font-size:0.85em;">' +
            '<thead><tr style="background:#f5f5f5;">' +
            '<th style="text-align:left; padding:4px 8px;">Date</th>' +
            '<th style="text-align:left; padding:4px 8px;">Time</th>' +
            '<th style="text-align:left; padding:4px 8px;">Client</th>' +
            '<th style="text-align:left; padding:4px 8px;">Pickup</th>' +
            '<th style="text-align:left; padding:4px 8px;">Destination</th>' +
            '<th style="text-align:right; padding:4px 8px;">Cost</th>' +
            '<th style="text-align:left; padding:4px 8px;">Driver</th>' +
            '<th style="text-align:right; padding:4px 8px;">Booking Fee</th>' +
            '</tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '</table>';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    $(document).ready(function () {
        let currentlyOpenContainer = null;
        let currentlyOpenBookingsContainer = null;

        $(document).on('click', '.toggle-bookings-btn', function () {
            const button    = $(this);
            const year      = button.data('year');
            const month     = button.data('month');
            const container = button.next('.bookings-detail-container');

            if (currentlyOpenBookingsContainer && currentlyOpenBookingsContainer !== container[0]) {
                $(currentlyOpenBookingsContainer).addClass('hidden').empty();
                $(currentlyOpenBookingsContainer).prev('.toggle-bookings-btn').text('📋 View Bookings');
            }

            if (container.hasClass('hidden')) {
                if (container.is(':empty')) {
                    container.html('<div style="text-align:center; padding:10px;">Loading bookings…</div>');
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                        method: 'GET',
                        data: { action: 'monthly_bookings_list', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success) {
                                container.html(buildBookingsTable(response.bookings));
                            } else {
                                container.html('<div class="error-message">⚠️ ' + (response.message || 'Failed to load') + '</div>');
                            }
                        },
                        error: function (xhr, status, err) {
                            container.html('<div class="error-message">⚠️ Network error: ' + err + '</div>');
                        }
                    });
                }
                container.removeClass('hidden');
                button.text('📋 Hide Bookings');
                currentlyOpenBookingsContainer = container[0];
            } else {
                container.addClass('hidden');
                button.text('📋 View Bookings');
                currentlyOpenBookingsContainer = null;
            }
        });

        $(document).on('click', '.toggle-weeks-btn', function () {
            const button    = $(this);
            const year      = button.data('year');
            const month     = button.data('month');
            const container = button.next('.weeks-container');

            if (currentlyOpenContainer && currentlyOpenContainer !== container[0]) {
                $(currentlyOpenContainer).addClass('hidden').empty();
                $(currentlyOpenContainer).prev('.toggle-weeks-btn').text('🔽 View Weeks');
            }

            if (container.hasClass('hidden')) {
                if (container.is(':empty')) {
                    container.html('<div style="text-align:center; padding:10px;">Loading weeks…</div>');
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                        method: 'GET',
                        data: { action: 'weekly_bookings_by_month', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success && response.data.length > 0) {
                                response.data.forEach(function (week) {
                                    container.append(buildWeekBlock(week));
                                });
                            } else if (response.success) {
                                container.html('<div class="error-message">No weeks found for this month.</div>');
                            } else {
                                container.html('<div class="error-message">⚠️ ' + (response.message || 'Failed to load weeks') + '</div>');
                            }
                        },
                        error: function (xhr, status, err) {
                            container.html('<div class="error-message">⚠️ Network error: ' + err + '</div>');
                        }
                    });
                }
                container.removeClass('hidden');
                button.text('🔼 Hide Weeks');
                currentlyOpenContainer = container[0];
            } else {
                container.addClass('hidden');
                button.text('🔽 View Weeks');
                currentlyOpenContainer = null;
            }
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>