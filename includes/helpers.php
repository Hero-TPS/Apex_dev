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
 * Used by: maintenance/index.php, modules/Bookings/add.php, modules/Bookings/edit.php
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
 * Used by: modules/Bookings/view.php
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
 * Build the WhatsApp evening confirmation message for tomorrow's booking.
 * Used by: modules/Bookings/api/index.php (tomorrows_bookings, mark_confirmed)
 *
 * @param array $bookingDetails  Must contain: client_name, trip_date, start_time,
 *                               pickup_location, destination.
 *                               Optional: driver_name, driver_phone
 */
function createEveningConfirmationMessage(array $bookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);
    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate = $start->format('d/m/y');
    $forTime = $start->format('H:i');

    $driverLine = '';
    if (!empty($bookingDetails['driver_name'])) {
        $driverLine = "\n🚗 Your driver will be: " . $bookingDetails['driver_name'] . "\n";
        if (!empty($bookingDetails['driver_phone'])) {
            $driverLine .= "📱 Driver phone: " . $bookingDetails['driver_phone'] . "\n";
        }
    }

    return "Good day " . $bookingDetails['client_name'] . "! 👋\n\n" .
        "Just confirming your booking for tomorrow:\n\n" .
        "📅 Date: " . $forDate . "\n" .
        "🕐 Pickup Time: " . $forTime . "\n" .
        "📍 From: " . $bookingDetails['pickup_location'] . "\n" .
        "🎯 To: " . $bookingDetails['destination'] . "\n" .
        $driverLine .
        "\nSee you tomorrow! 🚗";
}

/**
 * Build the WhatsApp booking confirmation message body.
 * Detects new vs. updated booking from the presence of updated_at.
 * Used by: modules/Bookings/view.php
 *
 * @param PDO   $pdo
 * @param array $bookingDetails  Must contain: trip_date, start_time, client_name,
 *                               pickup_location, destination, cost, and optionally
 *                               flight_number, description, updated_at, date_created,
 *                               driver_name, driver_phone
 */
function createWhatsAppMessage(PDO $pdo, array $bookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);

    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate = $start->format('d/m/y');
    $startTime = $start->format('H:i');

    $flightInfo = !empty($bookingDetails['flight_number'])
        ? "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n" : '';
    $costInfo = $bookingDetails['cost'] > 0
        ? "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n" : '';
    $paymentReceivedInfo = !empty($bookingDetails['payment_received'])
        ? "✅ Payment Received\n" : '';
    $displayNotes = appendAirportPickupNotice($pdo, $bookingDetails['pickup_location'] ?? '', $bookingDetails['description'] ?? '');
    $notesInfo = !empty($displayNotes)
        ? "📝 Notes: " . $displayNotes . "\n" : '';

    $driverInfo = '';
    if (!empty($bookingDetails['driver_name'])) {
        $driverInfo = "🚗 Your driver will be: " . $bookingDetails['driver_name'] . "\n";
        if (!empty($bookingDetails['driver_phone'])) {
            $driverInfo .= "📱 Driver phone: " . $bookingDetails['driver_phone'] . "\n";
        }
    }

    $isUpdate = !empty($bookingDetails['updated_at']);
    $title = $isUpdate ? "*BOOKING UPDATED AND CONFIRMED* ✅\n\n" : "*BOOKING CONFIRMED* ✅\n\n";

    // Timestamp line (value from DB is already SAST — no conversion needed)
    $timestampInfo = '';
    if ($isUpdate) {
        $updatedDate = new DateTime($bookingDetails['updated_at'], new DateTimeZone(TIME_ZONE));
        $timestampInfo = "\n✏️ Updated: " . $updatedDate->format('d/m/y H:i') . "\n";
    } elseif (!empty($bookingDetails['date_created'])) {
        $createdDate = new DateTime($bookingDetails['date_created'], new DateTimeZone(TIME_ZONE));
        $timestampInfo = "\n🕒 Created: " . $createdDate->format('d/m/y H:i') . "\n";
    }

    return $title .
        "Good day " . $bookingDetails['client_name'] . ",\n\n" .
        "📍 Pickup: " . $bookingDetails['pickup_location'] . "\n" .
        "📅 Date: " . $forDate . " at " . $startTime . "\n" .
        "🎯 Destination: " . $bookingDetails['destination'] . "\n" .
        $costInfo .
        $paymentReceivedInfo .
        $flightInfo .
        $notesInfo .
        $driverInfo .
        $timestampInfo .
        "\n🚗 Looking forward to being of service to you. 👍\n\n" .
        "Regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
}


