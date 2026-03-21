<?php
$page_title      = 'Balance Sheet';
$page_subtitle   = 'Monthly Balance Sheet Report';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Financials/helper.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Financials', 'url' => BASE_URL . '/modules/Financials/'],
    ['label' => 'Balance Sheet'],
]);

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

include ROOT_DIR . '/includes/header.php';
?>

<div class="balance-sheet-page">

    <div class="bs-top-bar no-print">
        <h2>📊 Monthly Balance Sheet</h2>
        <button onclick="window.print()" class="bs-print-btn">🖨️ Print / Save as PDF</button>
    </div>

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

        // === MONTHLY SUMMARY (grouped totals for this month) ===
        $mSummaryBookings = [];
        foreach ($bookings as $b) {
            $mMethod = strtoupper($b['payment_method'] ?? 'CASH');
            $mSummaryBookings[$mMethod] = ($mSummaryBookings[$mMethod] ?? 0.0) + (float) $b['cost'];
        }
        ksort($mSummaryBookings);

        $mSummaryUberEft  = 0.0;
        $mSummaryUberCash = 0.0;
        foreach ($uberRows as $u) {
            $mSummaryUberEft  += max(0.0, (float) $u['total_income'] - (float) $u['cash_received']);
            $mSummaryUberCash += (float) $u['cash_received'];
        }

        $mSummaryFuel = 0.0;
        foreach ($fuelRows as $f) {
            $mSummaryFuel += (float) $f['total_cost'];
        }

        $mSummaryUberCosts = [];
        foreach ($uberCosts as $uc) {
            $reason = $uc['reason'] ?? 'Other';
            $mSummaryUberCosts[$reason] = ($mSummaryUberCosts[$reason] ?? 0.0) + (float) $uc['amount'];
        }
        ksort($mSummaryUberCosts);

        $mSummaryCarRental = count($carRentalWeeks) * $carRentalWeekly;

        $mSummaryTotalIncome   = array_sum($mSummaryBookings) + $mSummaryUberEft + $mSummaryUberCash;
        $mSummaryTotalExpenses = $mSummaryFuel + array_sum($mSummaryUberCosts) + $mSummaryCarRental;
    ?>

    <div class="bs-month-block<?= ($idx > 0) ? ' bs-page-break' : '' ?>">

        <!-- Month Title Bar -->
        <div class="bs-month-title">
            <div class="bs-month-name"><?= htmlspecialchars($monthLabel) ?></div>
            <div class="bs-net <?= $netBalance >= 0 ? 'profit' : 'loss' ?>">
                Net: R <?= number_format(abs($netBalance), 2) ?> <?= $netBalance >= 0 ? '(Credit)' : '(Debit)' ?>
            </div>
        </div>

        <!-- ============ MONTHLY SUMMARY ============ -->
        <div class="bs-summary-tables">

            <!-- Income Summary -->
            <table class="bs-summary-table">
                <thead>
                    <tr>
                        <th colspan="2" class="bs-section-head bs-credit-head">INCOME SUMMARY</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mSummaryBookings as $mMethod => $mTotal): ?>
                    <tr>
                        <td>Bookings (<?= htmlspecialchars($mMethod) ?>)</td>
                        <td class="bs-amt"><?= number_format($mTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (round($mSummaryUberEft, 2) > 0): ?>
                    <tr>
                        <td>Uber Payouts (EFT)</td>
                        <td class="bs-amt"><?= number_format($mSummaryUberEft, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (round($mSummaryUberCash, 2) > 0): ?>
                    <tr>
                        <td>Uber Cash</td>
                        <td class="bs-amt"><?= number_format($mSummaryUberCash, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (empty($mSummaryBookings) && round($mSummaryUberEft, 2) <= 0 && round($mSummaryUberCash, 2) <= 0): ?>
                    <tr>
                        <td colspan="2" class="bs-empty">No income recorded for this month.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bs-total-row">
                        <td class="bs-total-label">TOTAL INCOME</td>
                        <td class="bs-amt bs-total-amt"><?= number_format($mSummaryTotalIncome, 2) ?></td>
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
                    <?php if (round($mSummaryFuel, 2) > 0): ?>
                    <tr>
                        <td>Fuel Fill-ups</td>
                        <td class="bs-amt"><?= number_format($mSummaryFuel, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($mSummaryUberCosts as $mReason => $mCostTotal): ?>
                    <tr>
                        <td>Vehicle Cost – <?= htmlspecialchars($mReason) ?></td>
                        <td class="bs-amt"><?= number_format($mCostTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (round($mSummaryCarRental, 2) > 0): ?>
                    <tr>
                        <td>Car Rental</td>
                        <td class="bs-amt"><?= number_format($mSummaryCarRental, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (round($mSummaryFuel, 2) <= 0 && empty($mSummaryUberCosts) && round($mSummaryCarRental, 2) <= 0): ?>
                    <tr>
                        <td colspan="2" class="bs-empty">No expenses recorded for this month.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bs-total-row">
                        <td class="bs-total-label">TOTAL EXPENSES</td>
                        <td class="bs-amt bs-total-amt"><?= number_format($mSummaryTotalExpenses, 2) ?></td>
                    </tr>
                </tfoot>
            </table>

        </div><!-- .bs-summary-tables -->

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
        <div class="bs-page-break">
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
                    <td>Vehicle Cost – <?= htmlspecialchars($uc['reason'] ?? '') ?></td>
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
        </div><!-- .bs-page-break -->
        
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
