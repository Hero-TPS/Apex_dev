<?php

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/financials/helper.php';

$page_title = 'Financial Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Financials';

include ROOT_DIR . '/includes/header.php';

$monthsBack = (int)getSystemVariable($pdo, 'financial_months_back', 3);
if ($monthsBack < 1) $monthsBack = 3;

$months = [];
$today = new DateTime();
for ($i = 0; $i < $monthsBack; $i++) {
    $date = clone $today;
    $date->modify("-$i months");
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n');
    $months[] = ['year' => $year, 'month' => $month];
}
usort($months, function($a, $b) {
    if ($a['year'] !== $b['year']) return $b['year'] - $a['year'];
    return $b['month'] - $a['month'];
});
?>

<div class="financial-dashboard">
    <h2>📅 Financial Summary (Last <?= $monthsBack ?> Months)</h2>
    
    <?php foreach ($months as $m): 
        $metrics = getMonthlyMetrics($pdo, $m['year'], $m['month']);
        $monthLabel = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));
    ?>
        <div class="financial-month-block" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?></h3>
                <span class="net-amount <?= ($metrics['net_profit'] >= 0) ? 'profit' : 'loss' ?>">
                    R<?= number_format($metrics['net_profit'], 2) ?>
                </span>
            </div>

            <!-- FULL METRICS — IDENTICAL TO WEEKLY -->
            <div class="metric-row"><span>Uber:</span> <span>R<?= number_format($metrics['uber_income'], 2) ?></span></div>
            <div class="metric-row"><span>Bookings:</span> <span>R<?= number_format($metrics['booking_income'], 2) ?></span></div>
            <div class="metric-row"><span>Total Income:</span> <strong>R<?= number_format($metrics['total_income'], 2) ?></strong></div>
            
            <div class="metric-row"><span>Uber Cash:</span> <span>R<?= number_format($metrics['uber_cash'], 2) ?></span></div>
            <div class="metric-row"><span>Uber Payout:</span> <strong>R<?= number_format($metrics['uber_payout'], 2) ?></strong></div>
            
            <div class="metric-row"><span>Fuel:</span> <span>R<?= number_format($metrics['fuel_cost'], 2) ?></span></div>
            <div class="metric-row"><span>Car Rental:</span> <span>R<?= number_format($metrics['car_rental'], 2) ?></span></div>
            <div class="metric-row"><span>Mobile Data:</span> <span>R<?= number_format($metrics['mobile_data_cost'], 2) ?></span></div>
            <div class="metric-row"><span>Total Expenses:</span> <strong>R<?= number_format($metrics['total_expenses'], 2) ?></strong></div>
            
            <div class="metric-row"><span>Booking Trips:</span> <span><?= $metrics['booking_trips'] ?></span></div>
            <div class="metric-row"><span>Uber Trips:</span> <span><?= $metrics['uber_trips'] ?></span></div>
            <div class="metric-row"><span>Total Trips:</span> <strong><?= $metrics['total_trips'] ?></strong></div>
            <div class="metric-row"><span>Income/Trip:</span> <span>R<?= number_format($metrics['income_per_trip'], 2) ?></span></div>
            <div class="metric-row"><span>Cost/km:</span> <span>R<?= number_format($metrics['cost_per_km'], 2) ?></span></div>
            <div class="metric-row"><span>Income/km:</span> <span>R<?= number_format($metrics['income_per_km'], 2) ?></span></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Format date as d/m/Y (South African standard)
function formatSA(dateObj) {
    if (isNaN(dateObj.getTime())) return '??/??/????';
    const day = String(dateObj.getDate()).padStart(2, '0');
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const year = dateObj.getFullYear();
    return `${day}/${month}/${year}`;
}

$(document).ready(function() {
    let currentlyOpenContainer = null;

    $(document).on('click', '.toggle-weeks-btn', function() {
        const button = $(this);
        const year = button.data('year');
        const month = button.data('month');
        const container = button.next('.weeks-container');

        if (currentlyOpenContainer && currentlyOpenContainer !== container[0]) {
            $(currentlyOpenContainer).addClass('hidden').empty();
            $(currentlyOpenContainer)
                .prev('.toggle-weeks-btn')
                .text('🔽 View Weeks');
        }

        if (container.hasClass('hidden')) {
            if (container.is(':empty')) {
                container.html('<div style="text-align:center; padding:10px;">Loading weeks...</div>');
                $.ajax({
                    url: '<?= BASE_URL ?>/financials/api.php?action=get_weeks',
                    method: 'GET',
                    // ✅ 'data' is explicitly present
                    data: { year: year, month: month },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            container.empty();
                            response.weeks.forEach(week => {
                                const start = new Date(week.monday * 1000);
                                const end = new Date(week.sunday * 1000);
                                const startStr = formatSA(start);
                                const endStr = formatSA(end);
                                
                                const html = `
                                    <div class="weekly-block">
                                        <div class="week-header">
                                            <strong>Week: ${startStr} – ${endStr}</strong>
                                            <span class="net-amount ${week.net_profit >= 0 ? 'profit' : 'loss'}">
                                                R${parseFloat(week.net_profit).toFixed(2)}
                                            </span>
                                        </div>
                                        <div class="metric-row"><span>Uber:</span> <span>R${parseFloat(week.uber_income).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Bookings:</span> <span>R${parseFloat(week.booking_income).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Total Income:</span> <strong>R${parseFloat(week.total_income).toFixed(2)}</strong></div>
                                        
                                        <div class="metric-row"><span>Uber Cash:</span> <span>R${parseFloat(week.uber_cash).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Uber Payout:</span> <strong>R${parseFloat(week.uber_payout).toFixed(2)}</strong></div>
                                        
                                        <div class="metric-row"><span>Fuel:</span> <span>R${parseFloat(week.fuel_cost).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Car Rental:</span> <span>R${parseFloat(week.car_rental).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Mobile Data:</span> <span>R${parseFloat(week.mobile_data_cost).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Total Expenses:</span> <strong>R${parseFloat(week.total_expenses).toFixed(2)}</strong></div>
                                        
                                        <div class="metric-row"><span>Booking Trips:</span> <span>${week.booking_trips}</span></div>
                                        <div class="metric-row"><span>Uber Trips:</span> <span>${week.uber_trips}</span></div>
                                        <div class="metric-row"><span>Total Trips:</span> <strong>${week.total_trips}</strong></div>
                                        <div class="metric-row"><span>Income/Trip:</span> <span>R${parseFloat(week.income_per_trip).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Cost/km:</span> <span>R${parseFloat(week.cost_per_km).toFixed(2)}</span></div>
                                        <div class="metric-row"><span>Income/km:</span> <span>R${parseFloat(week.income_per_km).toFixed(2)}</span></div>
                                </div>
                                `;
                                container.append(html);
                            });
                        } else {
                            container.html('<div class="error-message">⚠️ ' + (response.message || 'Failed to load weeks') + '</div>');
                        }
                    },
                    error: function(xhr, status, err) {
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

<?php include '../includes/footer.php'; ?>