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
 * Resolve the Sunday date (Y-m-d) for a week record — used as the
 * as-of date for historical rate lookups. Uber reporting treats each
 * week's Sunday as "the date this week's figures belong to."
 *
 * @param array $week  A row from uber_income (must include week_start,
 *                      a Unix timestamp for that week's Monday)
 */
function resolveUberWeekSunday(array $week): string
{
    $sunday = new DateTime();
    $sunday->setTimestamp((int) $week['week_start']);
    $sunday->setTimezone(new DateTimeZone(TIME_ZONE));
    $sunday->modify('+6 days');
    return $sunday->format('Y-m-d');
}

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
    $asOfDate  = resolveUberWeekSunday($record);
    $carRental = resolveCarRentalForWeek($pdo, $record['rental_override'] ?? null, $asOfDate);

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
        'car_rental'             => $carRental,
        'car_rental_is_override' => ($record['rental_override'] ?? null) !== null,
        'car_rental_override_at' => $record['rental_override_at'] ?? null,
        'fines'                  => $fines,
        'vehicle_repairs'        => $repairs,
        'card_income'            => $cardIncome,
        'deductions'             => $deductions,
        'net'                    => $net,
        'shortfall_paid'         => (float) ($record['shortfall_paid'] ?? 0),
    ];
}

/**
 * List vehicle repairs with how much has been paid off so far.
 *
 * Paid = the sum of Additional Cost rows linked to the repair through
 * uber_additional_costs.repair_id. It is calculated every time and never
 * stored, so editing or deleting a week corrects the balance by itself.
 *
 * Status: 'closed' (closed by hand), 'paid_off' (nothing left owing) or
 * 'open'. Payments above the total are allowed — remaining never goes
 * below zero.
 *
 * Needs migrations/20261114_1130_uber_vehicle_repairs.sql — throws a
 * PDOException if it has not been run yet.
 *
 * @param PDO  $pdo
 * @param bool $openOnly  Only repairs that are still open (not closed, still owing)
 * @return array<int, array<string, mixed>>  Oldest repair first. Each row has
 *     the table columns plus paid_amount, payment_count, remaining and status.
 */
function getUberRepairs(PDO $pdo, bool $openOnly = false): array
{
    $rows = $pdo->query("
        SELECT r.id, r.description, r.repair_date, r.total_amount,
               r.weekly_amount, r.is_closed, r.notes,
               (SELECT COALESCE(SUM(uac.amount), 0)
                  FROM uber_additional_costs uac
                 WHERE uac.repair_id = r.id) AS paid_amount,
               (SELECT COUNT(*)
                  FROM uber_additional_costs uac
                 WHERE uac.repair_id = r.id) AS payment_count
        FROM uber_vehicle_repairs r
        ORDER BY r.repair_date ASC, r.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $row['paid_amount']    = (float) $row['paid_amount'];
        $row['payment_count']  = (int) $row['payment_count'];
        $row['total_amount']   = (float) $row['total_amount'];
        $row['weekly_amount']  = $row['weekly_amount'] !== null ? (float) $row['weekly_amount'] : null;
        $row['remaining']      = max(0.0, round($row['total_amount'] - $row['paid_amount'], 2));

        if ((int) $row['is_closed'] === 1) {
            $row['status'] = 'closed';
        } elseif ($row['remaining'] <= 0.004) {
            $row['status'] = 'paid_off';
        } else {
            $row['status'] = 'open';
        }

        if ($openOnly && $row['status'] !== 'open') {
            continue;
        }
        $result[] = $row;
    }

    return $result;
}

/**
 * Every payment made towards a vehicle repair, grouped by repair.
 *
 * @param PDO $pdo
 * @return array<int, array<int, array{id: int, amount: float, week_display: string}>>
 *     Keyed by repair id, each list in week order.
 */
function getUberRepairPayments(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT uac.id, uac.repair_id, uac.amount, ui.week_start
        FROM uber_additional_costs uac
        JOIN uber_income ui ON uac.uber_income_id = ui.id
        WHERE uac.repair_id IS NOT NULL
        ORDER BY ui.week_start ASC, uac.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $tz     = new DateTimeZone(TIME_ZONE);
    $result = [];
    foreach ($rows as $row) {
        $start = new DateTime();
        $start->setTimestamp((int) $row['week_start']);
        $start->setTimezone($tz);
        $end = clone $start;
        $end->modify('+6 days');

        $result[(int) $row['repair_id']][] = [
            'id'           => (int) $row['id'],
            'amount'       => (float) $row['amount'],
            'week_display' => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
        ];
    }

    return $result;
}

/**
 * Repairs in the shape the weekly Add/Edit forms need for their repair
 * dropdown (all statuses — the forms decide which ones to offer).
 *
 * Returns an empty list, and logs, when the vehicle repairs table does not
 * exist yet, so the weekly forms keep working before the migration has run.
 *
 * @param PDO $pdo
 * @return array<int, array{id: int, description: string, status: string,
 *     total: float, paid: float, remaining: float, weekly: ?float}>
 */
function getUberRepairOptions(PDO $pdo): array
{
    try {
        $options = [];
        foreach (getUberRepairs($pdo) as $repair) {
            $options[] = [
                'id'          => (int) $repair['id'],
                'description' => $repair['description'],
                'status'      => $repair['status'],
                'total'       => $repair['total_amount'],
                'paid'        => $repair['paid_amount'],
                'remaining'   => $repair['remaining'],
                'weekly'      => $repair['weekly_amount'],
            ];
        }
        return $options;
    } catch (PDOException $e) {
        logError('UBER', 'Could not load vehicle repairs for the weekly form', ['error' => $e->getMessage()]);
        return [];
    }
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
    $stmt = $pdo->query("
        SELECT id, week_start, total_income, cash_received, shortfall_paid,
               balance_override, balance_override_at, rental_override
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
        $asOfDate   = resolveUberWeekSunday($week);
        $carRental  = resolveCarRentalForWeek($pdo, $week['rental_override'] ?? null, $asOfDate);
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
