<?php

if (!defined('ROOT_DIR')) {
    die('Direct access not allowed');
}

// Include core time utility
include ROOT_DIR . '/core/Time.php';

/**
 * Count number of Mondays (billing weeks) in a given month
 */
function getWeeksInMonth($year, $month)
{
    $tz = new DateTimeZone(Time::TIME_ZONE);
    $start = new DateTime("$year-$month-01", $tz);
    $end = new DateTime("$year-$month-01", $tz);
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
 * Get financial metrics for a single week (Mon–Sun, SAST)
 */
function getWeeklyMetrics($pdo, $startUnix, $endUnix)
{

    $dateRange = Time::weekIdToDateRange($startUnix);
    $unixRange = Time::weekIdToUnixRange($startUnix);

    // Get SAST date range for bookings (DATE column)
    $dateRange = Time::weekIdToDateRange($startUnix);
    $startDateStr = $dateRange['start_date'];
    $endDateStr = $dateRange['end_date'];

    // === BOOKINGS: use SAST date strings ===
    $stmt = $pdo->prepare("SELECT SUM(cost) AS income, COUNT(*) AS trips FROM bookings WHERE trip_date BETWEEN ? AND ?");
    $stmt->execute([$startDateStr, $endDateStr]);
    $row = $stmt->fetch();
    $bookingIncome = (float) ($row['income'] ?? 0);
    $bookingTrips = (int) ($row['trips'] ?? 0);

    // === UBER: match week_start (Monday 00:00 SAST Unix) ===
    $stmt = $pdo->prepare("SELECT total_income, cash_received, total_trips, mobile_data_cost FROM uber_income WHERE week_start = ?");
    $stmt->execute([$startUnix]);
    $uber = $stmt->fetch(PDO::FETCH_ASSOC);
    $uberIncome = (float) ($uber['total_income'] ?? 0);
    $uberCash = (float) ($uber['cash_received'] ?? 0);
    $uberTrips = (int) ($uber['total_trips'] ?? 0);
    $mobileDataCost = (float) ($uber['mobile_data_cost'] ?? 0);

    // === FUEL: align to full SAST days (00:00 Monday → 23:59 Sunday) ===
    $tz = new DateTimeZone(Time::TIME_ZONE);
    $startDateObj = new DateTime($dateRange['start_date'], $tz);
    $endDateObj = new DateTime($dateRange['end_date'], $tz);
    $endDateObj->setTime(23, 59, 59);

    $fuelStart = $startDateObj->getTimestamp(); // 2025-12-01 00:00:00 SAST
    $fuelEnd = $endDateObj->getTimestamp();     // 2025-12-07 23:59:59 SAST

    $stmt = $pdo->prepare("SELECT SUM(total_cost) AS cost, SUM(trip_km) AS km FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?");
    $stmt->execute([$fuelStart, $fuelEnd]);
    $row = $stmt->fetch();
    $fuelCost = (float) ($row['cost'] ?? 0);
    $totalTripKm = (float) ($row['km'] ?? 0);

    // === CAR RENTAL ===
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price');

    // === CALCULATIONS ===
    $totalIncome = $bookingIncome + $uberIncome;
    $totalTrips = $bookingTrips + $uberTrips;
    $totalExpenses = $fuelCost + $mobileDataCost + $carRental;
    $netProfit = $totalIncome - $totalExpenses;
    $uberPayout = $uberIncome - $uberCash - $carRental;
    $incomePerTrip = ($totalTrips > 0) ? ($totalIncome / $totalTrips) : 0.0;
    $costPerKm = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm = ($totalTripKm > 0) ? ($totalIncome / $totalTripKm) : 0.0;

    return [
        'uber_income' => $uberIncome,
        'booking_income' => $bookingIncome,
        'total_income' => $totalIncome,
        'uber_cash' => $uberCash,
        'uber_payout' => $uberPayout,
        'uber_trips' => $uberTrips,
        'fuel_cost' => $fuelCost,
        'car_rental' => $carRental,
        'mobile_data_cost' => $mobileDataCost,
        'total_expenses' => $totalExpenses,
        'booking_trips' => $bookingTrips,
        'total_trips' => $totalTrips,
        'total_trip_km' => $totalTripKm,
        'income_per_trip' => $incomePerTrip,
        'cost_per_km' => $costPerKm,
        'net_profit' => $netProfit,
        'income_per_km' => $incomePerKm
    ];
}

/**
 * Get financial metrics for an entire month (SAST)
 */
function getMonthlyMetrics($pdo, $year, $month)
{
    $tz = new DateTimeZone(Time::TIME_ZONE);
    $startDate = new DateTime("$year-$month-01", $tz);
    $endDate = clone $startDate;
    $endDate->modify('last day of this month');
    $startDateStr = $startDate->format('Y-m-d');
    $endDateStr = $endDate->format('Y-m-d');

    // === BOOKINGS ===
    $stmt = $pdo->prepare("SELECT SUM(cost) AS income, COUNT(*) AS trips FROM bookings WHERE trip_date BETWEEN ? AND ?");
    $stmt->execute([$startDateStr, $endDateStr]);
    $row = $stmt->fetch();
    $bookingIncome = (float) ($row['income'] ?? 0);
    $bookingTrips = (int) ($row['trips'] ?? 0);

    // === UBER ===
    $startUnix = $startDate->getTimestamp();
    $endUnix = $endDate->getTimestamp();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_income), 0) AS income, COALESCE(SUM(cash_received), 0) AS cash, COALESCE(SUM(total_trips), 0) AS trips, COALESCE(SUM(mobile_data_cost), 0) AS data FROM uber_income WHERE week_start BETWEEN ? AND ?");
    $stmt->execute([$startUnix, $endUnix]);
    $row = $stmt->fetch();
    $uberIncome = (float) ($row['income'] ?? 0);
    $uberCash = (float) ($row['cash'] ?? 0);
    $uberTrips = (int) ($row['trips'] ?? 0);
    $mobileDataCost = (float) ($row['data'] ?? 0);

    // === FUEL: align to full SAST days ===
    $endDateFull = clone $endDate;
    $endDateFull->setTime(23, 59, 59);
    $fuelStart = $startDate->getTimestamp();
    $fuelEnd = $endDateFull->getTimestamp();

    $stmt = $pdo->prepare("SELECT SUM(total_cost) AS cost, SUM(trip_km) AS km FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?");
    $stmt->execute([$fuelStart, $fuelEnd]);
    $row = $stmt->fetch();
    $fuelCost = (float) ($row['cost'] ?? 0);
    $totalTripKm = (float) ($row['km'] ?? 0);

    // === CAR RENTAL ===
    $carRental = (float) getSystemVariable($pdo, 'car_rental_price') * getWeeksInMonth($year, $month);

    // === CALCULATIONS ===
    $totalIncome = $bookingIncome + $uberIncome;
    $totalTrips = $bookingTrips + $uberTrips;
    $totalExpenses = $fuelCost + $mobileDataCost + $carRental;
    $netProfit = $totalIncome - $totalExpenses;
    $uberPayout = $uberIncome - $uberCash - $carRental;
    $incomePerTrip = ($totalTrips > 0) ? ($totalIncome / $totalTrips) : 0.0;
    $costPerKm = ($totalTripKm > 0) ? ($totalExpenses / $totalTripKm) : 0.0;
    $incomePerKm = ($totalTripKm > 0) ? ($totalIncome / $totalTripKm) : 0.0;

    return [
        'uber_income' => $uberIncome,
        'booking_income' => $bookingIncome,
        'total_income' => $totalIncome,
        'uber_cash' => $uberCash,
        'uber_payout' => $uberPayout,
        'uber_trips' => $uberTrips,
        'fuel_cost' => $fuelCost,
        'car_rental' => $carRental,
        'mobile_data_cost' => $mobileDataCost,
        'total_expenses' => $totalExpenses,
        'booking_trips' => $bookingTrips,
        'total_trips' => $totalTrips,
        'total_trip_km' => $totalTripKm,
        'income_per_trip' => $incomePerTrip,
        'cost_per_km' => $costPerKm,
        'net_profit' => $netProfit,
        'income_per_km' => $incomePerKm
    ];
}

/**
 * Get all weeks in a month (for drill-down)
 */
function getWeeklyBreakdownForMonth($pdo, $year, $month)
{
    $tz = new DateTimeZone(Time::TIME_ZONE);
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
        $sunday = clone $current;
        $sunday->modify('+6 days');
        if ($sunday > $lastDay) {
            $sunday = clone $lastDay;
        }

        $monday->setTime(0, 0, 0);
        $startUnix = strtotime($monday->format('Y-m-d'));

        $sunday->setTime(23, 59, 59);
        $endUnix = $sunday->getTimestamp();

        $weekData = getWeeklyMetrics($pdo, $startUnix, $endUnix);
        $weekData['monday'] = (int) $startUnix;
        $weekData['sunday'] = (int) $endUnix;
        $weeks[] = $weekData;

        $current->modify('+1 week');
    }
    return $weeks;
}