/**
 * Build the WA cleanup/re-engagement message for clients without bookings.
 * Sent during the Phase 1 client list cleanup campaign.
 * The message text can be edited here — no form needed.
 *
 * NOTE: The Clients view builds the equivalent message in JavaScript (client-side)
 * so that it can be opened directly as a wa.me link. This PHP function is provided
 * for Phase 2 server-side use (e.g. batch list generation, previews).
 *
 * @param string $clientName  The client's display name
 */
function createCleanupWhatsAppMessage(string $clientName): string
{
    return "Hi " . $clientName . " 👋 André Matthews suggested I reach out to you.\n\n" .
        "I'm " . BUSINESS_OWNER . " from " . BUSINESS_NAME . " — I've taken over his personal transport services in the Helderberg area since he retired.\n\n" .
        "Just checking whether transport is something you still need from time to time. I'd be happy to assist if so.\n\n" .
        "This is a once-off message — I won't follow up unless I hear from you.\n\n" .
        "Kind regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
    // ======================================================
}



/**
 * Build a WhatsApp reminder message for a prebooking.
 * Sent to the client to remind them about their tentative booking and request confirmation of details.
 * Used by: modules/Prebookings/index.php
 *
 * @param array $prebookingDetails  Must contain: client_name, trip_date.
 *                                  Optional: start_time, original_pickup, original_destination,
 *                                            was_swapped, cost, description
 */
function createPrebookingWhatsAppMessage(array $prebookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);
    $date = new DateTime($prebookingDetails['trip_date'], $timezone);
    $forDate = $date->format('d/m/y');

    $timeLine = !empty($prebookingDetails['start_time'])
        ? "⏰ Time: " . (new DateTime($prebookingDetails['trip_date'] . ' ' . $prebookingDetails['start_time'], $timezone))->format('H:i') . "\n"
        : "⏰ Time: TBC\n";

    $wasSwapped = !empty($prebookingDetails['was_swapped']);
    $rawPickup = $prebookingDetails['original_pickup'] ?? '';
    $rawDest = $prebookingDetails['original_destination'] ?? '';

    $effPickup = $wasSwapped ? $rawDest : $rawPickup;
    $effDest = $wasSwapped ? $rawPickup : $rawDest;

    $pickupLine = $effPickup !== '' ? "📍 Pickup: " . $effPickup . "\n" : "📍 Pickup: TBC\n";
    $destLine = $effDest !== '' ? "🎯 Destination: " . $effDest . "\n" : "🎯 Destination: TBC\n";

    $cost = isset($prebookingDetails['cost']) ? (float) $prebookingDetails['cost'] : 0;
    $costLine = $cost > 0
        ? "💰 Cost: R" . number_format($cost, 2) . "\n"
        : "💰 Cost: TBC\n";

    $notesLine = !empty($prebookingDetails['description'])
        ? "📝 Notes: " . $prebookingDetails['description'] . "\n"
        : '';

    return "*BOOKING REMINDER* 📋\n\n" .
        "Good day " . $prebookingDetails['client_name'] . ",\n\n" .
        "We have a tentative booking on record for you:\n\n" .
        "📅 Date: " . $forDate . "\n" .
        $timeLine .
        $pickupLine .
        $destLine .
        $costLine .
        $notesLine .
        "\nPlease let us know when you have all the details so we can finalise your booking. 😊\n\n" .
        "Regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
}


// --- SYSTEM VARIABLES ---

/**
 * All configurable system variables, with labels, types, and defaults.
 * Managed via maintenance/index.php and stored in the system_variables table.
 */

