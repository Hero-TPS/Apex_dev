<?php

if (!defined('ROOT_DIR')) {
    die('Direct access not allowed');
}

/**
 * Count number of billing weeks (Mon–Sun) that start within a given month.
 */
function getWeeksInMonth(int $year, int $month): int
{
    $tz = new DateTimeZone(TIME_ZONE);
    $start = new DateTime("$year-$month-01", $tz);
    $end   = new DateTime("$year-$month-01", $tz);
    $end->modify('last day of this month');

    $mondays = 0;
    $current = clone $start;
    if ($current->format('N') !== '1') {
        $current->modify('next monday');
    }
    while ($current <= $end) {
        $mondays++;
        $current->modify('+1 week');
    }
    return $mondays;
}

/**
 * Get financial metrics for a single week (Mon–Sun, SAST).
 *
 * @param  PDO $pdo
 * @param  int $startUnix  Unix timestamp of Monday 00:00 SAST
 * @param  int $endUnix    Unix timestamp of Sunday 23:59:59 SAST (used for fuel range)
 * @return array
 */
function getWeeklyMetrics(PDO $pdo, int $startUnix, int $endUnix): array
{
    $tz = new DateTimeZone(TIME_ZONE);

    // Build SAST date strings for bookings (DATE column)
    $startDateObj = new DateTime('@' . $startUnix);
    $startDateObj->setTimezone($tz);
    $endDateObj = clone $startDateObj;
    $endDateObj->modify('+6 days');

    $startDateStr = $startDateObj->format('Y-m-d');
    $endDateStr   = $endDateObj->format('Y-m-d');

    // === BOOKINGS ===
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(cost), 0) AS income, COUNT(*) AS trips
         FROM bookings WHERE trip_date BETWEEN ? AND ?"
    );
    $stmt->execute([$startDateStr, $endDateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $bookingIncome = (float) $row['income'];
    $bookingTrips  = (int)   $row['trips'];

    // === UBER income ===
    $stmt = $pdo->prepare(
        "SELECT id, total_income, cash_received, total_trips
         FROM uber_income WHERE week_start = ?"
    );
    $stmt->execute([$startUnix]);
    $uber = $stmt->fetch(PDO::FETCH_ASSOC);

    $uberIncome   = (float) ($uber['total_income']  ?? 0);
    $uberCash     = (float) ($uber['cash_received'] ?? 0);
    $uberTrips    = (int)   ($uber['total_trips']   ?? 0);
    $uberIncomeId = $uber['id'] ?? null;

    // === UBER additional costs (car wash, tolls, etc.) ===
    $uberAdditionalCosts = 0.0;
    if ($uberIncomeId !== null) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM uber_additional_costs WHERE uber_income_id = ?"
        );
        $stmt->execute([$uberIncomeId]);
        $uberAdditionalCosts = (float) $stmt->fetchColumn();
    }

    // === FUEL: full SAST day range ===
    $fuelEndObj = clone $endDateObj;
    $fuelEndObj->setTime(23, 59, 59);
    $fuelStart = $startDateObj->getTimestamp();
    $fuelEnd   = $fuelEndObj->getTimestamp();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_cost), 0) AS cost, COALESCE(SUM(trip_km), 0) AS km
         FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
    );
    $stmt->execute([$fuelStart, $fuelEnd]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $fuelCost    = (float) $row['cost'];
    $totalTripKm = (float) $row['km'];

    // === CAR RENTAL (weekly rate from system variables) ===
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    // === CALCULATIONS ===
    $totalIncome    = $bookingIncome + $uberIncome;
    $totalTrips     = $bookingTrips + $uberTrips;
    $totalExpenses  = $fuelCost + $carRental + $uberAdditionalCosts;
    $netProfit      = $totalIncome - $totalExpenses;
    $uberPayout     = $uberIncome - $uberCash - $carRental;
    $incomePerTrip  = ($totalTrips  > 0) ? ($totalIncome   / $totalTrips)  : 0.0;
    $costPerKm      = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm    = ($totalTripKm > 0) ? ($totalIncome   / $totalTripKm) : 0.0;

    return [
        'uber_income'           => $uberIncome,
        'booking_income'        => $bookingIncome,
        'total_income'          => $totalIncome,
        'uber_cash'             => $uberCash,
        'uber_payout'           => $uberPayout,
        'uber_trips'            => $uberTrips,
        'uber_additional_costs' => $uberAdditionalCosts,
        'fuel_cost'             => $fuelCost,
        'car_rental'            => $carRental,
        'total_expenses'        => $totalExpenses,
        'booking_trips'         => $bookingTrips,
        'total_trips'           => $totalTrips,
        'total_trip_km'         => $totalTripKm,
        'income_per_trip'       => $incomePerTrip,
        'cost_per_km'           => $costPerKm,
        'net_profit'            => $netProfit,
        'income_per_km'         => $incomePerKm,
    ];
}

/**
 * Get financial metrics for an entire calendar month (SAST).
 */
