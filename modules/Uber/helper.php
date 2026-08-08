<?php
// modules/Uber/helper.php
//
// Rental shortfall / running balance ledger.
//
// Each week:
//   card_income = total_income - cash_received   (the portion Uber pays
//                 the rental company directly)
//   deductions  = car_rental + fines + vehicle_repairs
//   net         = card_income - deductions
//
// That net first settles whatever balance is already outstanding:
//   - if net clears the outstanding balance with some left over, the
//     rental company pays the excess out (balance floors at 0 — they are
//     not a bank, no running credit is carried forward)
//   - if net doesn't fully cover the outstanding balance, the balance
//     just shrinks by that amount
//   - if net itself is negative, the balance grows
//
// Any actual payment made (shortfall_paid) then further reduces the
// balance, whenever it happens - not necessarily "the week after".
//
// The balance is a genuine running total computed live by walking every
// week in order — nothing is stored per-week except the raw inputs
// (total_income, cash_received, shortfall_paid) and the Additional Costs
// rows (Fines / Vehicle Repairs). This makes it self-correcting: editing
// or backfilling any past week automatically recalculates every balance
// after it.
//
// IMPORTANT: This is Uber-module record-keeping only. It must NOT be
// pulled into Financials, Budgeting, or any other reporting module.

/**
 * Walk every uber_income record in week order and compute the running
 * balance ledger.
 *
 * @param PDO $pdo
 * @return array<int, array{
 *     id: int,
 *     week_start: string,
 *     week_end: string,
 *     card_income: float,
 *     car_rental: float,
 *     fines: float,
 *     vehicle_repairs: float,
 *     deductions: float,
 *     net: float,
 *     balance_before: float,
 *     paid_out: float,
 *     shortfall_paid: float,
 *     balance_after: float
 * }>  Keyed by uber_income.id, in week order.
 */
