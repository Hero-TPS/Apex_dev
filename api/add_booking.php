<?php

// api/add_booking.php

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }

    // Get values
    $contact_id = $_POST['contact_id'] ?? '';
    $trip_date = $_POST['trip_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $original_pickup = $_POST['original_pickup'] ?? '';
    $original_destination = $_POST['original_destination'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';

    // Handle "Other" fields
    if ($original_pickup === 'other') {
        $original_pickup = $_POST['other_original_pickup'] ?? '';
    }
    if ($original_destination === 'other') {
        $original_destination = $_POST['other_original_destination'] ?? '';
    }
    if ($cost === 'other') {
        $cost = $_POST['other_cost'] ?? '';
    }

    $flight_number = $_POST['flight_number'] ?? '';
    $description = $_POST['description'] ?? '';

    // Validate critical non-empty fields (HTML5 should prevent this, but be safe)
    if (empty($contact_id))
        throw new Exception('Client not selected');
    if (empty($trip_date))
        throw new Exception('Trip date is required');
    if (empty($start_time))
        throw new Exception('Start time is required');
    if (empty($duration) || !is_numeric($duration))
        throw new Exception('Valid duration is required');
    if (empty($original_pickup))
        throw new Exception('Pickup location is required');
    if (empty($original_destination))
        throw new Exception('Destination is required');
    if (empty($cost))
        throw new Exception('Cost is required');

    // Calculate end_time safely
    $start = new DateTime($start_time);
    $start->modify("+" . (float) $duration . " hours");
    $end_time = $start->format('H:i:s');

// Add new destination to list if requested
    if ($original_destination === 'other' && isset($_POST['add_to_destinations'])) {
        $newDestination = trim($_POST['other_original_destination'] ?? '');
        if (!empty($newDestination)) {
            // Prevent duplicates
            $check = $pdo->prepare("SELECT id FROM destinations WHERE name = ? LIMIT 1");
            $check->execute([$newDestination]);
            if (!$check->fetch()) {
                $insertDest = $pdo->prepare("INSERT INTO destinations (name) VALUES (?)");
                $insertDest->execute([$newDestination]);
            }
        }
    }

    // Insert (omit date_created — it defaults to current timestamp)
    $stmt = $pdo->prepare("
        INSERT INTO bookings (
            contact_id, trip_date, start_time, end_time,
            original_pickup, original_destination, cost, payment_method,
            flight_number, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $contact_id,
        $trip_date,
        $start_time,
        $end_time,
        $original_pickup,
        $original_destination,
        $cost,
        $payment_method,
        $flight_number,
        $description
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}