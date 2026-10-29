<?php
// =============================================================================
// includes/helpers.php — Global helper functions
// =============================================================================

require_once __DIR__ . '/logger.php';

// --- DATA FETCHING ---

/**
 * Fetch all rows from a table.
 * Used by: modules/Bookings/add.php, modules/Bookings/edit.php
 * ⚠️ Only call with hardcoded, trusted table/column names!
 */
function fetchData(PDO $pdo, string $tableName, string $orderBy): array
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
        logError('HELPERS', "fetchData: Invalid ORDER BY: '$orderBy'");
        return [];
    }
    try {
        return $pdo->query("SELECT * FROM `$tableName` ORDER BY $orderBy")->fetchAll();
    } catch (PDOException $e) {
        logError('HELPERS', "fetchData failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch a single column from a table as a flat array.
 * Used by: services/lists.php, modules/Bookings/add.php, modules/Bookings/edit.php
 * ⚠️ Only call with hardcoded, trusted table/column names!
 */
function fetchColumn(PDO $pdo, string $tableName, string $columnName, string $orderBy): array
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
        logError('HELPERS', "fetchColumn: Invalid ORDER BY: '$orderBy'");
        return [];
    }
    try {
        return $pdo->query("SELECT `$columnName` FROM `$tableName` ORDER BY $orderBy")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        logError('HELPERS', "fetchColumn failed: " . $e->getMessage());
        return [];
    }
}

/**
 * If a client was archived, un-archive them the moment a booking or
 * prebooking is actually made for them — booking someone is treated as
 * evidence they're active again, so there's no separate "Unarchive" step.
 * Used by: modules/Bookings/api/index.php, modules/Prebookings/api/index.php
 * Safe to call for any contact_id; no-ops if already active or not found.
 */
function reactivateContactIfArchived(PDO $pdo, int $contactId): void
{
    if ($contactId <= 0) {
        return;
    }
    try {
        $stmt = $pdo->prepare("UPDATE contacts SET is_archived = 0 WHERE id = ? AND is_archived = 1");
        $stmt->execute([$contactId]);
        if ($stmt->rowCount() > 0) {
            logInfo('CLIENT', 'Client auto-reactivated via new booking', ['client_id' => $contactId]);
        }
    } catch (PDOException $e) {
        logError('HELPERS', 'reactivateContactIfArchived failed: ' . $e->getMessage(), ['client_id' => $contactId]);
    }
}

/**
 * Generate 15-minute interval time options (00:00–23:45) for dropdowns.
 * Used by: modules/Bookings/add.php, modules/Bookings/edit.php
 * Returns: array of 'HH:MM' strings
 */
