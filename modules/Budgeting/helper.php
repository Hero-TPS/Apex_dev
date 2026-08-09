<?php

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
 * Build the full weekly budget plan: rent/debt earmark status (split-capable —
 * a booking can partially fund both), car rental (settled weekly via Uber
 * payout, not daily), fuel needs (Uber + bookings), running costs, living
 * expenses, weighed against this week's income (bookings + Uber, where Uber
 * is only logged at week's end so may not be available mid-week).
 *
 * @param  PDO $pdo
 * @return array
 */
function getWeeklyBudgetPlan(PDO $pdo): array
{
    $tz = new DateTimeZone(TIME_ZONE);
    $today = new DateTime('now', $tz);
    $dayOfWeek = (int) $today->format('N');
    $monday = (clone $today)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
    $sunday = (clone $monday)->modify('+6 days')->setTime(23, 59, 59);

    $rentTarget  = (float) getSystemVariable($pdo, 'rent');
    $debtTarget  = (float) getSystemVariable($pdo, 'debt_payment');
    $carRental   = (float) getSystemVariable($pdo, 'car_rental_price');
    $livingDaily = (float) getSystemVariable($pdo, 'living_expenses_daily');

    // Cancelled bookings are deleted, not flagged — no status filter needed to exclude them.
    $stmt = $pdo->prepare(
        "SELECT id, trip_date, cost, status, earmarked_rent, earmarked_debt, original_pickup, original_destination
         FROM bookings WHERE trip_date BETWEEN ? AND ?"
    );
    $stmt->execute([$monday->format('Y-m-d'), $sunday->format('Y-m-d')]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookingIncomeTotal = 0.0;
    $earmarkedRent = 0.0;
    $earmarkedDebt = 0.0;
    $upcomingBookings = [];

    foreach ($bookings as $b) {
        $bookingIncomeTotal += (float) $b['cost'];
        $earmarkedRent += (float) ($b['earmarked_rent'] ?? 0);
        $earmarkedDebt += (float) ($b['earmarked_debt'] ?? 0);
        if ($b['status'] !== 'completed') {
            $upcomingBookings[] = $b;
        }
    }

    // Uber income/costs are only logged manually, usually at week's end (Sundays) —
    // a missing row mid-week means "not logged yet," not "zero income."
    $stmt = $pdo->prepare("SELECT id, total_income FROM uber_income WHERE week_start = ?");
    $stmt->execute([$monday->getTimestamp()]);
    $uberWeek = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $uberLogged = $uberWeek !== null;
    $uberIncomeSoFar = $uberLogged ? (float) $uberWeek['total_income'] : null;

    // Historical fuel_cost_per_km (trailing 8 weeks, reusing Financials' own metrics)
    $trailingStart = (clone $monday)->modify('-8 weeks');
    $metrics = getWeeklyMetrics($pdo, $trailingStart->getTimestamp(), $monday->getTimestamp());
    $fuelCostPerKm = (float) ($metrics['fuel_cost_per_km'] ?? 0);

    $bookingFuelForecast = 0.0;
    $bookingFuelDetails = [];
    foreach ($upcomingBookings as $b) {
        $estimate = estimateBookingFuelCost($b, $fuelCostPerKm);
        $bookingFuelDetails[] = array_merge(['booking_id' => $b['id'], 'trip_date' => $b['trip_date']], $estimate);
        if ($estimate['fuel_cost'] !== null) {
            $bookingFuelForecast += $estimate['fuel_cost'];
        }
    }

    // Rule of thumb: Uber fuel budget ~ 1/3 of car rental
    $uberFuelTarget = $carRental / 3;

    // Running costs planned = trailing 8-week average weekly total from uber_additional_costs
    $stmt = $pdo->prepare(
        "SELECT COALESCE(AVG(weekly_total), 0) FROM (
            SELECT uac.uber_income_id, SUM(uac.amount) AS weekly_total
            FROM uber_additional_costs uac
            JOIN uber_income ui ON ui.id = uac.uber_income_id
            WHERE ui.week_start BETWEEN ? AND ?
            GROUP BY uac.uber_income_id
         ) weekly"
    );
    $stmt->execute([$trailingStart->getTimestamp(), $monday->getTimestamp()]);
    $runningCostsPlanned = (float) $stmt->fetchColumn();

    $livingTarget = $livingDaily * 7;

    // Total obligations always include car rental + Uber fuel as planned weekly
    // costs, regardless of whether Uber's actually been logged yet this week.
    $totalObligations = $rentTarget + $debtTarget + $carRental + $uberFuelTarget
        + $bookingFuelForecast + $runningCostsPlanned + $livingTarget;
    $totalIncome = $bookingIncomeTotal + ($uberIncomeSoFar ?? 0);

    return [
        'week_start' => $monday->format('Y-m-d'),
        'rent' => ['target' => $rentTarget, 'earmarked' => $earmarkedRent, 'shortfall' => max(0, $rentTarget - $earmarkedRent)],
        'debt' => ['target' => $debtTarget, 'earmarked' => $earmarkedDebt, 'shortfall' => max(0, $debtTarget - $earmarkedDebt)],
        'car_rental' => ['amount' => $carRental, 'note' => 'Due Sunday via Uber payout'],
        'fuel' => [
            'uber_target' => $uberFuelTarget,
            'booking_forecast' => $bookingFuelForecast,
            'booking_details' => $bookingFuelDetails,
            'total' => $uberFuelTarget + $bookingFuelForecast,
        ],
        'running_costs_planned' => $runningCostsPlanned,
        'living_expenses_target' => $livingTarget,
        'income' => [
            'booking_income' => $bookingIncomeTotal,
            'uber_logged' => $uberLogged,
            'uber_income_so_far' => $uberIncomeSoFar,
            'total' => $totalIncome,
        ],
        'total_obligations' => $totalObligations,
        'buffer' => $totalIncome - $totalObligations,
    ];
}

