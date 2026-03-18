<?php
$page_title      = 'Balance Sheet';
$page_subtitle   = 'Monthly Balance Sheet Report';
$show_breadcrumb = true;
$breadcrumb      = ' > Financials > Balance Sheet';

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Financials/helper.php';

$monthsBack = (int) getSystemVariable($pdo, 'financial_months_back');
if ($monthsBack < 1) {
    $monthsBack = 3;
}

$months = [];
$today  = new DateTime();
for ($i = 1; $i < $monthsBack; $i++) {
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

$tz = new DateTimeZone(TIME_ZONE);

$carRentalWeekly = (float) getSystemVariable($pdo, 'car_rental_price');

// === PERIOD SUMMARY (aggregated across all displayed months) ===
$summaryBookings      = [];
$summaryUberEft       = 0.0;
$summaryUberCash      = 0.0;
$summaryFuel          = 0.0;
$summaryUberCosts     = [];
$summaryCarRental     = 0.0;
$summaryTotalIncome   = 0.0;
$summaryTotalExpenses = 0.0;
$summaryNetBalance    = 0.0;
$summaryPeriodLabel   = '';

if (!empty($months)) {
    $oldestMonth = $months[count($months) - 1];
    $newestMonth = $months[0];

    $summaryStart   = new DateTime("{$oldestMonth['year']}-{$oldestMonth['month']}-01", $tz);
    $summaryEnd     = new DateTime("{$newestMonth['year']}-{$newestMonth['month']}-01", $tz);
    $summaryEnd->modify('last day of this month');
    $summaryEndFull = clone $summaryEnd;
    $summaryEndFull->setTime(23, 59, 59);

    $summaryStartStr = $summaryStart->format('Y-m-d');
    $summaryEndStr   = $summaryEnd->format('Y-m-d');
    $summaryStartTs  = $summaryStart->getTimestamp();
    $summaryEndTs    = $summaryEndFull->getTimestamp();

    $summaryPeriodLabel = date('M Y', mktime(0, 0, 0, $oldestMonth['month'], 1, $oldestMonth['year']))
                        . ' – '
                        . date('M Y', mktime(0, 0, 0, $newestMonth['month'], 1, $newestMonth['year']));

    // Bookings grouped by payment method
    $stmt = $pdo->prepare(
        "SELECT UPPER(COALESCE(payment_method, 'CASH')) AS method, SUM(cost) AS total
         FROM bookings
         WHERE trip_date BETWEEN ? AND ?
         GROUP BY UPPER(COALESCE(payment_method, 'CASH'))
         ORDER BY method ASC"
    );
    $stmt->execute([$summaryStartStr, $summaryEndStr]);
    $summaryBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Uber income totals (EFT portion and cash portion)
    $stmt = $pdo->prepare(
        "SELECT SUM(total_income) AS total_income, SUM(cash_received) AS total_cash
         FROM uber_income
         WHERE week_start BETWEEN ? AND ?"
    );
    $stmt->execute([$summaryStartTs, $summaryEndTs]);
    $summaryUber     = $stmt->fetch(PDO::FETCH_ASSOC);
    $summaryUberEft  = max(0.0, (float) ($summaryUber['total_income'] ?? 0) - (float) ($summaryUber['total_cash'] ?? 0));
    $summaryUberCash = (float) ($summaryUber['total_cash'] ?? 0);

    // Fuel total
    $stmt = $pdo->prepare(
        "SELECT SUM(total_cost) AS total FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
    );
    $stmt->execute([$summaryStartTs, $summaryEndTs]);
    $summaryFuel = (float) ($stmt->fetchColumn() ?? 0);

    // Uber additional costs grouped by reason
    $stmt = $pdo->prepare(
        "SELECT COALESCE(uac.reason, 'Other') AS reason, SUM(uac.amount) AS total
         FROM uber_additional_costs uac
         JOIN uber_income ui ON uac.uber_income_id = ui.id
         WHERE ui.week_start BETWEEN ? AND ?
         GROUP BY COALESCE(uac.reason, 'Other')
         ORDER BY reason ASC"
    );
    $stmt->execute([$summaryStartTs, $summaryEndTs]);
    $summaryUberCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Car rental total (computed from weekly rate across all displayed months)
    foreach ($months as $sm) {
        $smFirst = new DateTime("{$sm['year']}-{$sm['month']}-01", $tz);
        if ($smFirst->format('N') !== '1') {
            $smFirst->modify('next monday');
        }
        $smLast = new DateTime("{$sm['year']}-{$sm['month']}-01", $tz);
        $smLast->modify('last day of this month');
        $smCurrent = clone $smFirst;
        while ($smCurrent <= $smLast) {
            $summaryCarRental += $carRentalWeekly;
            $smCurrent->modify('+1 week');
        }
    }

    $summaryTotalIncome   = array_sum(array_column($summaryBookings, 'total'))
                          + $summaryUberEft + $summaryUberCash;
    $summaryTotalExpenses = $summaryFuel
                          + array_sum(array_column($summaryUberCosts, 'total'))
                          + $summaryCarRental;
    $summaryNetBalance    = $summaryTotalIncome - $summaryTotalExpenses;
}

include ROOT_DIR . '/includes/header.php';
?>

<div class="balance-sheet-page">

    <div class="bs-top-bar no-print">
        <h2>📊 Monthly Balance Sheet</h2>
        <button onclick="window.print()" class="bs-print-btn">🖨️ Print / Save as PDF</button>
    </div>

    <?php if (!empty($months)): ?>
    <!-- ============ PERIOD SUMMARY ============ -->
    <div class="bs-summary-block">

        <div class="bs-summary-title">
            <div class="bs-month-name">📋 Period Summary</div>
            <div class="bs-summary-period"><?= htmlspecialchars($summaryPeriodLabel) ?></div>
        </div>

        <div class="bs-summary-tables">

            <!-- Income Summary -->
            <table class="bs-summary-table">
                <thead>
                    <tr>
                        <th colspan="2" class="bs-section-head bs-credit-head">INCOME SUMMARY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summaryBookings as $sb): ?>
                    <tr>
                        <td>Bookings (<?= htmlspecialchars($sb['method']) ?>)</td>
                        <td class="bs-amt"><?= number_format((float) $sb['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (round($summaryUberEft, 2) > 0): ?>
                    <tr>
                        <td>Uber Payouts (EFT)</td>
                        <td class="bs-amt"><?= number_format($summaryUberEft, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (round($summaryUberCash, 2) > 0): ?>
                    <tr>
                        <td>Uber Cash</td>
                        <td class="bs-amt"><?= number_format($summaryUberCash, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (empty($summaryBookings) && round($summaryUberEft, 2) <= 0 && round($summaryUberCash, 2) <= 0): ?>
                    <tr>
                        <td colspan="2" class="bs-empty">No income recorded for this period.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bs-total-row">
                        <td class="bs-total-label">TOTAL INCOME</td>
                        <td class="bs-amt bs-total-amt"><?= number_format($summaryTotalIncome, 2) ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Expense Summary -->
            <table class="bs-summary-table">
                <thead>
                    <tr>
                        <th colspan="2" class="bs-section-head bs-debit-head">EXPENSE SUMMARY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (round($summaryFuel, 2) > 0): ?>
                    <tr>
                        <td>Fuel Fill-ups</td>
                        <td class="bs-amt"><?= number_format($summaryFuel, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($summaryUberCosts as $suc): ?>
                    <tr>
                        <td>Uber Cost – <?= htmlspecialchars($suc['reason']) ?></td>
                        <td class="bs-amt"><?= number_format((float) $suc['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (round($summaryCarRental, 2) > 0): ?>
                    <tr>
                        <td>Car Rental</td>
                        <td class="bs-amt"><?= number_format($summaryCarRental, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (round($summaryFuel, 2) <= 0 && empty($summaryUberCosts) && round($summaryCarRental, 2) <= 0): ?>
                    <tr>
                        <td colspan="2" class="bs-empty">No expenses recorded for this period.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bs-total-row">
                        <td class="bs-total-label">TOTAL EXPENSES</td>
                        <td class="bs-amt bs-total-amt"><?= number_format($summaryTotalExpenses, 2) ?></td>
                    </tr>
                </tfoot>
            </table>

        </div><!-- .bs-summary-tables -->

        <div class="bs-net-summary <?= $summaryNetBalance >= 0 ? 'profit' : 'loss' ?>">
            <span class="bs-net-label">OVERALL NET BALANCE</span>
            <span class="bs-net-value">
                R <?= number_format(abs($summaryNetBalance), 2) ?>
                <?= $summaryNetBalance >= 0 ? 'CREDIT' : 'DEBIT' ?>
            </span>
        </div>

    </div><!-- .bs-summary-block -->
    <?php endif; ?>

    <?php foreach ($months as $idx => $m):

        $startDate    = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $endDate      = clone $startDate;
        $endDate->modify('last day of this month');

        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr   = $endDate->format('Y-m-d');

        $endDateFull  = clone $endDate;
        $endDateFull->setTime(23, 59, 59);

        $fuelStart    = $startDate->getTimestamp();
        $fuelEnd      = $endDateFull->getTimestamp();
        $uberStart    = $fuelStart;
        $uberEnd      = $fuelEnd;

        $monthLabel   = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));

        // === BOOKINGS ===
        $stmt = $pdo->prepare(
            "SELECT b.trip_date, b.start_time, b.cost, b.payment_method,
                    b.original_pickup, b.original_destination, c.name AS client_name
             FROM bookings b
             LEFT JOIN contacts c ON b.contact_id = c.id
             WHERE b.trip_date BETWEEN ? AND ?
             ORDER BY b.trip_date ASC, b.start_time ASC"
        );
        $stmt->execute([$startDateStr, $endDateStr]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // === UBER INCOME ===
        $stmt = $pdo->prepare(
            "SELECT week_start, week_end, total_income, cash_received
             FROM uber_income
             WHERE week_start BETWEEN ? AND ?
             ORDER BY week_start ASC"
        );
        $stmt->execute([$uberStart, $uberEnd]);
        $uberRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // === FUEL LOGS ===
        $stmt = $pdo->prepare(
            "SELECT log_timestamp, total_cost, payment_method
             FROM fuel_logs
             WHERE log_timestamp BETWEEN ? AND ?
             ORDER BY log_timestamp ASC"
        );
        $stmt->execute([$fuelStart, $fuelEnd]);
        $fuelRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // === UBER ADDITIONAL COSTS ===
        $stmt = $pdo->prepare(
            "SELECT uac.amount, uac.reason, ui.week_start
             FROM uber_additional_costs uac
             JOIN uber_income ui ON uac.uber_income_id = ui.id
             WHERE ui.week_start BETWEEN ? AND ?
             ORDER BY ui.week_start ASC, uac.id ASC"
        );
        $stmt->execute([$uberStart, $uberEnd]);
        $uberCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // === CAR RENTAL (weekly rate per billing week) ===
        $carRentalWeeks = [];

        $firstDay = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        if ($firstDay->format('N') !== '1') {
            $firstDay->modify('next monday');
        }
        $lastDay = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $lastDay->modify('last day of this month');

        $current = clone $firstDay;
        $weekNum = 1;
        while ($current <= $lastDay) {
            $carRentalWeeks[] = [
                'date'   => $current->format('d M Y'),
                'label'  => 'Car Rental – Week ' . $weekNum,
                'amount' => $carRentalWeekly,
            ];
            $current->modify('+1 week');
            $weekNum++;
        }

        // === TOTALS ===
        $totalCredits = 0.0;
        foreach ($bookings  as $b) { $totalCredits += (float) $b['cost'];         }
        foreach ($uberRows  as $u) { $totalCredits += (float) $u['total_income']; }

        $totalDebits = 0.0;
        foreach ($fuelRows       as $f)  { $totalDebits += (float) $f['total_cost']; }
        foreach ($uberCosts      as $uc) { $totalDebits += (float) $uc['amount'];    }
        foreach ($carRentalWeeks as $cr) { $totalDebits += $cr['amount'];            }

        $netBalance = $totalCredits - $totalDebits;
    ?>

    <div class="bs-month-block<?= ($idx > 0) ? ' bs-page-break' : '' ?>">

        <!-- Month Title Bar -->
        <div class="bs-month-title">
            <div class="bs-month-name"><?= htmlspecialchars($monthLabel) ?></div>
            <div class="bs-net <?= $netBalance >= 0 ? 'profit' : 'loss' ?>">
                Net: R <?= number_format(abs($netBalance), 2) ?> <?= $netBalance >= 0 ? '(Credit)' : '(Debit)' ?>
            </div>
        </div>

        <!-- ============ CREDITS TABLE ============ -->
        <table class="bs-table">
            <thead>
                <tr>
                    <th colspan="4" class="bs-section-head bs-credit-head">CREDITS — MONEY IN</th>
                </tr>
                <tr class="bs-col-head">
                    <th class="bs-col-date">Date</th>
                    <th class="bs-col-desc">Description</th>
                    <th class="bs-col-method">Method</th>
                    <th class="bs-col-amt">Amount (R)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b):
                    $method = strtoupper($b['payment_method'] ?? 'cash');
                    $desc   = ($b['client_name'] ?? 'Unknown')
                              . ' — '
                              . ($b['original_pickup'] ?? '')
                              . ' → '
                              . ($b['original_destination'] ?? '');
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($b['trip_date']))) ?></td>
                    <td><?= htmlspecialchars($desc) ?></td>
                    <td class="bs-method <?= $method === 'EFT' ? 'bs-eft' : 'bs-cash' ?>"><?= $method ?></td>
                    <td class="bs-amt"><?= number_format((float) $b['cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($uberRows as $u):
                    $wStart  = new DateTime('@' . $u['week_start']);
                    $wStart->setTimezone($tz);
                    $wEnd    = new DateTime('@' . $u['week_end']);
                    $wEnd->setTimezone($tz);
                    $wLabel  = $wStart->format('d M') . ' – ' . $wEnd->format('d M Y');
                    $eftAmt  = (float) $u['total_income'] - (float) $u['cash_received'];
                    $cashAmt = (float) $u['cash_received'];
                ?>
                <?php if ($eftAmt > 0.001): ?>
                <tr>
                    <td><?= htmlspecialchars($wStart->format('d M Y')) ?></td>
                    <td>Uber Payout – <?= htmlspecialchars($wLabel) ?></td>
                    <td class="bs-method bs-eft">EFT</td>
                    <td class="bs-amt"><?= number_format($eftAmt, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($cashAmt > 0.001): ?>
                <tr>
                    <td><?= htmlspecialchars($wStart->format('d M Y')) ?></td>
                    <td>Uber Cash – <?= htmlspecialchars($wLabel) ?></td>
                    <td class="bs-method bs-cash">CASH</td>
                    <td class="bs-amt"><?= number_format($cashAmt, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty($bookings) && empty($uberRows)): ?>
                <tr>
                    <td colspan="4" class="bs-empty">No income recorded for this month.</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bs-total-row">
                    <td colspan="3" class="bs-total-label">TOTAL CREDITS</td>
                    <td class="bs-amt bs-total-amt"><?= number_format($totalCredits, 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- ============ DEBITS TABLE ============ -->
        <table class="bs-table">
            <thead>
                <tr>
                    <th colspan="4" class="bs-section-head bs-debit-head">DEBITS — MONEY OUT</th>
                </tr>
                <tr class="bs-col-head">
                    <th class="bs-col-date">Date</th>
                    <th class="bs-col-desc">Description</th>
                    <th class="bs-col-method">Method</th>
                    <th class="bs-col-amt">Amount (R)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fuelRows as $f):
                    $fDate   = new DateTime('@' . $f['log_timestamp']);
                    $fDate->setTimezone($tz);
                    $fMethod = strtoupper($f['payment_method'] ?? 'cash');
                ?>
                <tr>
                    <td><?= htmlspecialchars($fDate->format('d M Y')) ?></td>
                    <td>Fuel Fill-up</td>
                    <td class="bs-method <?= $fMethod === 'EFT' ? 'bs-eft' : 'bs-cash' ?>"><?= $fMethod ?></td>
                    <td class="bs-amt"><?= number_format((float) $f['total_cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($uberCosts as $uc):
                    $cDate = new DateTime('@' . $uc['week_start']);
                    $cDate->setTimezone($tz);
                ?>
                <tr>
                    <td><?= htmlspecialchars($cDate->format('d M Y')) ?></td>
                    <td>Uber Cost – <?= htmlspecialchars($uc['reason'] ?? '') ?></td>
                    <td class="bs-method">—</td>
                    <td class="bs-amt"><?= number_format((float) $uc['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($carRentalWeeks as $cr): ?>
                <tr>
                    <td><?= htmlspecialchars($cr['date']) ?></td>
                    <td><?= htmlspecialchars($cr['label']) ?></td>
                    <td class="bs-method">—</td>
                    <td class="bs-amt"><?= number_format($cr['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($fuelRows) && empty($uberCosts) && empty($carRentalWeeks)): ?>
                <tr>
                    <td colspan="4" class="bs-empty">No expenses recorded for this month.</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bs-total-row">
                    <td colspan="3" class="bs-total-label">TOTAL DEBITS</td>
                    <td class="bs-amt bs-total-amt"><?= number_format($totalDebits, 2) ?></td>
                </tr>
            </tfoot>
        </table>

        <!-- Net Balance Summary -->
        <div class="bs-net-summary <?= $netBalance >= 0 ? 'profit' : 'loss' ?>">
            <span class="bs-net-label">NET BALANCE</span>
            <span class="bs-net-value">
                R <?= number_format(abs($netBalance), 2) ?>
                <?= $netBalance >= 0 ? 'CREDIT' : 'DEBIT' ?>
            </span>
        </div>

    </div><!-- .bs-month-block -->

    <?php endforeach; ?>

</div><!-- .balance-sheet-page -->

<?php include ROOT_DIR . '/includes/footer.php'; ?>
