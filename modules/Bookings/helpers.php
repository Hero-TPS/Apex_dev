<?php
/**
 * Booking helper functions
 * Assumes: 
 * - $pdo is available via dependency injection
 * - TIME_ZONE and CUSTOM_CALENDAR_ID are defined in config
 * - getGoogleAccessToken() is available
 */

/**
 * Fetch a full booking record by ID (with contact info)
 */
function getBookingById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $sql = "
        SELECT 
            b.*, 
            c.name AS client_name,
            c.phone AS client_phone
        FROM bookings b
        JOIN contacts c ON b.contact_id = c.id
        WHERE b.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Delete a booking from the database by ID
 * Returns true if a row was deleted, false otherwise
 */
function deleteBookingFromDb(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Delete a Google Calendar event by event ID
 * Returns true on successful deletion (HTTP 204), false otherwise
 */
function deleteBookingFromGoogleCalendar(string $eventId): bool
{
    if (empty($eventId)) {
        return false;
    }

    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        return false;
    }

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(CUSTOM_CALENDAR_ID) . '/events/' . urlencode($eventId);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 10
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 204) {
        logWarning('CALENDAR', 'Failed to delete calendar event', [
            'event_id' => $eventId,
            'http_code' => $httpCode
        ]);
        return false;
    }

    return true;
}

/**
 * Update Google Calendar event for a booking (e.g., after edit)
 * Returns event ID on success, null on failure
 */
function updateBookingInGoogleCalendar(array $bookingData, DateTime $start, DateTime $end): ?string
{
    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        return null;
    }

    $eventData = [
        'summary' => '🚗 ' . $bookingData['client_name'] . ' - R' . number_format($bookingData['cost'], 2),
        'location' => $bookingData['was_swapped'] ? $bookingData['original_destination'] : $bookingData['original_pickup'],
        'description' => createEventDescription($bookingData),
        'start' => [
            'dateTime' => $start->format(DateTime::RFC3339),
            'timeZone' => defined('TIME_ZONE') ? TIME_ZONE : 'UTC'
        ],
        'end' => [
            'dateTime' => $end->format(DateTime::RFC3339),
            'timeZone' => defined('TIME_ZONE') ? TIME_ZONE : 'UTC'
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [['method' => 'popup', 'minutes' => 60]]
        ]
    ];

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(CUSTOM_CALENDAR_ID) . '/events/' . urlencode($bookingData['google_calendar_event_id']);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($eventData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $response = json_decode($result, true);
        return $response['id'] ?? null;
    } else {
        logError('CALENDAR', 'Failed to update calendar event', [
            'http_code' => $httpCode,
            'response' => $result,
            'booking_id' => $bookingData['id'] ?? null,
            'event_id' => $bookingData['google_calendar_event_id'] ?? null
        ]);
        return null;
    }
}

/**
 * Create a new Google Calendar event for a booking
 * Returns event ID on success, null on failure
 */
function createBookingInGoogleCalendar(array $bookingData, DateTime $start, DateTime $end): ?string
{
    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        return null;
    }

    $eventData = [
        'summary' => '🚗 ' . $bookingData['client_name'] . ' - R' . number_format($bookingData['cost'], 2),
        'location' => $bookingData['was_swapped'] ? $bookingData['original_destination'] : $bookingData['original_pickup'],
        'description' => createEventDescription($bookingData),
        'start' => [
            'dateTime' => $start->format(DateTime::RFC3339),
            'timeZone' => defined('TIME_ZONE') ? TIME_ZONE : 'UTC'
        ],
        'end' => [
            'dateTime' => $end->format(DateTime::RFC3339),
            'timeZone' => defined('TIME_ZONE') ? TIME_ZONE : 'UTC'
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [['method' => 'popup', 'minutes' => 60]]
        ]
    ];

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(CUSTOM_CALENDAR_ID) . '/events';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($eventData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $response = json_decode($result, true);
        return $response['id'] ?? null;
    } else {
        logError('CALENDAR', 'Failed to create calendar event', [
            'http_code' => $httpCode,
            'response' => $result,
            'booking_id' => $bookingData['id'] ?? null,
            'client' => $bookingData['client_name'] ?? null
        ]);
        return null;
    }
}

function createEventDescription(array $bookingData): string
{
    $pickup = $bookingData['was_swapped'] ? $bookingData['original_destination'] : $bookingData['original_pickup'];
    $destination = $bookingData['was_swapped'] ? $bookingData['original_pickup'] : $bookingData['original_destination'];

    $description = "📍 Pickup: " . $pickup . "\n";
    $description .= "🎯 Destination: " . $destination . "\n";
    $description .= "💰 Cost: R" . number_format($bookingData['cost'], 2) . "\n";
    $description .= "💳 Payment: " . ($bookingData['payment_method'] === 'eft' ? 'EFT' : 'Cash') . "\n";

    if (!empty($bookingData['client_phone'])) {
        $description .= "📞 Phone: " . $bookingData['client_phone'] . "\n";
    }
    if (!empty($bookingData['flight_number'])) {
        $description .= "✈️ Flight: " . $bookingData['flight_number'] . "\n";
    }
    if (!empty($bookingData['description'])) {
        $description .= "\n📝 Notes: " . $bookingData['description'] . "\n";
    }

    // Link back to booking view
    if (!empty($bookingData['id'])) {
        $description .= "\n🔗 " . BASE_URL . "/modules/Bookings/view.php?id=" . $bookingData['id'];
    }

    return $description;
}