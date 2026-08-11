<?php
// modules/Budgeting/helper.php

if (!defined('ROOT_DIR')) {
    die('Direct access not allowed');
}

require_once ROOT_DIR . '/modules/Financials/helper.php';

/**
 * Estimate fuel cost for a single booking using live distance lookup
 * (same Google Distance Matrix call as DistanceCalculator/index.php) and
 * a recent historical fuel_cost_per_km average.
 *
 * @param  array $booking         Row with original_pickup, original_destination
 * @param  float $fuelCostPerKm   From Financials::getWeeklyMetrics()
 * @return array  ['distance_km' => float|null, 'fuel_cost' => float|null, 'status' => string]
 */
function estimateBookingFuelCost(array $booking, float $fuelCostPerKm): array
{
    $url = GOOGLE_DISTANCE_MATRIX_URL . '?' . http_build_query([
        'origins'      => $booking['original_pickup'],
        'destinations' => $booking['original_destination'],
        'units'        => 'metric',
        'key'          => GOOGLE_API_KEY,
    ]);

    $response = @file_get_contents($url);
    $data = $response ? json_decode($response, true) : null;
    $element = $data['rows'][0]['elements'][0] ?? null;

    if (!$element || $element['status'] !== 'OK') {
        return ['distance_km' => null, 'fuel_cost' => null, 'status' => 'error'];
    }

    $distanceKm = $element['distance']['value'] / 1000;

    return [
        'distance_km' => $distanceKm,
        'fuel_cost'   => $distanceKm * $fuelCostPerKm,
        'status'      => 'ok',
    ];
}

/**
 * Build a rolling 7-day forecast (today + next 6 days). Each day shows
 * scheduled bookings (income + fuel), and whichever day is the upcoming
 * Sunday also carries the car rental + Uber fuel target as a known
 * settlement (these are fixed/planned figures, not dependent on whether
 * Uber income has actually been logged yet).
 *
 * @param  PDO $pdo
 * @return array  ['days' => [...], 'final_net' => float]
 */
