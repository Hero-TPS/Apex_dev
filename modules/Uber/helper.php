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
