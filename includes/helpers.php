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
 * Build the WhatsApp booking confirmation message body.
 * Detects new vs. updated booking from the presence of updated_at.
 * Used by: modules/Bookings/view.php
 *
 * @param array $bookingDetails  Must contain: trip_date, start_time, client_name,
 *                               pickup_location, destination, cost, and optionally
 *                               flight_number, description, updated_at, date_created
 */
function createWhatsAppMessage(array $bookingDetails): string
{
    $timezone = new DateTimeZone(TIME_ZONE);

    $start     = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate   = $start->format('d/m/y');
    $startTime = $start->format('H:i');

    $flightInfo = !empty($bookingDetails['flight_number'])
        ? "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n" : '';
    $costInfo = $bookingDetails['cost'] > 0
        ? "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n" : '';
    $notesInfo = !empty($bookingDetails['description'])
        ? "📝 Notes: " . $bookingDetails['description'] . "\n" : '';

    $isUpdate = !empty($bookingDetails['updated_at']);
    $title    = $isUpdate ? "*BOOKING UPDATED AND CONFIRMED* ✅\n\n" : "*BOOKING CONFIRMED* ✅\n\n";

    // Timestamp line (value from DB is already SAST — no conversion needed)
    $timestampInfo = '';
    if ($isUpdate) {
        $updatedDate   = new DateTime($bookingDetails['updated_at'], new DateTimeZone(TIME_ZONE));
        $timestampInfo = "\n✏️ Updated: " . $updatedDate->format('d/m/y H:i') . "\n";
    } elseif (!empty($bookingDetails['date_created'])) {
        $createdDate   = new DateTime($bookingDetails['date_created'], new DateTimeZone(TIME_ZONE));
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
           $timestampInfo .
           "\n🚗 Looking forward to being of service to you. 👍\n\n" .
           "Regards,\n" . BUSINESS_OWNER . "\n" . BUSINESS_NAME;
}


// --- SYSTEM VARIABLES ---

/**
 * All configurable system variables, with labels, types, and defaults.
 * Managed via maintenance/index.php and stored in the system_variables table.
 */
const SYSTEM_VARIABLES = [
    'car_rental_price'      => ['label' => 'Car Rental Price (R)',       'type' => 'number', 'default' => 2600],
    'financial_months_back' => ['label' => 'Financial History (months)', 'type' => 'number', 'default' => 6],
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