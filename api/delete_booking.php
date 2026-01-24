<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../google-auth.php';
require_once __DIR__ . '/../includes/bookings.php'; // Contains getBookingById(), deleteBookingFromDb(), etc.

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id'])) {
    $response['message'] = 'Invalid request or missing booking ID.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$bookingId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($bookingId === false || $bookingId <= 0) {
    $response['message'] = 'Invalid booking ID.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Fetch full booking (including Google event ID)
    $booking = getBookingById($pdo, $bookingId);
    if (!$booking) {
        $response['message'] = "⚠️ Booking not found or already deleted.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Delete from database
    $deleted = deleteBookingFromDb($pdo, $bookingId);
    if (!$deleted) {
        $response['message'] = "⚠️ Booking could not be deleted (may have already been removed).";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Delete from Google Calendar if linked
    $googleEventId = $booking['google_calendar_event_id'] ?? null;
    $calendarDeleted = false;
    if (!empty($googleEventId)) {
        $calendarDeleted = deleteBookingFromGoogleCalendar($googleEventId);
    }

    // Build success message
    $message = "✅ Booking deleted from database.";
    if (!empty($googleEventId)) {
        $message .= $calendarDeleted 
            ? " Google Calendar event also deleted." 
            : " Failed to delete calendar event (it may have been removed manually).";
    }

    $response['success'] = true;
    $response['message'] = $message;

} catch (PDOException $e) {
    error_log('Database error in delete_booking: ' . $e->getMessage());
    $response['message'] = '❌ A database error occurred while deleting the booking.';
} catch (Exception $e) {
    error_log('General error in delete_booking: ' . $e->getMessage());
    $response['message'] = '❌ An unexpected error occurred.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);