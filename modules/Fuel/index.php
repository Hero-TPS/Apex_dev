<?php
$page_title = 'Fuel Reports';
$page_subtitle = 'Monthly Summary';
$show_breadcrumb = true;
$breadcrumb = ' > Fuel';

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';

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

include ROOT_DIR . '/includes/header.php';
?>

<div class="financial-dashboard">
    <h2>⛽ Fuel Reports (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <?php foreach ($months as $m):
        $tz        = new DateTimeZone(TIME_ZONE);
        $startDate = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $endDate   = clone $startDate;
        $endDate->modify('last day of this month');
        $endDate->setTime(23, 59, 59);

        $stmt = $pdo->prepare(
            "SELECT COALESCE(COUNT(*), 0) AS fill_count,
                    COALESCE(SUM(trip_km), 0) AS total_km,
                    COALESCE(SUM(total_cost), 0) AS total_cost,
                    COALESCE(SUM(total_cost / NULLIF(fuel_price, 0)), 0) AS total_liters
             FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
        );
        $stmt->execute([$startDate->getTimestamp(), $endDate->getTimestamp()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalKm     = (float) $row['total_km'];
        $totalCost   = (float) $row['total_cost'];
        $totalLiters = (float) $row['total_liters'];
        $costPerKm   = ($totalKm     > 0) ? ($totalCost   / $totalKm)          : 0.0;
        $kmPerL      = ($totalLiters > 0) ? ($totalKm     / $totalLiters)       : 0.0;
        $lPer100Km   = ($totalKm     > 0) ? ($totalLiters / $totalKm * 100)     : 0.0;

        $monthLabel = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));
    ?>
        <div class="financial-month-block" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?></h3>
                <span class="net-amount loss">R<?= number_format($totalCost, 2) ?></span>
            </div>

            <div class="metric-row"><span>Fill-ups:</span>    <strong><?= (int) $row['fill_count'] ?></strong></div>
            <div class="metric-row"><span>Total km:</span>    <strong><?= number_format($totalKm, 1) ?> km</strong></div>
            <div class="metric-row"><span>Total Cost:</span>  <strong>R<?= number_format($totalCost, 2) ?></strong></div>
            <div class="metric-row"><span>Cost / km:</span>   <strong>R<?= number_format($costPerKm, 2) ?></strong></div>
            <div class="metric-row"><span>Efficiency:</span>  <strong><?= number_format($kmPerL, 2) ?> km/l (<?= number_format($lPer100Km, 2) ?> l/100km)</strong></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ===== FUEL LOG ===== -->
<div class="content" style="margin-top: 2rem;">
    <h2>⛽ Fuel Log</h2>
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
    <div id="fuel-log-footer" style="text-align:center; margin-top: 0.75rem; display:none;">
        <button id="show-all-logs-btn" class="page-action-btn toggle">Show All Logs</button>
    </div>
</div>

<script>
    function buildWeekBlock(week) {
        const totalKm   = parseFloat(week.total_km   || 0);
        const totalCost = parseFloat(week.total_cost || 0);
        const costPerKm = parseFloat(week.cost_per_km || 0);
        const kmPerL    = parseFloat(week.km_per_l    || 0);
        const lPer100Km = parseFloat(week.l_per_100km || 0);
        return `
            <div class="weekly-block">
                <div class="week-header">
                    <strong>Week: ${week.week_label}</strong>
                    <span class="net-amount loss">R${totalCost.toFixed(2)}</span>
                </div>
                <div class="metric-row"><span>Fill-ups:</span>   <strong>${week.fill_count}</strong></div>
                <div class="metric-row"><span>Total km:</span>   <strong>${totalKm.toFixed(1)} km</strong></div>
                <div class="metric-row"><span>Total Cost:</span> <strong>R${totalCost.toFixed(2)}</strong></div>
                <div class="metric-row"><span>Cost / km:</span>  <strong>R${costPerKm.toFixed(2)}</strong></div>
                <div class="metric-row"><span>Efficiency:</span> <strong>${kmPerL.toFixed(2)} km/l (${lPer100Km.toFixed(2)} l/100km)</strong></div>
            </div>
        `;
    }

    $(document).ready(function () {
        const PREVIEW_LIMIT = 10;
        let allLogs = [];
        let currentlyOpenContainer = null;

        // ===== Month/Week toggle =====
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
                        url: '<?= BASE_URL ?>/modules/Fuel/api/index.php',
                        method: 'GET',
                        data: { action: 'weekly_fuel_by_month', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success && response.data.length > 0) {
                                response.data.forEach(function (week) {
                                    container.append(buildWeekBlock(week));
                                });
                            } else if (response.success) {
                                container.html('<div class="error-message">No data found for this month.</div>');
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

        // ===== Fuel Log =====
        $.ajax({
            url: '<?= BASE_URL ?>/modules/Fuel/api/index.php?action=get_all',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    allLogs = response.data;
                    renderLogs(allLogs.slice(0, PREVIEW_LIMIT));
                    if (allLogs.length > PREVIEW_LIMIT) {
                        $('#fuel-log-footer').show();
                    }
                } else {
                    $('#fuel-report-body').html('<tr><td colspan="7" class="error-message">No fuel logs found.</td></tr>');
                }
            },
            error: function () {
                $('#fuel-report-body').html('<tr><td colspan="7" class="error-message">Failed to load fuel logs.</td></tr>');
            }
        });

        $('#show-all-logs-btn').on('click', function () {
            renderLogs(allLogs);
            $('#fuel-log-footer').hide();
        });

        function renderLogs(logs) {
            const body = $('#fuel-report-body');
            body.empty();
            logs.forEach(log => {
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
        }

        $(document).on('click', '.delete-btn', function () {
            if (!confirm('Delete this fuel log?')) return;
            const id = $(this).data('id');

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Fuel/api/index.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        allLogs = allLogs.filter(l => l.id != id);
                        $('tr[data-log-id="' + id + '"]').fadeOut(function () {
                            $(this).remove();
                            if ($('#fuel-report-body tr').length === 0) {
                                $('#fuel-report-body').html('<tr><td colspan="7" class="error-message">No fuel logs found.</td></tr>');
                                $('#fuel-log-footer').hide();
                            }
                        });
                        showNotification('✓ ' + res.message, 'success');
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to delete fuel log', 'error');
                }
            });
        });

        function showNotification(message, type) {
            const className = type === 'success' ? 'success-message' : 'error-message';
            const notification = $('<div class="' + className + '">' + message + '</div>');
            $('.financial-dashboard').before(notification);
            setTimeout(function () {
                notification.fadeOut(function () { $(this).remove(); });
            }, 5000);
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>