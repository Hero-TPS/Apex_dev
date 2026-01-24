<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

$response = ['success' => false, 'message' => 'An error occurred.'];

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);

    // Fetch all necessary booking details for the message
    $sql = "SELECT b.*, c.name as client_name, c.phone as client_phone 
            FROM bookings b 
            JOIN contacts c ON b.contact_id = c.id 
            WHERE b.id = ?";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $bookingId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($bookingDetails = $result->fetch_assoc()) {
                
                // Calculate final locations for the message
                $bookingDetails['pickup_location'] = $bookingDetails['was_swapped'] ? $bookingDetails['original_destination'] : $bookingDetails['original_pickup'];
                $bookingDetails['destination'] = $bookingDetails['was_swapped'] ? $bookingDetails['original_pickup'] : $bookingDetails['original_destination'];

                // Generate the WhatsApp link
                $start_datetime = new DateTime($bookingDetails['trip_date'] . ' ' . $bookingDetails['start_time']);
                $whatsapp_link = createWhatsAppLink($bookingDetails);

                $response['success'] = true;
                $response['whatsapp_link'] = $whatsapp_link;
                $response['message'] = "Link generated successfully.";

            } else {
                $response['message'] = "Booking not found.";
            }
        }
        $stmt->close();
    }
} else {
    $response['message'] = "No booking ID provided.";
}

$mysqli->close();
echo json_encode($response);
?>