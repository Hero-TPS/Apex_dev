<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php'; // Ensure helper is loaded

$response = ['success' => false, 'message' => 'An error occurred.'];

if (isset($_GET['id'])) {
    $bookingId = intval($_GET['id']);
    if ($bookingId <= 0) {
        $response['message'] = "Invalid booking ID.";
        echo json_encode($response);
        exit;
    }

    try {
        $sql = "SELECT c.name AS client_name, c.phone AS client_phone
                FROM bookings b
                JOIN contacts c ON b.contact_id = c.id
                WHERE b.id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();

        if ($row) {
            $client_name = $row['client_name'];
            $client_phone = $row['client_phone'];
            $invoice_url = WEB_APP_URL . "/invoice.php?id=" . $bookingId;

            $message = "Good day " . $client_name . ",\n\n" .
                       "Thank you for your recent trip with us! We appreciate your business. 👍\n\n" .
                       "Here is a link to your invoice for your records:\n" . $invoice_url . "\n\n" .
                       "We look forward to seeing you again soon! 🚗";

            $whatsapp_number = formatPhoneNumberForWhatsApp($client_phone);
            $response['success'] = true;
            $response['whatsapp_link'] = "https://wa.me/" . $whatsapp_number . "?text=" . urlencode($message);
            $response['message'] = "Link generated successfully.";
        } else {
            $response['message'] = "Booking not found.";
        }
    } catch (PDOException $e) {
        error_log('DB error: ' . $e->getMessage());
        $response['message'] = 'Database error occurred.';
    }
} else {
    $response['message'] = "No booking ID provided.";
}

echo json_encode($response);
?>