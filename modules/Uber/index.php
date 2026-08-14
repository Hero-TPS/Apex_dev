<?php
$page_title = 'Uber Reports';
$page_subtitle = 'Monthly Summary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Uber']]);

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

include ROOT_DIR . '/includes/header.php';

$carRentalPrice = (float) getSystemVariable($pdo, 'car_rental_price');
?>

<div class="financial-dashboard">
    <h2>🚗 Uber Reports (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <?php foreach ($months as $m):
        $startDate = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $endDate   = clone $startDate;
        $endDate->modify('last day of this month');

        $startUnix = $startDate->getTimestamp();
        $endUnix   = $endDate->getTimestamp();

        // === INCOME TOTALS ===
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_income), 0)     AS total_income,
                    COALESCE(SUM(cash_received), 0)     AS cash_received,
                    COALESCE(SUM(total_trips), 0)       AS total_trips,
                    COALESCE(SUM(total_time_online), 0) AS total_time_online,
                    COALESCE(SUM(shortfall_paid), 0)    AS shortfall_paid,
                    COUNT(*)                            AS week_count
             FROM uber_income
             WHERE week_start BETWEEN ? AND ?"
        );
        $stmt->execute([$startUnix, $endUnix]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // === ADDITIONAL COSTS (separate to avoid row multiplication) ===
        $costStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(uac.amount), 0) AS additional_costs
             FROM uber_additional_costs uac
             JOIN uber_income ui ON uac.uber_income_id = ui.id
             WHERE ui.week_start BETWEEN ? AND ?"
        );
        $costStmt->execute([$startUnix, $endUnix]);
        $row['additional_costs'] = (float) $costStmt->fetchColumn();

        // Fines + Vehicle Repairs only, for the Total Net calc — matches
        // calculateUberWeekFinancials() in helper.php
        $shortfallCostStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(uac.amount), 0) AS amount
             FROM uber_additional_costs uac
             JOIN uber_income ui ON uac.uber_income_id = ui.id
             WHERE ui.week_start BETWEEN ? AND ?
             AND uac.reason IN ('Fines', 'Vehicle Repairs')"
        );
        $shortfallCostStmt->execute([$startUnix, $endUnix]);
        $finesAndRepairs = (float) $shortfallCostStmt->fetchColumn();

        $cardIncome = $row['total_income'] - $row['cash_received'];
        $totalNet   = $cardIncome - ($carRentalPrice * $row['week_count']) - $finesAndRepairs;
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

            <div class="metric-row"><span>Total Income:</span>      <strong>R<?= number_format($row['total_income'], 2) ?></strong></div>
            <div class="metric-row"><span>Cash Received:</span>     <span>R<?= number_format($row['cash_received'], 2) ?></span></div>
            <div class="metric-row"><span>Card Income:</span>        <span>R<?= number_format($cardIncome, 2) ?></span></div>
            <div class="metric-row"><span>Total Trips:</span>        <strong><?= (int) $row['total_trips'] ?></strong></div>
            <div class="metric-row"><span>Time Online:</span>        <span><?= number_format($row['total_time_online'], 1) ?> hrs</span></div>
            <div class="metric-row"><span>Additional Costs:</span>   <span>R<?= number_format($row['additional_costs'], 2) ?></span></div>
            <div class="metric-row"><span>Total Net:</span>          <strong class="net-amount <?= $totalNet >= 0 ? 'profit' : 'loss' ?>">R<?= number_format($totalNet, 2) ?></strong></div>
            <div class="metric-row"><span>Total Paid In:</span>      <span>R<?= number_format($row['shortfall_paid'], 2) ?></span></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function buildWeekBlock(log) {
        let costsHtml = '—';
        if (log.additional_costs && log.additional_costs.length > 0) {
            costsHtml = log.additional_costs.map(c =>
                `${c.reason}: R ${parseFloat(c.amount).toFixed(2)}`
            ).join('<br>');
        }
        const cardIncome = (parseFloat(log.total_income) - parseFloat(log.cash_received)).toFixed(2);
        const inProgressBadge = log.in_progress
            ? '<span class="week-in-progress">⏳ In Progress</span>'
            : '';

        return `
            <div class="weekly-block${log.in_progress ? ' week-in-progress-block' : ''}">
                <div class="week-header">
                    <strong>Week: ${log.week_display} ${inProgressBadge}</strong>
                    <span class="net-amount profit">R${parseFloat(log.total_income || 0).toFixed(2)}</span>
                </div>
                <div class="metric-row"><span>Total Income:</span>    <strong>R${parseFloat(log.total_income || 0).toFixed(2)}</strong></div>
                <div class="metric-row"><span>Cash Received:</span>   <span>R${parseFloat(log.cash_received || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Card Income:</span>     <span>R${cardIncome}</span></div>
                <div class="metric-row"><span>Total Trips:</span>     <strong>${log.total_trips}</strong></div>
                <div class="metric-row"><span>Time Online:</span>     <span>${parseFloat(log.total_time_online || 0).toFixed(1)} hrs</span></div>
                <div class="metric-row"><span>Additional Costs:</span><span>${costsHtml}</span></div>
                <div class="metric-row"><span>Car Rental:</span><span>R${parseFloat(log.financials.car_rental || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Fines:</span><span>R${parseFloat(log.financials.fines || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Vehicle Repairs:</span><span>R${parseFloat(log.financials.vehicle_repairs || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Net This Week:</span><strong class="net-amount ${parseFloat(log.financials.net || 0) >= 0 ? 'profit' : 'loss'}">R${parseFloat(log.financials.net || 0).toFixed(2)}</strong></div>
                <div class="metric-row"><span>Paid In:</span><span>R${parseFloat(log.financials.shortfall_paid || 0).toFixed(2)}</span></div>
                <div class="metric-row">
                    <span></span>
                    <span>
                        <a href="<?= BASE_URL ?>/modules/Uber/edit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
                        <button class="action-btn delete-btn" data-id="${log.id}">🗑️ Delete</button>
                    </span>
                </div>
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

            if (currentlyOpenContainer && currentlyOpenContainer !== container[0]) {
                $(currentlyOpenContainer).addClass('hidden').empty();
                $(currentlyOpenContainer).prev('.toggle-weeks-btn').text('🔽 View Weeks');
            }

            if (container.hasClass('hidden')) {
                if (container.is(':empty')) {
                    container.html('<div style="text-align:center; padding:10px;">Loading weeks…</div>');
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                        method: 'GET',
                        data: { action: 'get_by_month', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success && response.data.length > 0) {
                                response.data.forEach(function (log) {
                                    container.append(buildWeekBlock(log));
                                });
                            } else if (response.success) {
                                container.html('<div class="error-message">No Uber records found for this month.</div>');
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

        // Delete handler for Uber records inside weekly blocks
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
                        // Remove the weekly-block and reset its container so it reloads next time
                        const weekBlock = $('button[data-id="' + id + '"]').closest('.weekly-block');
                        const container = weekBlock.closest('.weeks-container');
                        weekBlock.fadeOut(function () {
                            $(this).remove();
                            if (container.find('.weekly-block').length === 0) {
                                container.html('<div class="error-message">No Uber records found for this month.</div>');
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
            $('.financial-dashboard').before(notification);
            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