/**
 * Substitute {{token}} placeholders in the DB-stored prompt template with
 * live values from the weekly budget plan.
 *
 * @param  string $template
 * @param  array  $plan  From getWeeklyBudgetPlan()
 * @return string
 */
function buildPromptFromBudgetPlan(string $template, array $plan): string
{
    $uberIncomeText = $plan['income']['uber_logged']
        ? 'R' . number_format($plan['income']['uber_income_so_far'], 2)
        : 'not logged yet this week (he logs Uber income/costs manually, usually on Sundays — do not treat this as zero income)';

    $replacements = [
        '{{week_start}}'             => $plan['week_start'],
        '{{rent_target}}'            => number_format($plan['rent']['target'], 2),
        '{{rent_earmarked}}'         => number_format($plan['rent']['earmarked'], 2),
        '{{rent_shortfall}}'         => number_format($plan['rent']['shortfall'], 2),
        '{{debt_target}}'            => number_format($plan['debt']['target'], 2),
        '{{debt_earmarked}}'         => number_format($plan['debt']['earmarked'], 2),
        '{{debt_shortfall}}'         => number_format($plan['debt']['shortfall'], 2),
        '{{car_rental}}'             => number_format($plan['car_rental']['amount'], 2) . ' (' . $plan['car_rental']['note'] . ')',
        '{{uber_fuel_target}}'       => number_format($plan['fuel']['uber_target'], 2),
        '{{booking_fuel_forecast}}'  => number_format($plan['fuel']['booking_forecast'], 2),
        '{{fuel_total}}'             => number_format($plan['fuel']['total'], 2),
        '{{running_costs_planned}}'  => number_format($plan['running_costs_planned'], 2),
        '{{living_expenses_target}}' => number_format($plan['living_expenses_target'], 2),
        '{{booking_income}}'         => number_format($plan['income']['booking_income'], 2),
        '{{uber_income_so_far}}'     => $uberIncomeText,
        '{{total_income}}'           => number_format($plan['income']['total'], 2),
        '{{total_obligations}}'      => number_format($plan['total_obligations'], 2),
        '{{buffer}}'                 => number_format($plan['buffer'], 2),
    ];

    return strtr($template, $replacements);
}

/**
 * Get this week's AI budget briefing, using a cached result if the plan
 * hasn't changed and the cache isn't stale (24h). Calls the Claude API
 * only when the plan's hash differs or 24h have passed.
 *
 * @param  PDO   $pdo
 * @param  array $plan          From getWeeklyBudgetPlan() — passed in so the
 *                               caller (the page) doesn't compute it twice
 * @param  bool  $forceRefresh
 * @return array  ['success' => bool, 'message' => string, 'cached' => bool]
 */
function getAiBudgetRecommendation(PDO $pdo, array $plan, bool $forceRefresh = false): array
{
    $hash = md5(json_encode($plan));

    $stmt = $pdo->prepare(
        "SELECT recommendation, snapshot_hash, generated_at FROM ai_recommendations
         WHERE type = 'weekly_budget' ORDER BY generated_at DESC LIMIT 1"
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

    $template = getSystemVariable($pdo, 'ai_prompt_template');
    $prompt = buildPromptFromBudgetPlan($template, $plan);

    $payload = [
        'model'      => 'claude-sonnet-5',
        'max_tokens' => 350,
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
    $text = $data['content'][0]['text'] ?? null;

    if (!$text) {
        return ['success' => false, 'message' => 'Unexpected AI response format', 'cached' => false];
    }

    $text = trim($text);
    $stmt = $pdo->prepare(
        "INSERT INTO ai_recommendations (type, snapshot_hash, recommendation, generated_at)
         VALUES ('weekly_budget', ?, ?, NOW())"
    );
    $stmt->execute([$hash, $text]);

    return ['success' => true, 'message' => $text, 'cached' => false];
}
