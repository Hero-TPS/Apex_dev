<?php
// modules/Uber/helper.php
//
// Rental shortfall record-keeping.
//
// Uber income is paid directly to the car rental company, who deduct the
// weekly car rental fee and any fines before passing on the balance. When
// income doesn't cover rental + fines, Quentin pays the difference in
// directly. This helper calculates that shortfall and tracks any unpaid
// balance carried forward week to week.
//
// IMPORTANT: These figures are for record-keeping within the Uber module
// only. They must NOT be pulled into Financials, Budgeting, or any other
// reporting module — car_rental_price is already used there as a fixed
// weekly cost, and layering the actual shortfall payments on top would
// double-count and skew those results.

/**
 * Calculate the rental shortfall for a single uber_income record.
 *
 * @param PDO   $pdo
 * @param array $record  A row from uber_income (must include id, total_income,
 *                        shortfall_carried_in, shortfall_paid)
 * @return array{
 *     car_rental: float,
 *     fines: float,
 *     shortfall_due: float,
 *     carried_in: float,
 *     total_owed: float,
 *     shortfall_paid: float,
 *     carried_out: float
 * }
 */
function calculateUberShortfall(PDO $pdo, array $record): array
{
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    // Fines are whatever has already been logged under Additional Costs
    // with reason 'Fines' for this week — no separate fines field, to
    // avoid double entry.
    $finesStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM uber_additional_costs WHERE uber_income_id = ? AND reason = 'Fines'"
    );
    $finesStmt->execute([$record['id'] ?? 0]);
    $fines = (float) $finesStmt->fetchColumn();

    $totalIncome = (float) ($record['total_income'] ?? 0);
    $carriedIn   = (float) ($record['shortfall_carried_in'] ?? 0);
    $paid        = (float) ($record['shortfall_paid'] ?? 0);

    $shortfallDue = max($carRental + $fines - $totalIncome, 0);
    $totalOwed    = $shortfallDue + $carriedIn;
    $carriedOut   = max($totalOwed - $paid, 0);

    return [
        'car_rental'     => $carRental,
        'fines'          => $fines,
        'shortfall_due'  => $shortfallDue,
        'carried_in'     => $carriedIn,
        'total_owed'     => $totalOwed,
        'shortfall_paid' => $paid,
        'carried_out'    => $carriedOut,
    ];
}

/**
 * Find the most recent uber_income record before the given week_start
 * timestamp and return its carried-forward (unpaid) balance. Used to
 * suggest the "Carried Over In" amount when logging a new week.
 *
 * @param PDO $pdo
 * @param int $weekStart  Unix timestamp of the Monday for the new week
 * @return float          0.0 if there is no prior record
 */
function getPreviousWeekCarryOut(PDO $pdo, int $weekStart): float
{
    $stmt = $pdo->prepare(
        "SELECT * FROM uber_income WHERE week_start < ? ORDER BY week_start DESC LIMIT 1"
    );
    $stmt->execute([$weekStart]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prev) {
        return 0.0;
    }

    $calc = calculateUberShortfall($pdo, $prev);
    return $calc['carried_o
ut'];
}
