<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Now use ROOT_DIR for all other includes
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/bookings.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // 🔹 Update payment method
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'update_payment') {
        $booking_id = intval($_REQUEST['id'] ?? 0);
        $payment_method = $_REQUEST['payment_method'] ?? 'cash';

        if ($booking_id <= 0 || !in_array($payment_method, ['cash', 'eft'])) {
            throw new Exception('Invalid booking ID or payment method.');
        }

        $stmt = $pdo->prepare("UPDATE bookings SET payment_method = ? WHERE id = ?");
        $stmt->execute([$payment_method, $booking_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Booking not found.');
        }

        $response = [
            'success' => true,
            'message' => 'Payment method updated.'
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔹 Update status
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'update_status') {
        $booking_id = intval($_REQUEST['id'] ?? 0);
        $status = $_REQUEST['status'] ?? '';

        if ($booking_id <= 0 || !in_array($status, ['confirmed', 'completed'])) {
            throw new Exception('Invalid booking ID or status.');
        }

        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$status, $booking_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Booking not found.');
        }

        $response = [
            'success' => true,
            'message' => 'Status updated to "' . htmlspecialchars($status) . '".'
        ];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔹 Full booking update
    if (isset($_REQUEST['booking_id'])) {
        $booking_id = intval($_REQUEST['booking_id'] ?? 0);
        $contact_id = intval($_REQUEST['contact_id'] ?? 0);
        $trip_date = trim($_REQUEST['trip_date'] ?? '');
        $start_time = trim($_REQUEST['start_time'] ?? '');
        $duration = floatval($_REQUEST['duration'] ?? 1);
        $pickup_location = trim($_REQUEST['original_pickup'] ?? '');
        $other_pickup_location = trim($_REQUEST['other_pickup_location'] ?? '');
        $destination = trim($_REQUEST['original_destination'] ?? '');
        $other_destination = trim($_REQUEST['other_destination'] ?? '');
        $cost = trim($_REQUEST['cost'] ?? '');
        $other_cost = trim($_REQUEST['other_cost'] ?? '');
        $flight_number = trim($_REQUEST['flight_number'] ?? '');
        $description_input = trim($_REQUEST['description'] ?? '');
        $swap_locations = isset($_REQUEST['swap_locations']);
        $payment_method = ($_REQUEST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash'; // ✅

        $original_pickup = ($pickup_location === 'other') ? $other_pickup_location : $pickup_location;
        $original_destination = ($destination === 'other') ? $other_destination : $destination;
        $final_cost = ($cost === 'other') ? floatval($other_cost) : floatval($cost);

        if ($booking_id <= 0 || $contact_id <= 0 || !$trip_date || !$start_time || !$original_pickup || !$original_destination) {
            $response['message'] = '❌ Please fill in all required fields.';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $start_datetime = new DateTime($trip_date . ' ' . $start_time, new DateTimeZone(TIME_ZONE));
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . ($duration * 60) . 'M'));
        $end_time_formatted = $end_datetime->format('H:i:s');

        // Fetch current data for change detection
        $stmt_fetch = $pdo->prepare("
            SELECT contact_id, trip_date, start_time, end_time, original_pickup, original_destination, 
                   was_swapped, cost, flight_number, description, payment_method
            FROM bookings 
            WHERE id = ?
        ");
        $stmt_fetch->execute([$booking_id]);
        $current = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            throw new Exception('Booking not found.');
        }

        // Check for actual changes
        $changed = (
            $current['contact_id'] != $contact_id ||
            $current['trip_date'] != $trip_date ||
            $current['start_time'] != $start_time ||
            $current['end_time'] != $end_time_formatted ||
            $current['original_pickup'] != $original_pickup ||
            $current['original_destination'] != $original_destination ||
            $current['was_swapped'] != ($swap_locations ? 1 : 0) ||
            $current['cost'] != $final_cost ||
            $current['flight_number'] != $flight_number ||
            $current['description'] != $description_input ||
            $current['payment_method'] != $payment_method // ✅
        );

        $description_to_save = $description_input;
        if ($changed) {
            $timestamp = date('Y-m-d H:i');
            $description_to_save .= "\n\n[Updated on {$timestamp} via admin]";
        }

        // Update booking
        $sql = "UPDATE bookings SET 
                    contact_id = ?, trip_date = ?, start_time = ?, end_time = ?,
                    original_pickup = ?, original_destination = ?, was_swapped = ?,
                    cost = ?, flight_number = ?, description = ?, payment_method = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $contact_id, $trip_date, $start_time, $end_time_formatted,
            $original_pickup, $original_destination, $swap_locations ? 1 : 0,
            $final_cost, $flight_number, $description_to_save, $payment_method, // ✅
            $booking_id
        ]);

        // Fetch updated booking
        $stmt = $pdo->prepare("SELECT b.*, c.name AS client_name, c.phone AS client_phone FROM bookings b JOIN contacts c ON b.contact_id = c.id WHERE b.id = ?");
        $stmt->execute([$booking_id]);
        $bookingDetails = $stmt->fetch();

        if (!$bookingDetails) {
            throw new Exception('Booking not found after update.');
        }

        $pickup = $bookingDetails['was_swapped'] ? $bookingDetails['original_destination'] : $bookingDetails['original_pickup'];
        $destination = $bookingDetails['was_swapped'] ? $bookingDetails['original_pickup'] : $bookingDetails['original_destination'];
        $bookingDetails['pickup_location'] = $pickup;
        $bookingDetails['destination'] = $destination;

        // Update Google Calendar if linked
        if (!empty($bookingDetails['google_calendar_event_id'])) {
            updateBookingInGoogleCalendar($bookingDetails, $start_datetime, $end_datetime);
        }

        $response['success'] = true;
        $response['message'] = "✅ Booking for '" . htmlspecialchars($bookingDetails['client_name'], ENT_QUOTES) . "' updated.";
        $fullMessage = createWhatsAppMessage($bookingDetails);
        $response['whatsapp'] = "https://wa.me/" . formatPhoneNumberForWhatsApp($bookingDetails['client_phone']) . "?text=" . urlencode($fullMessage);
        $response['fullMessage'] = $fullMessage;

    } else {
        $response['message'] = 'Invalid request. No booking ID received.';
    }

} catch (Exception $e) {
    error_log('Update booking error: ' . $e->getMessage());
    $response['message'] = '❌ Update failed.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>