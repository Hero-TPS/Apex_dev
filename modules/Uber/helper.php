<?php
// modules/Uber/helper.php
//
// Rental shortfall record-keeping — per-week, independent of history.
//
// Each week:
//   card_income = total_income - cash_received   (the portion Uber pays
//                 the rental company directly)
//   deductions  = car_rental + fines + vehicle_repairs
//   net         = card_income - deductions
//
// No running balance is tracked — each week stands on its own. Amount
// Paid In is still recorded (shortfall_paid) for your own reference, but
// nothing carries forward or accumulates across weeks.
//
// IMPORTANT: This is Uber-module record-keeping only. It must NOT be
// pulled into Financials, Budgeting, or any other reporting module.

/**
 * Compute the rental shortfall figures for a single uber_income record.
 *
 * @param PDO   $pdo
 * @param array $record  A row from uber_income (must include id,
 *                        total_income, cash_received, shortfall_paid)
 * @return array{
 *     car_rental: float,
 *     fines: float,
 *     vehicle_repairs: float,
 *     card_income: float,
 *     deductions: float,
 *     net: float,
 *     shortfall_paid: float
 * }
 */
function calculateUberWeekFinancials(PDO $pdo, array $record): array
{
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    // Fines and Vehicle Repairs are whatever has been logged under
    // Additional Costs for this week with those exact reasons.
    $costStmt = $pdo->prepare("
        SELECT reason, amount
        FROM uber_additional_costs
        WHERE uber_income_id = ?
        AND reason IN ('Fines', 'Vehicle Repairs')
    ");
    $costStmt->execute([$record['id'] ?? 0]);

    $fines = 0.0;
    $repairs = 0.0;
    foreach ($costStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['reason'] === 'Fines') {
            $fines += (float) $row['amount'];
        } elseif ($row['reason'] === 'Vehicle Repairs') {
            $repairs += (float) $row['amount'];
        }
    }

    $cardIncome = (float) ($record['total_income'] ?? 0) - (float) ($record['cash_received'] ?? 0);
    $deductions = $carRental + $fines + $repairs;
    $net        = $cardIncome - $deductions;

    return [
        'car_rental'      => $carRental,
        'fines'           => $fines,
        'vehicle_repairs' => $repairs,
        'card_income'     => $cardIncome,
        'deductions'      => $deductions,
        'net'             => $net,
        'shortfall_paid'  => (float) ($record['shortfall_paid'] ?? 0),
    ];
}

/**
 * Walk every week in order and resolve the Balance shown for each one.
 *
 * Sign convention: POSITIVE = you're ahead (in credit with the rental
 * company), NEGATIVE = you're behind (you owe them).
 *
 * Balance is blank until the first manual correction ("seed") is set on
 * some week. From that week onward:
 *   - a week WITH its own correction shows that value, full stop —
 *     Net/Paid In for that week are ignored for balance purposes once a
 *     correction is set on it
 *   - a week WITHOUT one shows: previous week's resolved balance
 *     + this week's Net + this week's Paid In (a good week or a payment
 *     both move you further ahead / less behind)
 *
 * This intentionally does NOT touch total_income, cash_received, or any
 * other field — it only reads balance_override / balance_override_at,
 * and this module's own Net/Paid In figures.
 *
 * @param PDO $pdo
 * @return array<int, array{
 *     balance: float|null,
 *     is_override: bool,
 *     override_at: string|null
 * }>  Keyed by uber_income.id, in week order. balance is null for every
 *     week before the first correction is set.
 */
function calculateUberBalanceWalk(PDO $pdo): array
{
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    $stmt = $pdo->query("
        SELECT id, total_income, cash_received, shortfall_paid,
               balance_override, balance_override_at
        FROM uber_income
        ORDER BY week_start ASC, id ASC
    ");
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$weeks) {
        return [];
    }

    // Batch-fetch Fines and Vehicle Repairs for all weeks in one query.
    $ids = array_column($weeks, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $costStmt = $pdo->prepare("
        SELECT uber_income_id, reason, amount
        FROM uber_additional_costs
        WHERE uber_income_id IN ($placeholders)
        AND reason IN ('Fines', 'Vehicle Repairs')
    ");
    $costStmt->execute($ids);

    $costsByWeek = [];
    foreach ($costStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $costsByWeek[$row['uber_income_id']][$row['reason']]
            = ($costsByWeek[$row['uber_income_id']][$row['reason']] ?? 0.0) + (float) $row['amount'];
    }

    $seeded  = false;
    $current = null;
    $result  = [];

    foreach ($weeks as $week) {
        $id = (int) $week['id'];

        if ($week['balance_override'] !== null) {
            $seeded  = true;
            $current = (float) $week['balance_override'];
            $result[$id] = [
                'balance'     => $current,
                'is_override' => true,
                'override_at' => $week['balance_override_at'],
            ];
            continue;
        }

        if (!$seeded) {
            $result[$id] = ['balance' => null, 'is_override' => false, 'override_at' => null];
            continue;
        }

        $fines      = $costsByWeek[$id]['Fines'] ?? 0.0;
        $repairs    = $costsByWeek[$id]['Vehicle Repairs'] ?? 0.0;
        $cardIncome = (float) $week['total_income'] - (float) $week['cash_received'];
        $net        = $cardIncome - ($carRental + $fines + $repairs);
        $paid       = (float) $week['shortfall_paid'];

        $current = $current + $net + $paid;

        $result[$id] = [
            'balance'     => $current,
            'is_override' => false,
            'override_at' => null,
        ];
    }

    return $result;
}
