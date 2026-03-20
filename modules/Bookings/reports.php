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
    ?>
        <div class="financial-month-block" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?></h3>
                <span class="net-amount profit">R<?= number_format($row['total_income'], 2) ?></span>
            </div>

            <div class="metric-row"><span>Total Bookings:</span> <strong><?= (int) $row['booking_count'] ?></strong></div>
            <div class="metric-row"><span>Total Income:</span>   <strong>R<?= number_format($row['total_income'], 2) ?></strong></div>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function buildWeekBlock(week) {
        return `
            <div class="weekly-block">
                <div class="week-header">
                    <strong>Week: ${week.week_label}</strong>
                    <span class="net-amount profit">R${parseFloat(week.total_income || 0).toFixed(2)}</span>
                </div>
                <div class="metric-row"><span>Bookings:</span>     <strong>${week.booking_count}</strong></div>
                <div class="metric-row"><span>Total Income:</span> <strong>R${parseFloat(week.total_income || 0).toFixed(2)}</strong></div>
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