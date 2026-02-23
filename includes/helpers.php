<?php
// --- DATA FETCHING FUNCTIONS ---

/**
 * Fetch all rows from a table
 * ⚠️ Only call with hardcoded, trusted table/column names!
 */
function fetchData(PDO $pdo, string $tableName, string $orderBy): array
{
    // Basic ORDER BY validation: column name (+ optional ASC/DESC)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
        error_log("fetchData: Invalid ORDER BY: '$orderBy'");
        return [];
    }

    $sql = "SELECT * FROM `$tableName` ORDER BY $orderBy";

    try {
        return $pdo->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log("fetchData failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch a single column from a table
 * ⚠️ Only call with hardcoded, trusted table/column names!
 */
function fetchColumn(PDO $pdo, string $tableName, string $columnName, string $orderBy): array
{
    // Validate ORDER BY (same as above)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\s+(ASC|DESC))?$/i', $orderBy)) {
        error_log("fetchColumn: Invalid ORDER BY: '$orderBy'");
        return [];
    }

    $sql = "SELECT `$columnName` FROM `$tableName` ORDER BY $orderBy";

    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("fetchColumn failed: " . $e->getMessage());
        return [];
    }
}
/**
 * Generate 15-minute time options for drop downs
 */
function generateTimeOptions() {
    $times = [];
    for ($hour = 0; $hour < 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 15) {
            $times[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }
    return $times;
}

// --- FORMATTING & MESSAGING FUNCTIONS ---

function formatPhoneNumberForWhatsApp($rawPhone) {
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

    // Apply local formatting logic
    if (substr($phone, 0, 1) === '0') {
        $phone = WHATSAPP_COUNTRY_CODE . substr($phone, 1);
    } elseif (substr($phone, 0, strlen(WHATSAPP_COUNTRY_CODE)) !== WHATSAPP_COUNTRY_CODE) {
        $phone = WHATSAPP_COUNTRY_CODE . $phone;
    }
    // Else: already starts with country code → keep as-is

    // Now check: is this a local (ZA) number? Only then apply mobile validation
    if (substr($phone, 0, strlen(WHATSAPP_COUNTRY_CODE)) === WHATSAPP_COUNTRY_CODE) {
        // South African number → validate length and mobile prefix
        if (strlen($phone) !== 11) { // '27' + 9 digits = 11 total
            return '';
        }
        $thirdDigit = $phone[2] ?? ''; // index 0='2', 1='7', 2=first national digit
        if (!in_array($thirdDigit, ['6', '7', '8'], true)) {
            return ''; // Landline or non-mobile
        }
    }
    // Else: foreign number (e.g., 44..., 1..., 91...) → accept without validation

    return $phone;
}

/**
 * Create WhatsApp booking confirmation message
 * Automatically detects if booking was updated by checking updated_at timestamp
 */
function createWhatsAppMessage($bookingDetails) {
    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time']);
    $forDate = $start->format('d/m/y');
    $startTime = $start->format('H:i');
    $flightInfo = !empty($bookingDetails['flight_number']) ? "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n" : '';
    $costInfo = $bookingDetails['cost'] > 0 ? "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n" : '';
    $notesInfo = !empty($bookingDetails['description']) ? "📝 Notes: " . $bookingDetails['description'] . "\n" : '';

    // ✅ Check if booking was updated (has updated_at timestamp)
    $isUpdate = !empty($bookingDetails['updated_at']);
    $title = $isUpdate ? "*BOOKING UPDATED AND CONFIRMED* ✅\n\n" : "*BOOKING CONFIRMED* ✅\n\n";

    // ✅ Add timestamp notice - created or updated
    $timestampInfo = '';
    if ($isUpdate) {
        $updatedDate = new DateTime($bookingDetails['updated_at']);
        $timestampInfo = "\n[Updated: " . $updatedDate->format('d/m/y H:i') . "]\n";
    } elseif (!empty($bookingDetails['date_created'])) {
        $createdDate = new DateTime($bookingDetails['date_created']);
        $timestampInfo = "\n[Created: " . $createdDate->format('d/m/y H:i') . "]\n";
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
/**
 * Create WhatsApp thank you message
 */
function createThankYouMessage($bookingDetails) {
    $invoice_url = WEB_APP_URL . "/invoice.php?id=" . $bookingDetails['id'];
    return "Good day " . $bookingDetails['client_name'] . ",\n\n" .
           "Thank you for your recent trip with " . BUSINESS_NAME . "! 👍\n\n" .
           "Link to your invoice for your records:\n" . $invoice_url . "\n\n" .
           "Looking forward to be of service to you again soon! 🚗";
}

/**
 * Create Google Calendar event description
 */
function createEventDescription($bookingDetails) {
    $phone = formatPhoneNumberForWhatsApp($bookingDetails['client_phone']);
    $detail_view_url = WEB_APP_URL . "/BookingDetail.php?id=" . $bookingDetails['id'];

    $description = "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n";
    $description .= "📞 Phone: " . $phone . "\n\n";
    $description .= "📍 Pickup: " . $bookingDetails['pickup_location'] . "\n";
    $description .= "🎯 Destination: " . $bookingDetails['destination'] . "\n\n";

    if (!empty($bookingDetails['flight_number'])) {
        $description .= "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n";
    }
    if (!empty($bookingDetails['description'])) {
        $description .= "Notes: " . $bookingDetails['description'] . "\n";
    }

    $description .= "\n---\n";
    $description .= "🔗 View Full Booking Details:\n" . $detail_view_url;

    return $description;
}

function getSystemVariable($pdo, $name) {
    $stmt = $pdo->prepare("SELECT value FROM system_variables WHERE name = ?");
    $stmt->execute([$name]);
    return $stmt->fetchColumn();
}

function dd($value) {
    echo '<pre>';
    print_r($value);
    echo '</pre>';
    die();
}