function getSevenDayForecast(PDO $pdo): array
{
    $tz = new DateTimeZone(TIME_ZONE);
    $today = new DateTime('now', $tz);
    $today->setTime(0, 0, 0);

    $carRental   = (float) getSystemVariable($pdo, 'car_rental_price');
    $livingDaily = (float) getSystemVariable($pdo, 'living_expenses_daily');
    $uberFuelTarget = $carRental / 3;

    // Historical fuel_cost_per_km (trailing 8 weeks up to today)
    $trailingStart = (clone $today)->modify('-8 weeks');
    $metrics = getWeeklyMetrics($pdo, $trailingStart->getTimestamp(), $today->getTimestamp());
    $fuelCostPerKm = (float) ($metrics['fuel_cost_per_km'] ?? 0);

    $days = [];
    $runningNet = 0.0;

    for ($i = 0; $i < 7; $i++) {
        $date = (clone $today)->modify("+{$i} days");
        $isSunday = ((int) $date->format('N')) === 7;

        $stmt = $pdo->prepare(
            "SELECT id, cost, status, original_pickup, original_destination
             FROM bookings WHERE trip_date = ?"
        );
        $stmt->execute([$date->format('Y-m-d')]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $bookingIncome = 0.0;
        $bookingFuel = 0.0;
        $bookingDetails = [];

        foreach ($bookings as $b) {
            $bookingIncome += (float) $b['cost'];
            if ($b['status'] !== 'completed') {
                $estimate = estimateBookingFuelCost($b, $fuelCostPerKm);
                if ($estimate['fuel_cost'] !== null) {
                    $bookingFuel += $estimate['fuel_cost'];
                }
                $bookingDetails[] = array_merge(['booking_id' => $b['id'], 'cost' => $b['cost']], $estimate);
            }
        }

        $settlement = $isSunday ? ['car_rental' => $carRental, 'uber_fuel_target' => $uberFuelTarget] : null;
        $dayOut = $bookingFuel + $livingDaily + ($isSunday ? $carRental + $uberFuelTarget : 0);
        $dayNet = $bookingIncome - $dayOut;
        $runningNet += $dayNet;

        $days[] = [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->format('l'),
            'is_sunday_settlement' => $isSunday,
            'booking_income' => $bookingIncome,
            'booking_fuel' => $bookingFuel,
            'booking_details' => $bookingDetails,
            'settlement' => $settlement,
            'living_expense' => $livingDaily,
            'day_net' => $dayNet,
            'running_net' => $runningNet,
        ];
    }

    return [
        'days' => $days,
        'fuel_cost_per_km' => $fuelCostPerKm,
        'final_net' => $runningNet,
    ];
}

/**
 * Monthly pace for a weekly-cadence obligation (rent or debt): "1 payment
 * per week" is the expected baseline, tracked against Mondays elapsed this
 * month, with a fixed monthly target (weekly rate x 4) rather than one that
 * inflates in 5-Monday months.
 *
 * @param  PDO    $pdo
 * @param  string $systemVarName  'rent' or 'debt_payment'
 * @param  string $bookingColumn  'earmarked_rent' or 'earmarked_debt'
 * @return array
 */
function getMonthlyPaceFor(PDO $pdo, string $systemVarName, string $bookingColumn): array
{
    $tz = new DateTimeZone(TIME_ZONE);
    $today = new DateTime('now', $tz);
    $monthStart = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);

    $weeklyRate = (float) getSystemVariable($pdo, $systemVarName);
    $monthlyTarget = $weeklyRate * 4;

    // Count Mondays from month start through today, capped at 4
    $mondaysSoFar = 0;
    $cursor = clone $monthStart;
    while ($cursor <= $today) {
        if ((int) $cursor->format('N') === 1) {
            $mondaysSoFar++;
        }
        $cursor->modify('+1 day');
    }
    $mondaysSoFar = min(4, $mondaysSoFar);
    $targetToDate = $weeklyRate * $mondaysSoFar;

    $monthEnd = (clone $monthStart)->modify('last day of this month')->setTime(23, 59, 59);
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM({$bookingColumn}), 0) FROM bookings WHERE trip_date BETWEEN ? AND ?"
    );
    $stmt->execute([$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
    $actualToDate = (float) $stmt->fetchColumn();

    $paymentsEquivalent = $weeklyRate > 0 ? round($actualToDate / $weeklyRate, 1) : 0;

    return [
        'weekly_rate' => $weeklyRate,
        'monthly_target' => $monthlyTarget,
        'expected_payments_so_far' => $mondaysSoFar,
        'target_to_date' => $targetToDate,
        'actual_to_date' => $actualToDate,
        'payments_equivalent' => $paymentsEquivalent,
        'behind_by' => max(0, $targetToDate - $actualToDate),
    ];
}

/**
 * @param  PDO $pdo
 * @return array  ['rent' => ..., 'debt' => ...] each from getMonthlyPaceFor()
 */
function getMonthlyPace(PDO $pdo): array
{
    return [
        'rent' => getMonthlyPaceFor($pdo, 'rent', 'earmarked_rent'),
        'debt' => getMonthlyPaceFor($pdo, 'debt_payment', 'earmarked_debt'),
    ];
}

/**
 * Render the 7-day forecast + monthly pace as a plain-text fact block for
 * the AI prompt — deterministic, no interpretation added here. The prompt
 * template itself instructs the AI to only restate these, not advise.
 *
 * @param  array $forecast  From getSevenDayForecast()
 * @param  array $pace      From getMonthlyPace()
 * @return string
 */
function renderFactsBlock(array $forecast, array $pace): string
{
    $lines = [];
    foreach ($forecast['days'] as $d) {
        $line = "{$d['day_name']} ({$d['date']}): ";
        $parts = [];
        if ($d['booking_income'] > 0) {
            $parts[] = "R" . number_format($d['booking_income'], 2) . " booking income";
        }
        if ($d['booking_fuel'] > 0) {
            $parts[] = "R" . number_format($d['booking_fuel'], 2) . " est. booking fuel";
        }
        if ($d['is_sunday_settlement']) {
            $parts[] = "R" . number_format($d['settlement']['car_rental'], 2) . " car rental due";
            $parts[] = "R" . number_format($d['settlement']['uber_fuel_target'], 2) . " Uber fuel target";
        }
        $parts[] = "R" . number_format($d['living_expense'], 2) . " living";
        $line .= implode(', ', $parts) . ". Day net: R" . number_format($d['day_net'], 2);
        $lines[] = $line;
    }
    $lines[] = "7-day projected net: R" . number_format($forecast['final_net'], 2);

    $lines[] = '';
    $lines[] = "Rent this month: {$pace['rent']['expected_payments_so_far']} payments expected so far, "
        . "R" . number_format($pace['rent']['actual_to_date'], 2) . " actually earmarked "
        . "(equivalent to {$pace['rent']['payments_equivalent']} payments) against "
        . "R" . number_format($pace['rent']['target_to_date'], 2) . " target-to-date. "
        . "Monthly target: R" . number_format($pace['rent']['monthly_target'], 2) . ".";
    $lines[] = "Debt this month: {$pace['debt']['expected_payments_so_far']} payments expected so far, "
        . "R" . number_format($pace['debt']['actual_to_date'], 2) . " actually earmarked "
        . "(equivalent to {$pace['debt']['payments_equivalent']} payments) against "
        . "R" . number_format($pace['debt']['target_to_date'], 2) . " target-to-date. "
        . "Monthly target: R" . number_format($pace['debt']['monthly_target'], 2) . ".";

    return implode("\n", $lines);
}

/**
 * Get the AI's factual restatement of the 7-day forecast + monthly pace,
 * using a cached result if the facts haven't changed and the cache isn't
 * stale (24h). Calls the Claude API only when the facts hash differs or
 * 24h have passed.
 *
 * @param  PDO   $pdo
 * @param  array $forecast
 * @param  array $pace
 * @param  bool  $forceRefresh
 * @return array  ['success' => bool, 'message' => string, 'cached' => bool]
 */
function getAiFactualBriefing(PDO $pdo, array $forecast, array $pace, bool $forceRefresh = false): array
{
    $factsBlock = renderFactsBlock($forecast, $pace);
    $template = getSystemVariable($pdo, 'ai_prompt_template');
    // Hash covers both the data AND the prompt wording — editing the template
    // in Maintenance must invalidate old cache the same way changed numbers do.
    $hash = md5($factsBlock . '|' . $template);

    $stmt = $pdo->prepare(
        "SELECT recommendation, snapshot_hash, generated_at FROM ai_recommendations
         WHERE type = 'seven_day_forecast' ORDER BY generated_at DESC LIMIT 1"
    );
    $stmt->execute();
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);

    $isFresh = $cached
        && $cached['snapshot_hash'] === $hash
        && (time() - strtotime($cached['generated_at'])) < 86400;

    if ($isFresh && !$forceRefresh) {
        return ['success' => true, 'message' => $cached['recommendation'], 'cached' => true];
    }

    if (!defined('ANTHROPIC_API_KEY') || empty(ANTHROPIC_API_KEY)) {
        return ['success' => false, 'message' => 'ANTHROPIC_API_KEY not configured', 'cached' => false];
    }

    $prompt = strtr($template, ['{{facts_block}}' => $factsBlock]);

    $payload = [
        'model'      => 'claude-sonnet-5',
        'max_tokens' => 1024,
        'messages'   => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200) {
        logCritical('BUDGETING_AI', 'Anthropic API call failed', [
            'http_code' => $httpCode,
            'curl_err'  => $curlErr,
        ]);
        return ['success' => false, 'message' => 'Could not reach AI advisor', 'cached' => false];
    }

    $data = json_decode($response, true);
    $text = null;
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text = $block['text'];
            break;
        }
    }

    if (!$text) {
        logCritical('BUDGETING_AI', 'Unexpected AI response format', [
            'raw_response' => substr($response, 0, 2000),
        ]);
        return ['success' => false, 'message' => 'Unexpected AI response format', 'cached' => false];
    }

    $text = trim($text);
    $stmt = $pdo->prepare(
        "INSERT INTO ai_recommendations (type, snapshot_hash, recommendation, generated_at)
         VALUES ('seven_day_forecast', ?, ?, NOW())"
    );
    $stmt->execute([$hash, $text]);

    return ['success' => true, 'message' => $text, 'cached' => false];
}
