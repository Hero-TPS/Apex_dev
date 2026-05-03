<?php
// =============================================================================
// includes/helpers.php — Global helper functions
// =============================================================================

// --- DATA FETCHING ---

/**
 * Fetch all rows from a table.
 * Used by: modules/Bookings/add.php, modules/Bookings/edit.php
 * ⚠️ Only call with hardcoded, trusted table/column names!
 */
function fetchData(PDO $pdo, string $tableName, string $orderBy): array
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
        error_log("fetchData: Invalid ORDER BY: '$orderBy'");
        return [];
    }
    try {
        return $pdo->query("SELECT * FROM `$tableName` ORDER BY $orderBy")->fetchAll();
    } catch (PDOException $e) {
        error_log("fetchData failed: " . $e->getMessage());
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
        error_log("fetchColumn: Invalid ORDER BY: '$orderBy'");
        return [];
    }
    try {
        return $pdo->query("SELECT `$columnName` FROM `$tableName` ORDER BY $orderBy")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("fetchColumn failed: " . $e->getMessage());
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
 * @param array $bookingDetails  Must contain: trip_date, start_time, client_name,
 *                               pickup_location, destination, cost, and optionally
 *                               flight_number, description, updated_at, date_created,
 *                               driver_name, driver_phone
 */
function createWhatsAppMessage(array $bookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);

    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate = $start->format('d/m/y');
    $startTime = $start->format('H:i');

    $flightInfo = !empty($bookingDetails['flight_number'])
        ? "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n" : '';
    $costInfo = $bookingDetails['cost'] > 0
        ? "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n" : '';
    $notesInfo = !empty($bookingDetails['description'])
        ? "📝 Notes: " . $bookingDetails['description'] . "\n" : '';

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
    // ======================================================
    // CLEANUP WA MESSAGE — edit the text below as needed
    // ======================================================
    return "Hi " . $clientName . " 👋\n\n" .
        "My name is " . BUSINESS_OWNER . ", and I have recently taken over André Matthews' personal transport services. As you may know, André has retired and relocated to Namibia — we wish him well!\n\n" .
        "André kindly shared his client list with me, and your details were included. I wanted to reach out personally to introduce myself and let you know that I am available should you ever need a reliable transport service.\n\n" .
        "I want to assure you that this is a once-off introduction message. I will not contact you again unless you choose to reach out to me — no follow-ups, no further messages.\n\n" .
        "I would love to hear from you. Could you perhaps let me know if you are still in the area and whether transport assistance is something you may need from time to time?\n\n" .
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