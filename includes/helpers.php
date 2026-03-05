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
    // ✅ Use configured timezone
    $timezone = new DateTimeZone(TIME_ZONE);
    
    $start = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], $timezone);
    $forDate = $start->format('d/m/y');
    $startTime = $start->format('H:i');
    $flightInfo = !empty($bookingDetails['flight_number']) ? "✈️ Flight Number: " . $bookingDetails['flight_number'] . "\n" : '';
    $costInfo = $bookingDetails['cost'] > 0 ? "💰 Cost: R" . number_format($bookingDetails['cost'], 2) . "\n" : '';
    $notesInfo = !empty($bookingDetails['description']) ? "📝 Notes: " . $bookingDetails['description'] . "\n" : '';

    // ✅ Check if booking was updated (has updated_at timestamp)
    $isUpdate = !empty($bookingDetails['updated_at']);
    $title = $isUpdate ? "*BOOKING UPDATED AND CONFIRMED* ✅\n\n" : "*BOOKING CONFIRMED* ✅\n\n";

    // ✅ Add timestamp notice - created or updated (convert from UTC to local timezone)
    $timestampInfo = '';
    if ($isUpdate) {
        // Create DateTime in UTC (server timezone), then convert to local
        $updatedDate = new DateTime($bookingDetails['updated_at'], new DateTimeZone('UTC'));
        $timestampInfo = "\n✏️ Updated: " . $updatedDate->format('d/m/y H:i') . "\n";
    } elseif (!empty($bookingDetails['date_created'])) {
        // Create DateTime in UTC (server timezone), then convert to local
        $createdDate = new DateTime($bookingDetails['date_created'], new DateTimeZone('UTC'));
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

/**
 * Build WhatsApp URL
 */
function buildWhatsAppUrl($phone, $message) {
    $cleanPhone = formatPhoneNumberForWhatsApp($phone);
    if (!$cleanPhone) {
        return '#';
    }
    return 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($message);
}

/**
 * Sanitize filename for safe storage
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename);
    return $filename;
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get file extension from filename
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file type is allowed
 */
function isAllowedFileType($filename, array $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']) {
    $ext = getFileExtension($filename);
    return in_array($ext, $allowedTypes, true);
}

/**
 * Generate a random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get current page URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . '://' . $host . $uri;
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Escape HTML entities
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Debug helper - dump and die
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * Get a system variable value from the database.
 * Returns $default if the variable is not found.
 */
const SYSTEM_VARIABLES = [
    'car_rental_price'      => ['label' => 'Car Rental Price (R)',      'type' => 'number', 'default' => 2600],
    'financial_months_back' => ['label' => 'Financial History (months)', 'type' => 'number', 'default' => 6],
];

function getSystemVariable(PDO $pdo, string $name) {
    $stmt = $pdo->prepare("SELECT value FROM system_variables WHERE name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetchColumn();
    return ($result !== false) ? $result : (SYSTEM_VARIABLES[$name]['default'] ?? null);
}