function getMonthlyMetrics(PDO $pdo, int $year, int $month): array
{
    $tz = new DateTimeZone(TIME_ZONE);

    $startDate = new DateTime("$year-$month-01", $tz);
    $endDate   = clone $startDate;
    $endDate->modify('last day of this month');

    $startDateStr = $startDate->format('Y-m-d');
    $endDateStr   = $endDate->format('Y-m-d');

    // === BOOKINGS ===
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(cost), 0) AS income, COUNT(*) AS trips
         FROM bookings WHERE trip_date BETWEEN ? AND ?"
    );
    $stmt->execute([$startDateStr, $endDateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $bookingIncome = (float) $row['income'];
    $bookingTrips  = (int)   $row['trips'];

    // === UBER income (all weeks whose Monday falls within this month) ===
    $startUnix = $startDate->getTimestamp();
    $endUnix   = $endDate->getTimestamp();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_income), 0) AS income,
                COALESCE(SUM(cash_received), 0)  AS cash,
                COALESCE(SUM(total_trips),   0)  AS trips
         FROM uber_income WHERE week_start BETWEEN ? AND ?"
    );
    $stmt->execute([$startUnix, $endUnix]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $uberIncome = (float) $row['income'];
    $uberCash   = (float) $row['cash'];
    $uberTrips  = (int)   $row['trips'];

    // === UBER additional costs (car wash, tolls, etc.) ===
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(uac.amount), 0) AS total
         FROM uber_additional_costs uac
         JOIN uber_income ui ON uac.uber_income_id = ui.id
         WHERE ui.week_start BETWEEN ? AND ?"
    );
    $stmt->execute([$startUnix, $endUnix]);
    $uberAdditionalCosts = (float) $stmt->fetchColumn();

    // === FUEL: full SAST day range for the month ===
    $endDateFull = clone $endDate;
    $endDateFull->setTime(23, 59, 59);
    $fuelStart = $startDate->getTimestamp();
    $fuelEnd   = $endDateFull->getTimestamp();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_cost), 0) AS cost, COALESCE(SUM(trip_km), 0) AS km
         FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
    );
    $stmt->execute([$fuelStart, $fuelEnd]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $fuelCost    = (float) $row['cost'];
    $totalTripKm = (float) $row['km'];

    // === CAR RENTAL (weekly rate × number of billing weeks in month) ===
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price') * getWeeksInMonth($year, $month);

    // === CALCULATIONS ===
    $totalIncome    = $bookingIncome + $uberIncome;
    $totalTrips     = $bookingTrips + $uberTrips;
    $totalExpenses  = $fuelCost + $carRental + $uberAdditionalCosts;
    $netProfit      = $totalIncome - $totalExpenses;
    $uberPayout     = $uberIncome - $uberCash - $carRental;
    $incomePerTrip  = ($totalTrips  > 0) ? ($totalIncome   / $totalTrips)  : 0.0;
    $costPerKm      = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm    = ($totalTripKm > 0) ? ($totalIncome   / $totalTripKm) : 0.0;

    return [
        'uber_income'           => $uberIncome,
        'booking_income'        => $bookingIncome,
        'total_income'          => $totalIncome,
        'uber_cash'             => $uberCash,
        'uber_payout'           => $uberPayout,
        'uber_trips'            => $uberTrips,
        'uber_additional_costs' => $uberAdditionalCosts,
        'fuel_cost'             => $fuelCost,
        'car_rental'            => $carRental,
        'total_expenses'        => $totalExpenses,
        'booking_trips'         => $bookingTrips,
        'total_trips'           => $totalTrips,
        'total_trip_km'         => $totalTripKm,
        'income_per_trip'       => $incomePerTrip,
        'cost_per_km'           => $costPerKm,
        'net_profit'            => $netProfit,
        'income_per_km'         => $incomePerKm,
    ];
}

/**
 * Return an array of weekly metric snapshots for drill-down within a month.
 */
function getWeeklyBreakdownForMonth(PDO $pdo, int $year, int $month): array
{
    $tz   = new DateTimeZone(TIME_ZONE);
    $weeks = [];

    $firstDay = new DateTime("$year-$month-01", $tz);
    if ($firstDay->format('N') !== '1') {
        $firstDay->modify('next monday');
    }
    $lastDay = new DateTime("$year-$month-01", $tz);
    $lastDay->modify('last day of this month');

    $current = clone $firstDay;
    while ($current <= $lastDay) {
        $monday = clone $current;
        $monday->setTime(0, 0, 0);

        $sunday = clone $monday;
        $sunday->modify('+6 days');
        if ($sunday > $lastDay) {
            $sunday = clone $lastDay;
        }
        $sunday->setTime(23, 59, 59);

        $startUnix = $monday->getTimestamp();
        $endUnix   = $sunday->getTimestamp();

        $weekData            = getWeeklyMetrics($pdo, $startUnix, $endUnix);
        $weekData['monday']  = $startUnix;
        $weekData['sunday']  = $endUnix;
        $weeks[]             = $weekData;

        $current->modify('+1 week');
    }

    return $weeks;
}