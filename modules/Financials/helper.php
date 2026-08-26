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
 * Calculate km driven in a period using odometer delta.
 *
 * Returns the difference between the last odo_km on or before $endUnix
 * and the last odo_km strictly before $startUnix.
 * Returns 0.0 if insufficient data exists.
 *
 * This avoids the trip_km boundary problem where a fill-up's trip_km
 * spans across a week/month boundary and distorts efficiency metrics
 * (km/l, l/100km, cost/km, income/km).
 */
function getOdoKmForPeriod(PDO $pdo, int $startUnix, int $endUnix): float
{
    // Odometer at end of period (last fill-up on or before period end)
    $stmt = $pdo->prepare(
        "SELECT odo_km FROM fuel_logs WHERE log_timestamp <= ? ORDER BY log_timestamp DESC LIMIT 1"
    );
    $stmt->execute([$endUnix]);
    $endOdo = $stmt->fetchColumn();

    if ($endOdo === false) {
        return 0.0; // No fuel data at all
    }

    // Odometer just before start of period (last fill-up before period start)
    $stmt = $pdo->prepare(
        "SELECT odo_km FROM fuel_logs WHERE log_timestamp < ? ORDER BY log_timestamp DESC LIMIT 1"
    );
    $stmt->execute([$startUnix]);
    $startOdo = $stmt->fetchColumn();

    if ($startOdo === false) {
        $startOdo = 0.0; // No prior entry — assume starting from zero
    }

    $delta = (float) $endOdo - (float) $startOdo;
    return $delta > 0.0 ? $delta : 0.0;
}

