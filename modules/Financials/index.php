<?php

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Financials/helper.php';

$page_title    = 'Financial Summary';
$show_breadcrumb = true;
$breadcrumb    = buildBreadcrumb([['label' => 'Financials']]);

include ROOT_DIR . '/includes/header.php';

$monthsBack = (int) getSystemVariable($pdo, 'financial_months_back');
if ($monthsBack < 1) {
    $monthsBack = 3;
}

$months = [];
$today  = new DateTime();
for ($i = 0; $i < $monthsBack; $i++) {
    $date  = clone $today;
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
?>

<div class="financial-dashboard">
    <h2>📅 Financial Summary (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <?php foreach ($months as $m):
        $metrics    = getMonthlyMetrics($pdo, $m['year'], $m['month']);
        $monthLabel = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));
    ?>
        <div class="financial-month-block" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?></h3>
                <span class="net-amount <?= ($metrics['net_profit'] >= 0) ? 'profit' : 'loss' ?>">
                    R<?= number_format($metrics['net_profit'], 2) ?>
                </span>
            </div>

            <!-- INCOME -->
            <div class="metric-row"><span>Uber Income:</span>    <span>R<?= number_format($metrics['uber_income'],    2) ?></span></div>
            <div class="metric-row"><span>Bookings:</span>       <span>R<?= number_format($metrics['booking_income'], 2) ?></span></div>
            <div class="metric-row"><span>Total Income:</span>   <strong>R<?= number_format($metrics['total_income'],  2) ?></strong></div>

            <!-- UBER PAYOUT -->
            <div class="metric-row"><span>Uber Cash Received:</span> <span>R<?= number_format($metrics['uber_cash'],    2) ?></span></div>
            <div class="metric-row"><span>Uber Payout:</span>        <strong>R<?= number_format($metrics['uber_payout'], 2) ?></strong></div>

            <!-- EXPENSES -->
            <div class="metric-row"><span>Fuel Cost:</span>            <span>R<?= number_format($metrics['fuel_cost'],             2) ?></span></div>
            <div class="metric-row"><span>Car Rental:</span>           <span>R<?= number_format($metrics['car_rental'],            2) ?></span></div>
            <div class="metric-row"><span>Vehicle Costs:</span>        <span>R<?= number_format($metrics['uber_additional_costs'], 2) ?></span></div>
            <div class="metric-row"><span>Total Expenses:</span>       <strong>R<?= number_format($metrics['total_expenses'],       2) ?></strong></div>

            <!-- KPIs -->
            <div class="metric-row"><span>Booking Trips:</span> <span><?= (int) $metrics['booking_trips'] ?></span></div>
            <div class="metric-row"><span>Uber Trips:</span>    <span><?= (int) $metrics['uber_trips']    ?></span></div>
            <div class="metric-row"><span>Total Trips:</span>   <strong><?= (int) $metrics['total_trips']  ?></strong></div>
            <div class="metric-row"><span>Total km:</span>      <span><?= number_format($metrics['total_trip_km'], 1) ?> km</span></div>
            <div class="metric-row"><span>Income / Trip:</span> <span>R<?= number_format($metrics['income_per_trip'], 2) ?></span></div>
            <div class="metric-row"><span>Cost / km:</span>     <span>R<?= number_format($metrics['cost_per_km'],    2) ?></span></div>
            <div class="metric-row"><span>Income / km:</span>   <span>R<?= number_format($metrics['income_per_km'],  2) ?></span></div>
            <div class="metric-row"><span>Fuel Cost / km:</span><span>R<?= number_format($metrics['fuel_cost_per_km'], 2) ?></span></div>
            <div class="metric-row"><span>Fuel Efficiency:</span><span><?= number_format($metrics['fuel_km_per_l'], 2) ?> km/l (<?= number_format($metrics['fuel_l_per_100km'], 2) ?> l/100km)</span></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function formatSA(dateObj) {
        if (isNaN(dateObj.getTime())) return '??/??/????';
        const day   = String(dateObj.getDate()).padStart(2, '0');
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const year  = dateObj.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function fmtR(val) {
        return 'R' + parseFloat(val || 0).toFixed(2);
    }

    function buildWeekBlock(week) {
        const start       = new Date(week.monday * 1000);
        const end         = new Date(week.sunday * 1000);
        const profitClass = parseFloat(week.net_profit) >= 0 ? 'profit' : 'loss';
        const inProgressBadge = week.in_progress
            ? '<span class="week-in-progress">⏳ In Progress</span>'
            : '';

        return `
            <div class="weekly-block${week.in_progress ? ' week-in-progress-block' : ''}">
                <div class="week-header">
                    <strong>Week: ${formatSA(start)} – ${formatSA(end)} ${inProgressBadge}</strong>
                    <span class="net-amount ${profitClass}">${fmtR(week.net_profit)}</span>
                </div>

                <div class="metric-row"><span>Uber Income:</span>    <span>${fmtR(week.uber_income)}</span></div>
                <div class="metric-row"><span>Bookings:</span>       <span>${fmtR(week.booking_income)}</span></div>
                <div class="metric-row"><span>Total Income:</span>   <strong>${fmtR(week.total_income)}</strong></div>

                <div class="metric-row"><span>Uber Cash Received:</span> <span>${fmtR(week.uber_cash)}</span></div>
                <div class="metric-row"><span>Uber Payout:</span>        <strong>${fmtR(week.uber_payout)}</strong></div>

                <div class="metric-row"><span>Fuel Cost:</span>            <span>${fmtR(week.fuel_cost)}</span></div>
                <div class="metric-row"><span>Car Rental:</span>           <span>${fmtR(week.car_rental)}</span></div>
                <div class="metric-row"><span>Vehicle Costs:</span>        <span>${fmtR(week.uber_additional_costs)}</span></div>
                <div class="metric-row"><span>Total Expenses:</span>       <strong>${fmtR(week.total_expenses)}</strong></div>

                <div class="metric-row"><span>Booking Trips:</span> <span>${week.booking_trips}</span></div>
                <div class="metric-row"><span>Uber Trips:</span>    <span>${week.uber_trips}</span></div>
                <div class="metric-row"><span>Total Trips:</span>   <strong>${week.total_trips}</strong></div>
                <div class="metric-row"><span>Total km:</span>      <span>${parseFloat(week.total_trip_km || 0).toFixed(1)} km</span></div>
                <div class="metric-row"><span>Income / Trip:</span> <span>${fmtR(week.income_per_trip)}</span></div>
                <div class="metric-row"><span>Cost / km:</span>     <span>${fmtR(week.cost_per_km)}</span></div>
                <div class="metric-row"><span>Income / km:</span>   <span>${fmtR(week.income_per_km)}</span></div>
                <div class="metric-row"><span>Fuel Cost / km:</span><span>${fmtR(week.fuel_cost_per_km)}</span></div>
                <div class="metric-row"><span>Fuel Efficiency:</span><span>${parseFloat(week.fuel_km_per_l || 0).toFixed(2)} km/l (${parseFloat(week.fuel_l_per_100km || 0).toFixed(2)} l/100km)</span></div>
            </div>
        `;
    }

    $(document).ready(function () {
        let currentlyOpenContainer = null;

        $(document).on('click', '.toggle-weeks-btn', function () {
            const button    = $(this);
            const year      = button.data('year');
            const month     = button.data('month');
            const container = button.next('.weeks-container');

            // Close any other open container
            if (currentlyOpenContainer && currentlyOpenContainer !== container[0]) {
                $(currentlyOpenContainer).addClass('hidden').empty();
                $(currentlyOpenContainer).prev('.toggle-weeks-btn').text('🔽 View Weeks');
            }

            if (container.hasClass('hidden')) {
                if (container.is(':empty')) {
                    container.html('<div style="text-align:center; padding:10px;">Loading weeks…</div>');
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Financials/api/index.php',
                        method: 'GET',
                        data: { action: 'get_weeks', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success && response.weeks.length > 0) {
                                response.weeks.forEach(function (week) {
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