function generateTimeOptions(): array
{
    $times = [];
    for ($hour = 0; $hour < 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 15) {
            $times[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }
    return $times;
}


// --- WHATSAPP & MESSAGING ---

/**
 * Format a raw phone number for use in a WhatsApp URL.
 * Handles SA local numbers (08x → 27x), international (+xx), and validates SA mobile prefix.
 * Used by: buildWhatsAppUrl(), modules/Bookings/view.php
 * Returns: digits-only string, or '' if invalid/not a mobile number
 */
function formatPhoneNumberForWhatsApp(string $rawPhone): string
{
    if (!$rawPhone) {
        return '';
    }

    $rawPhone = trim($rawPhone);

    // Handle international format (starts with +)
    if (substr($rawPhone, 0, 1) === '+') {
        $phone = preg_replace('/\D/', '', substr($rawPhone, 1));
        return $phone ?: '';
    }

    // Non-+ input: clean digits only
    $phone = preg_replace('/\D/', '', $rawPhone);
    if (!$phone) {
        return '';
    }

    // Apply local SA formatting logic
    if (substr($phone, 0, 1) === '0') {
        $phone = WHATSAPP_COUNTRY_CODE . substr($phone, 1);
    } elseif (substr($phone, 0, strlen(WHATSAPP_COUNTRY_CODE)) !== WHATSAPP_COUNTRY_CODE) {
        $phone = WHATSAPP_COUNTRY_CODE . $phone;
    }

    // Validate SA mobile numbers (27 + 9 digits = 11 total, prefix 6/7/8)
    if (substr($phone, 0, strlen(WHATSAPP_COUNTRY_CODE)) === WHATSAPP_COUNTRY_CODE) {
        if (strlen($phone) !== 11) {
            return '';
        }
        $thirdDigit = $phone[2] ?? '';
        if (!in_array($thirdDigit, ['6', '7', '8'], true)) {
            return ''; // Landline or non-mobile
        }
    }

    return $phone;
}

/**
 * Build a wa.me WhatsApp URL for a given phone number and message.
 * Used by: modules/Bookings/api/index.php, modules/Prebookings/index.php, modules/Prebookings/api/index.php
 * Returns: full URL string, or '#' if phone is invalid
 */
function buildWhatsAppUrl(string $phone, string $message): string
{
    $cleanPhone = formatPhoneNumberForWhatsApp($phone);
    if (!$cleanPhone) {
        return '#';
    }
    return 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($message);
}

/**
 * Build the Waze navigation URLs for a booking's pickup and destination.
 * The client's saved GPS pin always belongs to the client's home address, so it
 * is used for the pickup side when the trip is not swapped, and for the
 * destination side when it is swapped. A GPS pin is only used when the pickup
 * is not a custom ("other") address. Anything without a pin falls back to an
 * address search.
 * Used by: modules/Bookings/view.php, modules/WhatsApp/helpers.php (buildBookingMessageVars)
 *
 * @param array $booking  Must contain: pickup_location, destination (already
 *                        swapped for display), was_swapped, and optionally
 *                        pickup_is_custom, client_pickup_lat, client_pickup_lng
 * @return array{pickup: string, destination: string}
 */
function buildWazeUrls(array $booking): array
{
    $hasGps = !empty($booking['client_pickup_lat']) && !empty($booking['client_pickup_lng']);
    $useGps = $hasGps && empty($booking['pickup_is_custom']);
    $gpsUrl = $hasGps
        ? 'https://waze.com/ul?ll=' . $booking['client_pickup_lat'] . ',' . $booking['client_pickup_lng'] . '&navigate=yes'
        : '';

    $pickupSearchUrl = 'https://waze.com/ul?q=' . urlencode($booking['pickup_location'] ?? '') . '&navigate=yes';
    $destSearchUrl   = 'https://waze.com/ul?q=' . urlencode($booking['destination'] ?? '') . '&navigate=yes';

    if (empty($booking['was_swapped'])) {
        return [
            'pickup'      => $useGps ? $gpsUrl : $pickupSearchUrl,
            'destination' => $destSearchUrl,
        ];
    }

    return [
        'pickup'      => $pickupSearchUrl,
        'destination' => $useGps ? $gpsUrl : $destSearchUrl,
    ];
}

/**
 * Build the Flightradar24 tracking URL for a flight number
 * (spaces removed, lowercased — e.g. "BA 6201" becomes .../ba6201).
 * Used by: modules/Bookings/view.php, modules/WhatsApp/helpers.php (buildBookingMessageVars)
 */
function buildFlightradarUrl(string $flightNumber): string
{
    $clean = preg_replace('/\s+/', '', $flightNumber);
    return 'https://www.flightradar24.com/data/flights/' . strtolower($clean);
}

/**
 * Work out who owes whom between Apex Transit and the allocated driver for a booking.
 * Single source of truth for the driver WhatsApp message and the booking view,
 * so the two can never disagree.
 *   - Cash + booking fee:    driver pays Apex the fee.
 *   - EFT  + booking fee:    Apex pays the driver cost minus fee, once the client has paid.
 *   - No booking fee + EFT:  Apex pays the driver the full cost, once the client has paid.
 *   - No booking fee + cash: nothing due.
 *   - No fee recorded:       nothing stated (matches the driver message).
 * Used by: modules/WhatsApp/helpers.php (buildBookingMessageVars), modules/Bookings/view.php
 *
 * @param array $bookingDetails  Needs: cost, payment_method. Optional: booking_fee, no_booking_fee.
 * @return array{direction: string, amount: float, fee: ?float, no_booking_fee: bool, is_eft: bool}
 *               direction is 'driver_pays_apex', 'apex_pays_driver' or 'none'.
 *               fee is null when waived or not recorded.
 */
function calculateDriverSettlement(array $bookingDetails): array
{
    $cost = (float) ($bookingDetails['cost'] ?? 0);
    $isEft = (($bookingDetails['payment_method'] ?? '') === 'eft');
    $noBookingFee = !empty($bookingDetails['no_booking_fee']);
    $fee = (!$noBookingFee && isset($bookingDetails['booking_fee']))
        ? (float) $bookingDetails['booking_fee']
        : null;

    $direction = 'none';
    $amount = 0.0;

    if ($noBookingFee) {
        if ($isEft) {
            $direction = 'apex_pays_driver';
            $amount = $cost;
        }
    } elseif ($fee !== null && $fee > 0) {
        if ($isEft) {
            $direction = 'apex_pays_driver';
            $amount = $cost - $fee;
        } else {
            $direction = 'driver_pays_apex';
            $amount = $fee;
        }
    }

    return [
        'direction'      => $direction,
        'amount'         => $amount,
        'fee'            => $fee,
        'no_booking_fee' => $noBookingFee,
        'is_eft'         => $isEft,
    ];
}


// --- SYSTEM VARIABLES ---

/**
 * All configurable system variables, with labels, types, and defaults.
 * Managed via services/variables.php and stored in the system_variables table.
 */

const SYSTEM_VARIABLES = [
    'car_rental_price' => ['label' => 'Car Rental Price (R)', 'type' => 'number', 'default' => 2600],
    'financial_months_back' => ['label' => 'Financial History (months)', 'type' => 'number', 'default' => 6],
    'apex_booking_fee_pct' => ['label' => 'Apex Booking Fee (%)', 'type' => 'number', 'default' => 0],
    'rate_per_km' => ['label' => 'Rate per Km (R)', 'type' => 'number', 'default' => 15.00],
    'distance_warning_km' => [
        'label' => 'Distance Warning (km)',
        'type' => 'number',
        'default' => 200,
        'hint' => 'A single trip or itinerary leg longer than this shows a warning, because it usually means Google placed an address in the wrong town.',
    ],
    'rent' => ['label' => 'Rent (R/week)', 'type' => 'number', 'default' => 0],
    'debt_payment' => ['label' => 'Debt Payment (R/week)', 'type' => 'number', 'default' => 0],
    'living_expenses_daily' => ['label' => 'Living Expenses (R/day)', 'type' => 'number', 'default' => 120],
    'after_hours_charge' => ['label' => 'After-Hours Surcharge (R)', 'type' => 'number', 'default' => 75],
    'after_hours_start' => ['label' => 'After-Hours Start Time', 'type' => 'time', 'default' => '20:00'],
    'after_hours_end' => ['label' => 'After-Hours End Time', 'type' => 'time', 'default' => '06:00'],
    'airport_pickup_notice' => [
        'label' => 'Cape Town Airport Pickup Notice',
        'type' => 'textarea',
        'default' => "Please ensure that your phone is on and connected after you've landed.",
    ],
    'airport_pickup_notice_af' => [
        'label' => 'Cape Town Airport Pickup Notice (Afrikaans)',
        'type' => 'text',
        'default' => 'Maak asseblief seker dat u foon aan en gekoppel is nadat u geland het.',
        'hint' => 'Sent instead of the English notice to clients set to Afrikaans. Leave empty to send the English notice.',
    ],
    'after_hours_notice_af' => [
        'label' => 'After-Hours Notice (Afrikaans)',
        'type' => 'text',
        'default' => "Dit is 'n na-ure rit — 'n na-ure toeslag van R{amount} is by die koste ingesluit.",
        'hint' => 'Sent for clients set to Afrikaans. {amount} is replaced by the booking\'s after-hours charge. Leave the {amount} in.',
    ],
    'invoice_payment_details' => [
        'label' => 'Invoice Payment Details (bank / EFT)',
        'type' => 'textarea',
        'default' => "Bank: (your bank)\nAccount holder: (your name)\nAccount number: (your account number)\nBranch code: (branch code)",
    ],
    'invoice_payment_terms' => [
        'label' => 'Invoice Payment Terms',
        'type' => 'text',
        'default' => '',
        'optional' => true,
        'hint' => 'Free text shown on unpaid invoices, e.g. "Payment due within 7 days". Leave empty to show nothing.',
    ],
    'invoice_footer_note' => [
        'label' => 'Invoice Footer Note',
        'type' => 'text',
        'default' => 'Thank you for your business!',
    ],
    'ai_prompt_template' => [
        'label' => 'AI Budget Prompt Template',
        'type' => 'textarea',
        'default' => <<<PROMPT
Restate the facts below as a short, plain daily digest. Rules:
- Report numbers only. No advice, no opinions, no encouragement, no warnings you invent.
- If a figure is zero or missing from the facts, say so plainly — do not fill in a guess.
- One line per day, then the two monthly pace lines, exactly as given.
- No preamble, no closing remarks.

FACTS:
{{facts_block}}
PROMPT,
    ],
];
/**
 * Get a system variable value from the database.
 * Falls back to the default defined in SYSTEM_VARIABLES if not found.
 * Used by: financials/helper.php
 *
 * @param PDO    $pdo
 * @param string $name  Key matching a SYSTEM_VARIABLES entry
 * @return mixed        Stored value, or default, or null if key unknown
 */
function getSystemVariable(PDO $pdo, string $name): mixed
{
    $stmt = $pdo->prepare("SELECT value FROM system_variables WHERE name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetchColumn();
    return ($result !== false) ? $result : (SYSTEM_VARIABLES[$name]['default'] ?? null);
}

/**
 * True when a location name identifies the Cape Town Airport pickup point
 * specifically — not just any location whose name happens to mention the
 * airport (e.g. an airport drop-off/departure entry). Requires all three
 * keywords present independently (case-insensitive); each is checked on
 * its own rather than against one concatenated phrase, since saved
 * location names don't always put them in the same order or adjacent.
 *
 * @param string $location
 */
function isAirportPickupLocation(string $location): bool
{
    return stripos($location, 'Cape Town') !== false
        && stripos($location, 'Airport') !== false
        && stripos($location, 'Pickup') !== false;
}

/**
 * Returns booking notes with the Cape Town Airport pickup reminder
 * appended on its own line, when the effective pickup location matches
 * isAirportPickupLocation(). The reminder text itself lives in the
 * airport_pickup_notice system variable (editable via services/variables.php)
 * — it is never written into bookings.description. It's appended here, at
 * render time only, so repeated edits/renders never duplicate it in storage.
 *
 * Used by: modules/WhatsApp/helpers.php (buildBookingMessageVars), modules/Bookings/view.php
 *
 * @param PDO    $pdo
 * @param string $pickup  Effective pickup location — caller must already
 *                        have resolved this for was_swapped
 * @param string $notes   Raw stored notes/description text
 * @param string $lang    'en' (default) or 'af'. Afrikaans uses the
 *                        airport_pickup_notice_af variable and falls back to the
 *                        English notice when that is empty.
 * @return string          Notes text ready for display
 */
function appendAirportPickupNotice(PDO $pdo, string $pickup, ?string $notes, string $lang = 'en'): string
{
    $notes = $notes ?? '';

    if (!isAirportPickupLocation($pickup)) {
        return $notes;
    }

    $notice = '';
    if ($lang === 'af') {
        $notice = trim((string) getSystemVariable($pdo, 'airport_pickup_notice_af'));
    }
    if ($notice === '') {
        $notice = trim((string) getSystemVariable($pdo, 'airport_pickup_notice'));
    }
    if ($notice === '' || stripos($notes, $notice) !== false) {
        return $notes;
    }

    return trim($notes) === '' ? $notice : rtrim($notes) . "\n" . $notice;
}

/**
 * Whether a trip start time falls within the after-hours window defined by
 * the after_hours_start / after_hours_end system variables. The window may
 * wrap past midnight (e.g. 20:00 to 06:00), so it's checked as "at or after
 * start, OR before end" rather than a simple range.
 * Used by: modules/Bookings/api/index.php
 *
 * @param PDO    $pdo
 * @param string $startTime  "H:i" or "H:i:s" trip start time
 * @return bool
 */
function isAfterHoursStartTime(PDO $pdo, string $startTime): bool
{
    $start = substr((string) getSystemVariable($pdo, 'after_hours_start'), 0, 5);
    $end = substr((string) getSystemVariable($pdo, 'after_hours_end'), 0, 5);
    $time = substr($startTime, 0, 5);

    if ($start === '' || $end === '') {
        return false;
    }
    if ($start <= $end) {
        return $time >= $start && $time < $end;
    }
    // Window wraps past midnight
    return $time >= $start || $time < $end;
}

/**
 * Returns booking notes with the after-hours surcharge notice appended,
 * when the booking's stored after_hours_charge is set. Computed at render
 * time only, from the stored charge amount — never written into
 * bookings.description, same principle as appendAirportPickupNotice().
 * Used by: modules/WhatsApp/helpers.php (buildBookingMessageVars), modules/Bookings/view.php
 *
 * @param float|string|null $afterHoursCharge  bookings.after_hours_charge value
 * @param string|null       $notes             Raw stored notes/description text
 * @param string            $lang              'en' (default) or 'af'
 * @param PDO|null          $pdo               Needed for 'af' (reads the after_hours_notice_af
 *                                             variable); without it the default Afrikaans text is used
 * @return string                               Notes text ready for display
 */
function appendAfterHoursNotice($afterHoursCharge, ?string $notes, string $lang = 'en', ?PDO $pdo = null): string
{
    $notes = $notes ?? '';
    $charge = (float) $afterHoursCharge;

    if ($charge <= 0) {
        return $notes;
    }

    if ($lang === 'af') {
        if (stripos($notes, 'na-ure') !== false) {
            return $notes;
        }
        $template = $pdo !== null
            ? trim((string) getSystemVariable($pdo, 'after_hours_notice_af'))
            : SYSTEM_VARIABLES['after_hours_notice_af']['default'];
        if ($template === '' || strpos($template, '{amount}') === false) {
            $template = SYSTEM_VARIABLES['after_hours_notice_af']['default'];
        }
        $notice = str_replace('{amount}', number_format($charge, 2), $template);
        return trim($notes) === '' ? $notice : rtrim($notes) . "\n" . $notice;
    }

    if (stripos($notes, 'after-hours') !== false) {
        return $notes;
    }

    $notice = 'This is an after-hours trip — a R' . number_format($charge, 2) . ' after-hours surcharge is included in the cost.';
    return trim($notes) === '' ? $notice : rtrim($notes) . "\n" . $notice;
}

/**
 * Get the value a system variable held as of a given date, using
 * system_variable_history. Past periods keep the rate that was in
 * effect at the time — changing the current rate never rewrites them.
 *
 * Lookup rule: the most recent history row for this variable with
 * effective_from <= $asOfDate. If no history rows exist yet for this
 * variable, falls back to the current live system_variables value
 * (via getSystemVariable) — this is what protects existing history
 * before any rows have been logged; nothing gets backfilled.
 *
 * Used by: modules/Uber/helper.php, modules/Financials/helper.php
 *
 * @param PDO    $pdo
 * @param string $name      Key matching a SYSTEM_VARIABLES entry
 * @param string $asOfDate  Date string 'Y-m-d' for the period in question
 * @return mixed            Historical value if one exists on/before that
 *                          date, otherwise the current live value
 */
function getHistoricalVariable(PDO $pdo, string $name, string $asOfDate): mixed
{
    $stmt = $pdo->prepare(
        "SELECT value FROM system_variable_history
         WHERE variable_name = ? AND effective_from <= ?
         ORDER BY effective_from DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$name, $asOfDate]);
    $result = $stmt->fetchColumn();

    return ($result !== false) ? $result : getSystemVariable($pdo, $name);
}

/**
 * Resolve car_rental_price for a single week, applying the agreed
 * precedence: a one-off weekly override (if set) always wins; otherwise
 * fall through to the historical rate for that week's as-of date (which
 * itself falls back to the current live value if no history exists yet).
 *
 * Set only from Uber (uber_income.rental_override), but read here by
 * both Uber and Financials so an override made in Uber is automatically
 * reflected in Financials too.
 *
 * @param PDO        $pdo
 * @param mixed      $rentalOverride  uber_income.rental_override for
 *                                    this week, or null if not set
 * @param string     $asOfDate        Date string 'Y-m-d' for this week
 *                                    (used only when there's no override)
 */
function resolveCarRentalForWeek(PDO $pdo, $rentalOverride, string $asOfDate): float
{
    if ($rentalOverride !== null) {
        return (float) $rentalOverride;
    }
    return (float) getHistoricalVariable($pdo, 'car_rental_price', $asOfDate);
}

/**
 * Calculate the Apex booking fee from a trip cost and fee percentage.
 * Returns 0.0 if cost or pct is zero/negative.
 * Used by: modules/Bookings/api/index.php
 */
function calculateBookingFee(float $cost, float $pct): float
{
    if ($cost <= 0 || $pct <= 0) {
        return 0.0;
    }
    return round($cost * $pct / 100, 2);
}

/**
 * One Distance Matrix request between two places. Each side is either free text or "place_id:XYZ".
 * Used by: calculateTripDistanceKm(), calculateLegDistance()
 *
 * Returns:
 *   status       'ok' | 'not_found' (an address could not be located) | 'no_route' | 'error' (network/API problem)
 *   km           float|null
 *   origin_found / dest_found   bool, false when Google could not locate that side
 *   from_address / to_address   the address Google actually used (for "Google read this as ..." messages)
 *
 * region=za only biases ambiguous names towards South Africa; it does not restrict results.
 */
function requestDistanceMatrix(string $origin, string $destination, int $timeout = 8): array
{
    $result = [
        'status' => 'error', 'km' => null,
        'origin_found' => true, 'dest_found' => true,
        'from_address' => '', 'to_address' => '',
    ];

    $url = GOOGLE_DISTANCE_MATRIX_URL . '?' . http_build_query([
        'origins'      => $origin,
        'destinations' => $destination,
        'units'        => 'metric',
        'region'       => 'za',
        'key'          => GOOGLE_API_KEY,
    ]);

    $context = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $responseJson = @file_get_contents($url, false, $context);
    if ($responseJson === false) {
        return $result;
    }

    $data = json_decode($responseJson, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
        return $result;
    }

    $result['from_address'] = (string) ($data['origin_addresses'][0] ?? '');
    $result['to_address']   = (string) ($data['destination_addresses'][0] ?? '');
    $result['origin_found'] = $result['from_address'] !== '';
    $result['dest_found']   = $result['to_address'] !== '';

    $element = $data['rows'][0]['elements'][0] ?? null;
    if (!$element) {
        return $result;
    }

    if (($element['status'] ?? '') === 'OK') {
        $result['status'] = 'ok';
        $result['km']     = round($element['distance']['value'] / 1000, 1);
    } elseif (($element['status'] ?? '') === 'NOT_FOUND') {
        $result['status'] = 'not_found';
    } elseif (($element['status'] ?? '') === 'ZERO_RESULTS') {
        $result['status'] = 'no_route';
    }
    return $result;
}

/**
 * Calculate driving distance in km between two addresses using the
 * Google Distance Matrix API. Used by: Bookings add/update (distance_km
 * column) and modules/DistanceCalculator.
 *
 * Returns null on any failure (address not found, API error, network
 * issue) so callers can store/display "unknown" rather than a wrong
 * number — never guess or fall back to a default distance.
 */
function calculateTripDistanceKm(string $origin, string $destination): ?float
{
    if (trim($origin) === '' || trim($destination) === '') {
        return null;
    }
    return requestDistanceMatrix($origin, $destination)['km'];
}

/**
 * Distance between two places with enough detail to tell the user what went wrong.
 * Either side may carry a Google place id (exact); if Google rejects the id the plain text is tried instead.
 * Used by: modules/Itineraries (legs), modules/Bookings (live distance check)
 *
 * Returns the requestDistanceMatrix() array, with status 'missing' when either side is blank.
 */
function calculateLegDistance(string $origin, string $destination, ?string $originPlaceId = null, ?string $destPlaceId = null, int $timeout = 8): array
{
    $origin      = trim($origin);
    $destination = trim($destination);
    if ($origin === '' || $destination === '') {
        return [
            'status' => 'missing', 'km' => null, 'origin_found' => true, 'dest_found' => true,
            'from_address' => '', 'to_address' => '',
        ];
    }

    $o = $originPlaceId ? 'place_id:' . $originPlaceId : $origin;
    $d = $destPlaceId ? 'place_id:' . $destPlaceId : $destination;

    $result = requestDistanceMatrix($o, $d, $timeout);

    // A stale or rejected place id: fall back to the typed text before giving up
    if ($result['status'] === 'not_found' && ($originPlaceId || $destPlaceId)) {
        $result = requestDistanceMatrix($origin, $destination, $timeout);
    }
    return $result;
}

/**
 * The distance (km) above which a single trip or leg gets a "check the address" warning.
 * Set in Services → System Variables (distance_warning_km).
 */
function getDistanceWarningKm(PDO $pdo): float
{
    $limit = (float) getSystemVariable($pdo, 'distance_warning_km');
    return $limit > 0 ? $limit : 200.0;
}

// --- OUTPUT HELPERS ---

/**
 * Escape a string for safe HTML output (shorthand for htmlspecialchars).
 * Used by: templates throughout
 */
function e(?string $string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Build a breadcrumb HTML string for use with the $breadcrumb variable.
 * Generates linked segments for parent pages and plain text for the current page.
 * Must be called after config.php is loaded (BASE_URL required for 'url' values).
 * Used by: all pages with $show_breadcrumb = true
 *
 * @param array $items  Each item: ['label' => '...'] for the current page,
 *                      or ['label' => '...', 'url' => '...'] for a parent segment.
 * @return string       HTML string starting with ' > ', for appending after "Home".
 */
function buildBreadcrumb(array $items): string
{
    $parts = [];
    foreach ($items as $item) {
        if (!empty($item['url'])) {
            $parts[] = '<a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
        } else {
            $parts[] = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        }
    }
    return ' > ' . implode(' > ', $parts);
}

/**
 * Apply an optional ?months= override on top of a system-variable months-back
 * value, for a temporary quick view (e.g. "Show Last 12 Months") that doesn't
 * touch the saved setting itself.
 * Used by: Financials/index.php, Financials/balance_sheet.php, Fuel/index.php,
 *          Uber/index.php, Bookings/reports.php
 *
 * @param int $monthsBack  The months-back value from the saved system variable
 * @return int             $monthsBack, or the ?months= override if present and valid
 */
function applyMonthsOverride(int $monthsBack): int
{
    if (!empty($_GET['months']) && ctype_digit((string) $_GET['months'])) {
        return max(1, (int) $_GET['months']);
    }
    return $monthsBack;
}

/**
 * Render a small toggle link for report pages using applyMonthsOverride():
 * "Show Last N Months" when viewing the default range, or "Back to Default"
 * when a temporary override is currently active. Reload-based, not saved.
 * Used by: Financials/index.php, Financials/balance_sheet.php, Fuel/index.php,
 *          Uber/index.php, Bookings/reports.php
 *
 * @param int $currentMonthsBack  The months value currently in effect (after override)
 * @param int $defaultMonthsBack  The saved system-variable value (or its fallback)
 * @param int $quickViewMonths    The quick-view months count to offer (default 12)
 * @return string                 HTML for the toggle, or '' if there's nothing to offer
 */
function renderMonthsOverrideToggle(int $currentMonthsBack, int $defaultMonthsBack, int $quickViewMonths = 12): string
{
    if ($currentMonthsBack !== $defaultMonthsBack) {
        return '<div class="months-toggle"><a href="?" class="months-toggle-btn">'
            . 'Back to Default (' . $defaultMonthsBack . ' months)</a></div>';
    }
    if ($currentMonthsBack < $quickViewMonths) {
        return '<div class="months-toggle"><a href="?months=' . $quickViewMonths . '" class="months-toggle-btn">'
            . 'Show Last ' . $quickViewMonths . ' Months</a></div>';
    }
    return '';
}

/**
 * Send a JSON response and exit.
 * Used by: all API files site-wide.
 *
 * @param mixed $data        Array to encode as JSON
 * @param int   $statusCode  HTTP status code (default 200)
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
