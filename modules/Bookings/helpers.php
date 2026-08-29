<?php
// modules/Bookings/helpers.php
/**
 * Booking helper functions
 * Assumes: 
 * - $pdo is available via dependency injection
 * - TIME_ZONE and CUSTOM_CALENDAR_ID are defined in config
 * - getGoogleAccessToken() is available
 */

/**
 * For a set of trip dates, find every pair of bookings on those dates whose
 * time ranges overlap — checked against ALL bookings regardless of status or
 * driver assignment. Back-to-back bookings that only touch at the boundary
 * are not considered an overlap.
 *
 * Used by add.php/edit.php (via the check_overlap API action), view.php,
 * index.php, and reports.php so every page applies the same definition of
 * "overlap".
 *
 * @param string[] $tripDates Y-m-d dates to check
 * @return array<int, array<int, array{id:int,start_time:string,end_time:string,client_name:string,driver_name:?string}>>
 *         Keyed by booking id => list of the *other* bookings it overlaps with.
 */
function getBookingOverlapsForDates(PDO $pdo, array $tripDates): array
{
    $tripDates = array_values(array_unique(array_filter($tripDates)));
    if (empty($tripDates)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($tripDates), '?'));
    $sql = "
        SELECT
            b1.id AS booking_id,
            b2.id AS other_id,
            b2.start_time AS other_start,
            b2.end_time AS other_end,
            c2.name AS other_client,
            d2.name AS other_driver
        FROM bookings b1
        JOIN bookings b2
            ON b1.trip_date = b2.trip_date
           AND b1.id != b2.id
           AND CAST(b1.start_time AS TIME) < CAST(b2.end_time AS TIME)
           AND CAST(b1.end_time AS TIME) > CAST(b2.start_time AS TIME)
        JOIN contacts c2 ON b2.contact_id = c2.id
        LEFT JOIN drivers d2 ON b2.driver_id = d2.id
        WHERE b1.trip_date IN ($placeholders)
        ORDER BY b1.id ASC, b2.start_time ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($tripDates);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int) $row['booking_id']][] = [
            'id'          => (int) $row['other_id'],
            'start_time'  => date('H:i', strtotime($row['other_start'])),
            'end_time'    => date('H:i', strtotime($row['other_end'])),
            'client_name' => $row['other_client'],
            'driver_name' => $row['other_driver'] ?? null,
        ];
    }
    return $map;
}

/**
 * Fetch a full booking record by ID (with contact info and allocated driver)
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
            c.phone AS client_phone,
            c.pickup_lat AS client_pickup_lat,
            c.pickup_lng AS client_pickup_lng,
            d.name AS driver_name,
            d.phone AS driver_phone
        FROM bookings b
        JOIN contacts c ON b.contact_id = c.id
        LEFT JOIN drivers d ON b.driver_id = d.id
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

/**
 * Create a Google Calendar event for a prebooking (yellow/tentative).
 * Uses an all-day event if no start time is supplied, otherwise a 1-hour event.
 * Returns the Google Calendar event ID on success, null on failure.
 */
function createPrebookingInGoogleCalendar(array $data): ?string
{
    $accessToken = getGoogleAccessToken();
    if (!$accessToken) {
        return null;
    }

    $tz          = new DateTimeZone(defined('TIME_ZONE') ? TIME_ZONE : 'UTC');
    $wasSwapped  = !empty($data['was_swapped']);
    $pickup      = $wasSwapped ? ($data['original_destination'] ?? '') : ($data['original_pickup'] ?? '');
    $destination = $wasSwapped ? ($data['original_pickup'] ?? '') : ($data['original_destination'] ?? '');

    $summary = '📋 TENTATIVE: ' . $data['client_name'];
    if ($pickup !== '' && $destination !== '') {
        $summary .= ' ' . $pickup . ' → ' . $destination;
    } elseif ($destination !== '') {
        $summary .= ' → ' . $destination;
    } elseif ($pickup !== '') {
        $summary .= ' ' . $pickup . ' →';
    }

    $description = "📋 Tentative / Pre-booking\n";
    $description .= "👤 Client: " . $data['client_name'] . "\n";
    if (!empty($data['client_phone'])) {
        $description .= "📞 Phone: " . $data['client_phone'] . "\n";
    }
    if ($pickup !== '') {
        $description .= "📍 Pickup: " . $pickup . "\n";
    }
    if ($destination !== '') {
        $description .= "🎯 Destination: " . $destination . "\n";
    }
    if (!empty($data['cost'])) {
        $description .= "💰 Cost: R" . number_format((float) $data['cost'], 2) . "\n";
    }
    if (!empty($data['description'])) {
        $description .= "\n📝 Notes: " . $data['description'] . "\n";
    }
    if (!empty($data['id'])) {
        $description .= "\n🔗 " . BASE_URL . "/modules/Prebookings/?highlight=" . $data['id'];
    }

    if (!empty($data['start_time'])) {
        $start = new DateTime($data['trip_date'] . ' ' . $data['start_time'], $tz);
        $end   = clone $start;
        $end->modify('+1 hour');
        $eventData = [
            'summary'     => $summary,
            'location'    => $pickup !== '' ? $pickup : null,
            'description' => $description,
            'colorId'     => '5',
            'start'       => ['dateTime' => $start->format(DateTime::RFC3339), 'timeZone' => TIME_ZONE],
            'end'         => ['dateTime' => $end->format(DateTime::RFC3339),   'timeZone' => TIME_ZONE],
            'reminders'   => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 60]]],
        ];
    } else {
        $eventData = [
            'summary'     => $summary,
            'location'    => $pickup !== '' ? $pickup : null,
            'description' => $description,
            'colorId'     => '5',
            'start'       => ['date' => $data['trip_date']],
            'end'         => ['date' => $data['trip_date']],
            'reminders'   => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 480]]],
        ];
    }

    // Remove null keys so Google Calendar API doesn't receive them
    $eventData = array_filter($eventData, fn($v) => $v !== null);

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode(CUSTOM_CALENDAR_ID) . '/events';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($eventData),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $response = json_decode($result, true);
        return $response['id'] ?? null;
    }

    logError('CALENDAR', 'Failed to create prebooking calendar event', [
        'http_code' => $httpCode,
        'response'  => $result,
        'client'    => $data['client_name'] ?? null,
    ]);
    return null;
}

/**
 * Delete a prebooking Google Calendar event by event ID.
 * Returns true on success (HTTP 204), false otherwise.
 */
function deletePrebookingFromGoogleCalendar(string $eventId): bool
{
    return deleteBookingFromGoogleCalendar($eventId);
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
    if (!empty($bookingData['driver_name'])) {
        $description .= "🚗 Driver: " . $bookingData['driver_name'];
        if (!empty($bookingData['driver_phone'])) {
            $description .= " | " . $bookingData['driver_phone'];
        }
        $description .= "\n";
        if (!empty($bookingData['no_booking_fee'])) {
            $description .= "💼 Booking Fee: None (full amount to driver)\n";
        } elseif (!empty($bookingData['booking_fee'])) {
            $description .= "💼 Booking Fee: R" . number_format((float) $bookingData['booking_fee'], 2) . "\n";
        }
        if (!empty($bookingData['driver_notes'])) {
            $description .= "📝 Driver Notes: " . $bookingData['driver_notes'] . "\n";
        }
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
