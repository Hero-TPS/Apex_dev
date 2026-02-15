<?php
// modules/Bookings/api/index.php
// Unified Bookings API router - combines add, get, update, delete actions

// Bootstrap: require config (three levels up from modules/Bookings/api)
require_once __DIR__ . '/../../../config.php';

// Shared helpers and booking logic
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/bookings.php';

// Set JSON header
header('Content-Type: application/json');

// Response helpers
function jsonResponse(array $payload, int $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Determine action
$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Infer action from method if not explicit
if (!$action) {
    if ($method === 'GET') $action = 'get';
    elseif ($method === 'POST') $action = 'add';
    elseif ($method === 'PUT' || $method === 'PATCH') $action = 'update';
    elseif ($method === 'DELETE') $action = 'delete';
}

// Dispatch
try {
    switch ($action) {
        case 'get':
            handleGetBookings();
            break;
        case 'add':
        case 'create':
            handleAddBooking();
            break;
        case 'update':
        case 'update_payment':
        case 'update_status':
            handleUpdateBooking();
            break;
        case 'delete':
            handleDeleteBooking();
            break;
        
        // NEW: Weekly bookings report
        case 'weekly_bookings':
            handleWeeklyBookings();
            break;
        
        // NEW: Monthly bookings report
        case 'monthly_bookings':
            handleMonthlyBookings();
            break;
        
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action: ' . ($action ?? 'none')], 400);
    }
} catch (Exception $e) {
    error_log('Bookings API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

// ========== HANDLERS ==========

function handleGetBookings() {
    global $pdo;
    
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

    jsonResponse($response);
}

function handleAddBooking() {
    global $pdo;

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

        // Validate
        if (empty($contact_id)) throw new Exception('Client not selected');
        if (empty($trip_date)) throw new Exception('Trip date is required');
        if (empty($start_time)) throw new Exception('Start time is required');
        if (empty($duration) || !is_numeric($duration)) throw new Exception('Valid duration is required');
        if (empty($original_pickup)) throw new Exception('Pickup location is required');
        if (empty($original_destination)) throw new Exception('Destination is required');
        if (empty($cost)) throw new Exception('Cost is required');

        // Calculate end_time
        $start = new DateTime($start_time);
        $start->modify("+" . (float) $duration . " hours");
        $end_time = $start->format('H:i:s');

        // Add new destination to list if requested
        if ($original_destination === 'other' && isset($_POST['add_to_destinations'])) {
            $newDestination = trim($_POST['other_original_destination'] ?? '');
            if (!empty($newDestination)) {
                $check = $pdo->prepare("SELECT id FROM destinations WHERE name = ? LIMIT 1");
                $check->execute([$newDestination]);
                if (!$check->fetch()) {
                    $insertDest = $pdo->prepare("INSERT INTO destinations (name) VALUES (?)");
                    $insertDest->execute([$newDestination]);
                }
            }
        }

        // Insert booking
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

        jsonResponse([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleUpdateBooking() {
    global $pdo;

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        // Sub-action: update payment method
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

            jsonResponse(['success' => true, 'message' => 'Payment method updated.']);
        }

        // Sub-action: update status
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

            jsonResponse([
                'success' => true,
                'message' => 'Status updated to "' . htmlspecialchars($status) . '".'
            ]);
        }

        // Full booking update
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
            $payment_method = ($_REQUEST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash';

            $original_pickup = ($pickup_location === 'other') ? $other_pickup_location : $pickup_location;
            $original_destination = ($destination === 'other') ? $other_destination : $destination;
            $final_cost = ($cost === 'other') ? floatval($other_cost) : floatval($cost);

            if ($booking_id <= 0 || $contact_id <= 0 || !$trip_date || !$start_time || !$original_pickup || !$original_destination) {
                jsonResponse(['success' => false, 'message' => '❌ Please fill in all required fields.'], 400);
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
                $current['payment_method'] != $payment_method
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
                $final_cost, $flight_number, $description_to_save, $payment_method,
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

            jsonResponse($response);

        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid request. No booking ID received.'], 400);
        }

    } catch (Exception $e) {
        error_log('Update booking error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => '❌ Update failed.'], 400);
    }
}

function handleDeleteBooking() {
    global $pdo;

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid request or missing booking ID.'], 400);
    }

    $bookingId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($bookingId === false || $bookingId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid booking ID.'], 400);
    }

    try {
        // Fetch full booking (including Google event ID)
        $booking = getBookingById($pdo, $bookingId);
        if (!$booking) {
            jsonResponse(['success' => false, 'message' => "⚠️ Booking not found or already deleted."], 404);
        }

        // Delete from database
        $deleted = deleteBookingFromDb($pdo, $bookingId);
        if (!$deleted) {
            jsonResponse(['success' => false, 'message' => "⚠️ Booking could not be deleted (may have already been removed)."], 404);
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

        jsonResponse(['success' => true, 'message' => $message]);

    } catch (PDOException $e) {
        error_log('Database error in delete_booking: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => '❌ A database error occurred while deleting the booking.'], 500);
    } catch (Exception $e) {
        error_log('General error in delete_booking: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => '❌ An unexpected error occurred.'], 500);
    }
}

// ========== NEW: REPORTS HANDLERS ==========

function handleWeeklyBookings() {
    global $pdo;

    try {
        $sql = "
            SELECT 
                YEAR(trip_date) as year,
                WEEK(trip_date, 1) as week_number,
                MIN(trip_date) as week_start,
                MAX(trip_date) as week_end,
                COUNT(*) as booking_count,
                SUM(cost) as total_income
            FROM bookings
            WHERE trip_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
            GROUP BY YEAR(trip_date), WEEK(trip_date, 1)
            ORDER BY year DESC, week_number DESC
        ";
        
        $stmt = $pdo->query($sql);
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formatted = array_map(function($week) {
            $start = new DateTime($week['week_start']);
            $end = new DateTime($week['week_end']);
            return [
                'week_label' => $start->format('d M') . ' - ' . $end->format('d M Y'),
                'booking_count' => (int)$week['booking_count'],
                'total_income' => (float)$week['total_income']
            ];
        }, $weeks);
        
        jsonResponse([
            'success' => true,
            'data' => $formatted
        ]);

    } catch (PDOException $e) {
        error_log('Weekly bookings report error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

function handleMonthlyBookings() {
    global $pdo;

    try {
        $currentYear = date('Y');
        
        $sql = "
            SELECT 
                MONTH(trip_date) as month_number,
                MONTHNAME(trip_date) as month_name,
                COUNT(*) as booking_count,
                SUM(cost) as total_income
            FROM bookings
            WHERE YEAR(trip_date) = ?
            GROUP BY MONTH(trip_date), MONTHNAME(trip_date)
            ORDER BY month_number ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$currentYear]);
        $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formatted = array_map(function($month) {
            return [
                'month_label' => $month['month_name'],
                'booking_count' => (int)$month['booking_count'],
                'total_income' => (float)$month['total_income']
            ];
        }, $months);
        
        jsonResponse([
            'success' => true,
            'data' => $formatted
        ]);

    } catch (PDOException $e) {
        error_log('Monthly bookings report error: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}