function calculateUberLedger(PDO $pdo): array
{
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    $stmt = $pdo->query("
        SELECT id, week_start, week_end, total_income, cash_received, shortfall_paid
        FROM uber_income
        ORDER BY week_start ASC, id ASC
    ");
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$weeks) {
        return [];
    }

    // Batch-fetch Fines and Vehicle Repairs for all weeks in one query,
    // rather than one query per week.
    $ids = array_column($weeks, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $costStmt = $pdo->prepare("
        SELECT uber_income_id, reason, amount
        FROM uber_additional_costs
        WHERE uber_income_id IN ($placeholders)
        AND reason IN ('Fines', 'Vehicle Repairs')
    ");
    $costStmt->execute($ids);

    $finesByWeek = [];
    $repairsByWeek = [];
    foreach ($costStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $wid = $row['uber_income_id'];
        if ($row['reason'] === 'Fines') {
            $finesByWeek[$wid] = ($finesByWeek[$wid] ?? 0.0) + (float) $row['amount'];
        } elseif ($row['reason'] === 'Vehicle Repairs') {
            $repairsByWeek[$wid] = ($repairsByWeek[$wid] ?? 0.0) + (float) $row['amount'];
        }
    }

    $balance = 0.0;
    $ledger = [];

    foreach ($weeks as $week) {
        $id = (int) $week['id'];

        $cardIncome = (float) $week['total_income'] - (float) $week['cash_received'];
        $fines      = $finesByWeek[$id] ?? 0.0;
        $repairs    = $repairsByWeek[$id] ?? 0.0;
        $deductions = $carRental + $fines + $repairs;
        $net        = $cardIncome - $deductions;

        $balanceBefore = $balance;

        // Net first settles the outstanding balance. Floors at 0 — any
        // excess is paid out to Quentin, never carried as a credit.
        $balanceAfterDeduction = max($balanceBefore - $net, 0.0);
        $paidOut = max($net - $balanceBefore, 0.0);

        $paid = (float) $week['shortfall_paid'];
        $balanceAfterPayment = max($balanceAfterDeduction - $paid, 0.0);

        $ledger[$id] = [
            'id'              => $id,
            'week_start'      => $week['week_start'],
            'week_end'        => $week['week_end'],
            'card_income'     => $cardIncome,
            'car_rental'      => $carRental,
            'fines'           => $fines,
            'vehicle_repairs' => $repairs,
            'deductions'      => $deductions,
            'net'             => $net,
            'balance_before'  => $balanceBefore,
            'paid_out'        => $paidOut,
            'shortfall_paid'  => $paid,
            'balance_after'   => $balanceAfterPayment,
        ];

        $balance = $balanceAfterPayment;
    }

    return $ledger;
}

/**
 * Get the computed ledger entry for a single uber_income record.
 *
 * @param PDO $pdo
 * @param int $recordId
 * @return array|null  Null if the record has no ledger entry (shouldn't
 *                      normally happen if the record exists).
 */
function getUberLedgerEntry(PDO $pdo, int $recordId): ?array
{
    $ledger = calculateUberLedger($pdo);
    return $ledger[$recordId] ?? null;
}

/**
 * Get the current outstanding balance — i.e. balance_after of the most
 * recently dated week on record. Used as an informational display when
 * logging a new week; 0 if there's no history yet.
 *
 * @param PDO $pdo
 * @return float
 */
function getCurrentUberBalance(PDO $pdo): float
{
    $ledger = calculateUberLedger($pdo);
    if (empty($ledger)) {
        return 0.0;
    }
    $last = end($ledger);
    return $last['balance_after'];
}

/**
 * Full ledger grouped by month, for the History Report.
 *
 * The balance itself is always computed from the FULL history (so it's
 * always accurate, no matter how far back the report window starts) —
 * only which months get returned/displayed is limited by $monthsBack.
 *
 * @param PDO $pdo
 * @param int $monthsBack  Number of months to include, most recent first.
 *                          Matches the `financial_months_back` system
 *                          variable used by other report pages.
 * @return array{
 *     months: array<int, array{
 *         label: string,
 *         year: int,
 *         month: int,
 *         weeks: array,
 *         totals: array{card_income: float, net: float, shortfall_paid: float, paid_out: float},
 *         balance_at_month_end: float
 *     }>,
 *     current_balance: float
 * }
 */
function getUberLedgerReport(PDO $pdo, int $monthsBack): array
{
    $ledger = calculateUberLedger($pdo);

    $tz = new DateTimeZone(TIME_ZONE);
    $windowStart = new DateTime('first day of this month', $tz);
    $windowStart->modify('-' . ($monthsBack - 1) . ' months');
    $windowStart->setTime(0, 0, 0);
    $windowStartTs = $windowStart->getTimestamp();

    $months = []; // keyed by "Y-n"

    foreach ($ledger as $entry) {
        if ((int) $entry['week_start'] < $windowStartTs) {
            continue;
        }

        $dt = new DateTime();
        $dt->setTimestamp((int) $entry['week_start']);
        $dt->setTimezone($tz);

        $key = $dt->format('Y-m');

        if (!isset($months[$key])) {
            $months[$key] = [
                'label'  => $dt->format('F Y'),
                'year'   => (int) $dt->format('Y'),
                'month'  => (int) $dt->format('n'),
                'weeks'  => [],
                'totals' => ['card_income' => 0.0, 'net' => 0.0, 'shortfall_paid' => 0.0, 'paid_out' => 0.0],
                'balance_at_month_end' => 0.0,
            ];
        }

        $endDt = clone $dt;
        $endDt->setTimestamp((int) $entry['week_end']);
        $endDt->setTimezone($tz);
        $entry['week_display'] = $dt->format('d M Y') . ' – ' . $endDt->format('d M Y');

        $months[$key]['weeks'][] = $entry;
        $months[$key]['totals']['card_income']    += $entry['card_income'];
        $months[$key]['totals']['net']             += $entry['net'];
        $months[$key]['totals']['shortfall_paid']  += $entry['shortfall_paid'];
        $months[$key]['totals']['paid_out']        += $entry['paid_out'];
        $months[$key]['balance_at_month_end'] = $entry['balance_after'];
    }

    // Most recent month first, matching other report pages
    krsort($months);

    return [
        'months'          => array_values($months),
        'current_balance' => empty($ledger) ? 0.0 : end($ledger)['balance_after'],
    ];
}
