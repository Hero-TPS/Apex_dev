<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';



$response = [
    'success' => false,
    'message' => 'An error occurred while fetching bookings.',
    'bookings' => []
];

try {
    $show = $_GET['show'] ?? 'upcoming';
    $bookings = [];

    if ($show === 'all') {
        $sql = "
            SELECT 
                b.id,
                b.trip_date,
                b.start_time,
                b.end_time,
                b.status,
                b.original_pickup,
                b.original_destination,
                b.was_swapped,
                b.cost,
                c.name AS client_name 
            FROM bookings b
            JOIN contacts c ON b.contact_id = c.id
            ORDER BY b.trip_date DESC, b.start_time DESC
            LIMIT 100
        ";
        $stmt = $pdo->query($sql);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $bookings = array_reverse($recentBookings);
    } else {
        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');
        $sql = "
            SELECT 
                b.id,
                b.trip_date,
                b.start_time,
                b.end_time,
                b.status,
                b.original_pickup,
                b.original_destination,
                b.was_swapped,
                b.cost,
                c.name AS client_name 
            FROM bookings b
            JOIN contacts c ON b.contact_id = c.id
            WHERE b.trip_date >= ?
            ORDER BY b.trip_date ASC, b.start_time ASC
            LIMIT 100
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$today]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $now = new DateTime('now', new DateTimeZone(TIME_ZONE));
    $today_str = $now->format('Y-m-d');

    foreach ($bookings as $row) {
        $pickup = $row['was_swapped'] ? $row['original_destination'] : $row['original_pickup'];
        $destination = $row['was_swapped'] ? $row['original_pickup'] : $row['original_destination'];

        $tripDate = new DateTime($row['trip_date']);
        $isToday = ($row['trip_date'] === $today_str);
        $isPast = ($tripDate < $now && !$isToday);
        $isOverdue = $isPast && ($row['status'] !== 'completed');

        $response['bookings'][] = [
            'id' => (int)$row['id'],
            'trip_date' => date('d/m/y', strtotime($row['trip_date'])),
            'trip_date_raw' => $row['trip_date'],
            'start_time' => date('H:i', strtotime($row['start_time'])),
            'status' => $row['status'],
            'is_overdue' => $isOverdue,
            'is_today' => $isToday,
            'is_past' => $isPast,
            'pickup_location' => $pickup,
            'destination' => $destination,
            'cost' => 'R' . number_format((float)$row['cost'], 2),
            'client_name' => $row['client_name']
        ];
    }

    $response['success'] = true;
    $response['message'] = count($bookings) > 0 
        ? 'Bookings retrieved successfully.' 
        : 'No bookings found.';

} catch (PDOException $e) {
    error_log('get_bookings error: ' . $e->getMessage());
    $response['message'] = 'Database error occurred.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>