const SYSTEM_VARIABLES = [
    'car_rental_price' => ['label' => 'Car Rental Price (R)', 'type' => 'number', 'default' => 2600],
    'financial_months_back' => ['label' => 'Financial History (months)', 'type' => 'number', 'default' => 6],
    'apex_booking_fee_pct' => ['label' => 'Apex Booking Fee (%)', 'type' => 'number', 'default' => 0],
    'rate_per_km' => ['label' => 'Rate per Km (R)', 'type' => 'number', 'default' => 15.00],
    'rent' => ['label' => 'Rent (R/week)', 'type' => 'number', 'default' => 0],
    'debt_payment' => ['label' => 'Debt Payment (R/week)', 'type' => 'number', 'default' => 0],
    'living_expenses_daily' => ['label' => 'Living Expenses (R/day)', 'type' => 'number', 'default' => 120],
    'airport_pickup_notice' => [
        'label' => 'Cape Town Airport Pickup Notice',
        'type' => 'textarea',
        'default' => "Please ensure that your phone is on and connected after you've landed.",
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
 * airport_pickup_notice system variable (editable via maintenance/index.php)
 * — it is never written into bookings.description. It's appended here, at
 * render time only, so repeated edits/renders never duplicate it in storage.
 *
 * Used by: createWhatsAppMessage(), modules/Bookings/view.php
 *
 * @param PDO    $pdo
 * @param string $pickup  Effective pickup location — caller must already
 *                        have resolved this for was_swapped
 * @param string $notes   Raw stored notes/description text
 * @return string          Notes text ready for display
 */
function appendAirportPickupNotice(PDO $pdo, string $pickup, ?string $notes): string
{
    $notes = $notes ?? '';

    if (!isAirportPickupLocation($pickup)) {
        return $notes;
    }

    $notice = trim((string) getSystemVariable($pdo, 'airport_pickup_notice'));
    if ($notice === '' || stripos($notes, $notice) !== false) {
        return $notes;
    }

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

    $url = GOOGLE_DISTANCE_MATRIX_URL . '?' . http_build_query([
        'origins'      => $origin,
        'destinations' => $destination,
        'units'        => 'metric',
        'key'          => GOOGLE_API_KEY,
    ]);

    $responseJson = @file_get_contents($url);
    if ($responseJson === false) {
        return null;
    }

    $data = json_decode($responseJson, true);
    $element = $data['rows'][0]['elements'][0] ?? null;

    if (!$element || $element['status'] !== 'OK') {
        return null;
    }

    return round($element['distance']['value'] / 1000, 1);
}

/**
 * Build the WhatsApp message to send to an allocated driver about a booking.
 * Used by: modules/Bookings/view.php
 *
 * @param array $bookingDetails  Must contain: trip_date, start_time, client_name,
 *                               pickup_location, destination, cost, payment_method,
 *                               driver_name, and optionally booking_fee
 */
function createDriverBookingMessage(array $bookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);
    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate = $start->format('d/m/y');
    $forTime = $start->format('H:i');

    $driverName = $bookingDetails['driver_name'] ?? 'Driver';
    $cost = (float) ($bookingDetails['cost'] ?? 0);
    $isEft = ($bookingDetails['payment_method'] === 'eft');
    $noBookingFee = !empty($bookingDetails['no_booking_fee']);
    $bookingFee = (!$noBookingFee && isset($bookingDetails['booking_fee']) && $bookingDetails['booking_fee'] !== null)
        ? (float) $bookingDetails['booking_fee']
        : null;

    $msg = "Good day " . $driverName . "! 🚗\n\n";
    $msg .= "You have a booking allocated to you:\n\n";
    $msg .= "📅 Date: " . $forDate . " at " . $forTime . "\n";
    $msg .= "👤 Client: " . ($bookingDetails['client_name'] ?? '') . "\n";
    if (!empty($bookingDetails['client_phone'])) {
        $msg .= "📱 Client phone: " . $bookingDetails['client_phone'] . "\n";
    }
    $msg .= "📍 Pickup: " . ($bookingDetails['pickup_location'] ?? '') . "\n";
    $msg .= "🎯 Destination: " . ($bookingDetails['destination'] ?? '') . "\n";
    $msg .= "💰 Trip Cost: R" . number_format($cost, 2) . "\n";

    if ($noBookingFee) {
        $msg .= "\n✅ No booking fee — full amount goes to you.\n";
        if ($isEft) {
            $msg .= "📲 This is an EFT booking with no booking fee. Apex Transit will pay you after payment received from client.\n";
        } else {
            $msg .= "💵 This is a cash booking with no booking fee. No payment due to Apex Transit\n";
        }
    } elseif ($bookingFee !== null && $bookingFee > 0) {
        $msg .= "💼 Apex Booking Fee: R" . number_format($bookingFee, 2) . "\n";
        if ($isEft) {
            $msg .= "\n📲 This is an EFT booking. Apex Transit will pay you after deducting the booking fee and payment received from client.\n";
        } else {
            $msg .= "\n💵 This is a cash booking. Please pay Apex Transit the booking fee of R" . number_format($bookingFee, 2) . ".\n";
        }
    } elseif ($isEft) {
        $msg .= "\n📲 This is an EFT booking.\n";
    } else {
        $msg .= "\n💵 This is a cash booking.\n";
    }

    if (!empty($bookingDetails['driver_notes'])) {
        $msg .= "\n📝 Notes: " . $bookingDetails['driver_notes'] . "\n";
    }

    $msg .= "\nThank you! 🙏";
    $msg .= "\nDrive safe! 🚗";
    return $msg;
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