/**
 * Get financial metrics for a single week (Mon–Sun, SAST).
 *
 * @param  PDO $pdo
 * @param  int $startUnix  Unix timestamp of Monday 00:00 SAST
 * @param  int $endUnix    Unix timestamp of Sunday 23:59:59 SAST
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
        "SELECT COALESCE(SUM(COALESCE(booking_fee, cost)), 0) AS income, COUNT(*) AS trips
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

    // === FUEL: cost and litres by fill-up timestamp (actual spend this week) ===
    $fuelEndObj = clone $endDateObj;
    $fuelEndObj->setTime(23, 59, 59);
    $fuelStart = $startDateObj->getTimestamp();
    $fuelEnd   = $fuelEndObj->getTimestamp();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_cost), 0) AS cost,
                COALESCE(SUM(total_cost / NULLIF(fuel_price, 0)), 0) AS liters
         FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
    );
    $stmt->execute([$fuelStart, $fuelEnd]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $fuelCost   = (float) $row['cost'];
    $fuelLiters = (float) $row['liters'];

    // === KM driven: odometer delta (avoids trip_km boundary distortion) ===
    $totalTripKm = getOdoKmForPeriod($pdo, $fuelStart, $fuelEnd);

    // === CAR RENTAL (rate as it stood this week — historical lookup) ===
    $carRental = (float) getHistoricalVariable($pdo, 'car_rental_price', $endDateStr);

    // === CALCULATIONS ===
    $totalIncome    = $bookingIncome + $uberIncome;
    $totalTrips     = $bookingTrips + $uberTrips;
    $totalExpenses  = $fuelCost + $carRental + $uberAdditionalCosts;
    $netProfit      = $totalIncome - $totalExpenses;
    $uberPayout     = $uberIncome - $uberCash - $carRental;
    $incomePerTrip  = ($totalTrips  > 0) ? ($totalIncome   / $totalTrips)  : 0.0;
    $costPerKm      = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm    = ($totalTripKm > 0) ? ($totalIncome   / $totalTripKm) : 0.0;
    $fuelCostPerKm  = ($totalTripKm > 0) ? ($fuelCost      / $totalTripKm) : 0.0;
    $fuelKmPerL     = ($fuelLiters  > 0) ? ($totalTripKm   / $fuelLiters)  : 0.0;
    $fuelL100Km     = ($totalTripKm > 0) ? ($fuelLiters    / $totalTripKm * 100) : 0.0;

    return [
        'uber_income'           => $uberIncome,
        'booking_income'        => $bookingIncome,
        'total_income'          => $totalIncome,
        'uber_cash'             => $uberCash,
        'uber_payout'           => $uberPayout,
        'uber_trips'            => $uberTrips,
        'uber_additional_costs' => $uberAdditionalCosts,
        'fuel_cost'             => $fuelCost,
        'fuel_liters'           => $fuelLiters,
        'car_rental'            => $carRental,
        'total_expenses'        => $totalExpenses,
        'booking_trips'         => $bookingTrips,
        'total_trips'           => $totalTrips,
        'total_trip_km'         => $totalTripKm,
        'income_per_trip'       => $incomePerTrip,
        'cost_per_km'           => $costPerKm,
        'net_profit'            => $netProfit,
        'income_per_km'         => $incomePerKm,
        'fuel_cost_per_km'      => $fuelCostPerKm,
        'fuel_km_per_l'         => $fuelKmPerL,
        'fuel_l_per_100km'      => $fuelL100Km,
    ];
}

/**
 * Get financial metrics for an entire calendar month (SAST).
 *
 * Fuel cost and litres are summed across billing weeks (Mon–Sun) whose Monday
 * falls within this month, using the full week range (no month-end cap), so
 * fill-ups in the next calendar month that belong to a billing week starting
 * this month are correctly attributed here.
 *
 * km driven (total_trip_km) is derived from the odometer delta between the
 * start and end of the calendar month, completely avoiding the trip_km
 * boundary distortion where a fill-up's trip_km spans a month boundary.
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
        "SELECT COALESCE(SUM(COALESCE(booking_fee, cost)), 0) AS income, COUNT(*) AS trips
         FROM bookings WHERE trip_date BETWEEN ? AND ?"
    );
    $stmt->execute([$startDateStr, $endDateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $bookingIncome = (float) $row['income'];
    $bookingTrips  = (int)   $row['trips'];

    // === UBER income (all weeks whose Monday falls within this month) ===
    $startUnix = $startDate->getTimestamp();
    $endUnix   = (clone $endDate)->setTime(23, 59, 59)->getTimestamp();

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

    // === FUEL and CAR RENTAL: summed across billing weeks whose Monday falls
    //     in this month. Fuel uses the full Mon–Sun range per week (no
    //     month-end cap) so fill-ups in the next calendar month that belong
    //     to a billing week starting this month are correctly attributed
    //     here. Car rental is resolved per-week (not rate × week-count) so
    //     that a rate change partway through the month prices each week at
    //     whatever was actually in effect for it.
    $fuelCost   = 0.0;
    $fuelLiters = 0.0;
    $carRental  = 0.0;

    $fuelWeekCurrent = new DateTime("$year-$month-01", $tz);
    if ($fuelWeekCurrent->format('N') !== '1') {
        $fuelWeekCurrent->modify('next monday');
    }
    $fuelLastDay = clone $endDate;

    while ($fuelWeekCurrent <= $fuelLastDay) {
        $wMonday = clone $fuelWeekCurrent;
        $wMonday->setTime(0, 0, 0);

        $wSunday = clone $wMonday;
        $wSunday->modify('+6 days');
        $wSunday->setTime(23, 59, 59);

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_cost), 0) AS cost,
                    COALESCE(SUM(total_cost / NULLIF(fuel_price, 0)), 0) AS liters
             FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
        );
        $stmt->execute([$wMonday->getTimestamp(), $wSunday->getTimestamp()]);
        $fRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $fuelCost   += (float) $fRow['cost'];
        $fuelLiters += (float) $fRow['liters'];

        $carRental += (float) getHistoricalVariable($pdo, 'car_rental_price', $wSunday->format('Y-m-d'));

        $fuelWeekCurrent->modify('+1 week');
    }

    // === KM driven: odometer delta for the full calendar month.
    //     Uses the last odo reading on/before month end minus the last odo
    //     reading before month start — unaffected by fill-up timing.
    $totalTripKm = getOdoKmForPeriod($pdo, $startUnix, $endUnix);

    // === CALCULATIONS ===
    $totalIncome    = $bookingIncome + $uberIncome;
    $totalTrips     = $bookingTrips + $uberTrips;
    $totalExpenses  = $fuelCost + $carRental + $uberAdditionalCosts;
    $netProfit      = $totalIncome - $totalExpenses;
    $uberPayout     = $uberIncome - $uberCash - $carRental;
    $incomePerTrip  = ($totalTrips  > 0) ? ($totalIncome   / $totalTrips)  : 0.0;
    $costPerKm      = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm    = ($totalTripKm > 0) ? ($totalIncome   / $totalTripKm) : 0.0;
    $fuelCostPerKm  = ($totalTripKm > 0) ? ($fuelCost      / $totalTripKm) : 0.0;
    $fuelKmPerL     = ($fuelLiters  > 0) ? ($totalTripKm   / $fuelLiters)  : 0.0;
    $fuelL100Km     = ($totalTripKm > 0) ? ($fuelLiters    / $totalTripKm * 100) : 0.0;

    return [
        'uber_income'           => $uberIncome,
        'booking_income'        => $bookingIncome,
        'total_income'          => $totalIncome,
        'uber_cash'             => $uberCash,
        'uber_payout'           => $uberPayout,
        'uber_trips'            => $uberTrips,
        'uber_additional_costs' => $uberAdditionalCosts,
        'fuel_cost'             => $fuelCost,
        'fuel_liters'           => $fuelLiters,
        'car_rental'            => $carRental,
        'total_expenses'        => $totalExpenses,
        'booking_trips'         => $bookingTrips,
        'total_trips'           => $totalTrips,
        'total_trip_km'         => $totalTripKm,
        'income_per_trip'       => $incomePerTrip,
        'cost_per_km'           => $costPerKm,
        'net_profit'            => $netProfit,
        'income_per_km'         => $incomePerKm,
        'fuel_cost_per_km'      => $fuelCostPerKm,
        'fuel_km_per_l'         => $fuelKmPerL,
        'fuel_l_per_100km'      => $fuelL100Km,
    ];
}

/**
 * Return an array of weekly metric snapshots for drill-down within a month.
 * Calls getWeeklyMetrics() which already uses odometer delta for km.
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

    $today   = new DateTime('now', $tz);
    $current = clone $firstDay;
    while ($current <= $lastDay) {
        $monday = clone $current;
        $monday->setTime(0, 0, 0);

        // Use the full billing week (Mon–Sun) — no month-end cap — so that
        // metrics cover all days that belong to this week, including any tail
        // days that fall in the next calendar month.
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $sunday->setTime(23, 59, 59);

        $startUnix = $monday->getTimestamp();
        $endUnix   = $sunday->getTimestamp();

        $weekData                   = getWeeklyMetrics($pdo, $startUnix, $endUnix);
        $weekData['monday']         = $startUnix;
        $weekData['sunday']         = $endUnix;
        $weekData['display_sunday'] = $endUnix;
        $weekData['in_progress']    = ($sunday > $today);
        $weeks[]                    = $weekData;

        $current->modify('+1 week');
    }

    return $weeks;
}
