<?php
// refresh_calendar_icons.php (one-off, delete after running)
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Bookings/helpers.php';

$stmt = $pdo->prepare("
    SELECT b.*, c.name AS client_name, c.phone AS client_phone,
           d.name AS driver_name, d.phone AS driver_phone
    FROM bookings b
    JOIN contacts c ON b.contact_id = c.id
    LEFT JOIN drivers d ON b.driver_id = d.id
    WHERE b.trip_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
      AND b.google_calendar_event_id IS NOT NULL
      AND b.google_calendar_event_id != ''
");
$stmt->execute();
$bookings = $stmt->fetchAll();

$updated = 0;
$failed = 0;

foreach ($bookings as $bookingDetails) {
    $pickup = $bookingDetails['was_swapped'] ? $bookingDetails['original_destination'] : $bookingDetails['original_pickup'];
    $destination = $bookingDetails['was_swapped'] ? $bookingDetails['original_pickup'] : $bookingDetails['original_destination'];
    $bookingDetails['pickup_location'] = $pickup;
    $bookingDetails['destination'] = $destination;

    $start_datetime = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time'], new DateTimeZone(TIME_ZONE));
    $end_datetime = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['end_time'], new DateTimeZone(TIME_ZONE));

    $result = updateBookingInGoogleCalendar($bookingDetails, $start_datetime, $end_datetime);

    if ($result) {
        $updated++;
    } else {
        $failed++;
    }
}

echo "Done. Updated: $updated, Failed: $failed, Total: " . count($bookings);
