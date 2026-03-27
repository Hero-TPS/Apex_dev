<?php
// modules/Bookings/api/index.php
// Unified Bookings API router

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/../helpers.php';

requireApiLogin();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!$action) {
    if ($method === 'GET')
        $action = 'get';
    elseif ($method === 'POST')
        $action = 'add';
    elseif ($method === 'PUT' || $method === 'PATCH')
        $action = 'update';
    elseif ($method === 'DELETE')
        $action = 'delete';
}

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
        case 'update_gate_code':         // ✅ NEW
            handleUpdateGateCode();
            break;
        case 'tomorrows_bookings':
            handleTomorrowsBookings();
            break;
        case 'mark_confirmed':
            handleMarkConfirmed();
            break;
        case 'log_whatsapp':
            handleLogWhatsApp();
            break;
        case 'get_whatsapp_log':
            handleGetWhatsAppLog();
            break;
        case 'weekly_bookings':
            handleWeeklyBookings();
            break;
        case 'monthly_bookings':
            handleMonthlyBookings();
            break;
        case 'weekly_bookings_by_month':
            handleWeeklyBookingsByMonth();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action: ' . ($action ?? 'none')], 400);
    }
} catch (Exception $e) {
    logCritical('BOOKING_API', 'Unhandled exception in action: ' . $action, [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleGetBookings()
{
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
                    b.id, b.contact_id, b.trip_date, b.start_time, b.end_time, b.status,
                    b.original_pickup, b.original_destination, b.was_swapped, b.cost,
                    c.name AS client_name, c.phone AS client_phone
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
                    b.id, b.contact_id, b.trip_date, b.start_time, b.end_time, b.status,
                    b.original_pickup, b.original_destination, b.was_swapped, b.cost,
                    c.name AS client_name, c.phone AS client_phone
                FROM bookings b
                JOIN contacts c ON b.contact_id = c.id
                WHERE b.trip_date >= ? AND b.status != 'completed'
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
                'id' => (int) $row['id'],
                'contact_id' => (int) $row['contact_id'],
                'trip_date' => date('d/m/y', strtotime($row['trip_date'])),
                'trip_date_raw' => $row['trip_date'],
                'start_time' => date('H:i', strtotime($row['start_time'])),
                'status' => $row['status'],
                'is_overdue' => $isOverdue,
                'is_today' => $isToday,
                'is_past' => $isPast,
                'pickup_location' => $pickup,
                'destination' => $destination,
                'cost' => 'R' . number_format((float) $row['cost'], 2),
                'client_name' => $row['client_name'],
                'client_phone' => formatPhoneNumberForWhatsApp($row['client_phone'] ?? '')
            ];
        }

        $response['success'] = true;
        $response['message'] = count($bookings) > 0
            ? 'Bookings retrieved successfully.'
            : 'No bookings found.';

    } catch (PDOException $e) {
        logError('BOOKING', 'Database error fetching bookings', [
            'error' => $e->getMessage(),
            'show' => $show ?? 'upcoming'
        ]);
        $response['message'] = 'Database error: ' . $e->getMessage();
        $response['error_type'] = 'database';
    } catch (Exception $e) {
        logError('BOOKING', 'Error fetching bookings', [
            'error' => $e->getMessage(),
            'type' => get_class($e),
            'show' => $show ?? 'upcoming'
        ]);
        $response['message'] = 'Server error: ' . $e->getMessage();
        $response['error_type'] = 'general';
    }

    jsonResponse($response);
}

function handleAddBooking()
{
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
        $was_swapped = isset($_POST['swap_locations']) ? 1 : 0; // ✅ FIX

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

        // Calculate end_time
        $start = new DateTime($trip_date . ' ' . $start_time, new DateTimeZone(TIME_ZONE));
        $end = clone $start;
        $end->modify("+" . (float) $duration . " hours");
        $end_time = $end->format('H:i:s');

        // Add new destination to list if requested
        if (isset($_POST['add_to_destinations'])) {
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
                original_pickup, original_destination, was_swapped, cost, payment_method,
                flight_number, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $contact_id,
            $trip_date,
            $start_time,
            $end_time,
            $original_pickup,
            $original_destination,
            $was_swapped,           // ✅ FIX
            $cost,
            $payment_method,
            $flight_number,
            $description
        ]);

        $booking_id = $pdo->lastInsertId();

        // CREATE GOOGLE CALENDAR EVENT
        $googleEventId = null;
        $stmt = $pdo->prepare("
            SELECT b.*, c.name AS client_name, c.phone AS client_phone 
            FROM bookings b 
            JOIN contacts c ON b.contact_id = c.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bookingData) {
            // Apply swap for calendar event location
            $bookingData['pickup_location'] = $was_swapped ? $original_destination : $original_pickup;
            $bookingData['destination'] = $was_swapped ? $original_pickup : $original_destination;

            $googleEventId = createBookingInGoogleCalendar($bookingData, $start, $end);

            if ($googleEventId) {
                $updateStmt = $pdo->prepare("UPDATE bookings SET google_calendar_event_id = ? WHERE id = ?");
                $updateStmt->execute([$googleEventId, $booking_id]);
            } else {
                logWarning('BOOKING', 'Booking created but calendar event failed', [
                    'booking_id' => $booking_id
                ]);
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking_id' => $booking_id,
            'google_event_id' => $googleEventId
        ]);

    } catch (Exception $e) {
        logError('BOOKING', 'Failed to create booking', [
            'error' => $e->getMessage(),
            'contact_id' => $contact_id ?? null,
            'trip_date' => $trip_date ?? null
        ]);
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleUpdateBooking()
{
    global $pdo;

    try {
        // Sub-action: update payment method
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'update_payment') {
            $booking_id = intval($_REQUEST['id'] ?? 0);
            $payment_method = $_REQUEST['payment_method'] ?? 'cash';

            if ($booking_id <= 0 || !in_array($payment_method, ['cash', 'eft'])) {
                throw new Exception('Invalid booking ID or payment method.');
            }

            $stmt = $pdo->prepare("UPDATE bookings SET payment_method = ?, updated_at = NOW() WHERE id = ?");
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

            $stmt = $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
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
            $payment_method = trim($_REQUEST['payment_method'] ?? 'cash');
            $swap_locations = isset($_REQUEST['swap_locations']);

            // Handle "Other" fields
            if ($pickup_location === 'other') {
                $original_pickup = $other_pickup_location;
            } else {
                $original_pickup = $pickup_location;
            }

            if ($destination === 'other') {
                $original_destination = $other_destination;
            } else {
                $original_destination = $destination;
            }

            if ($cost === 'other') {
                $final_cost = floatval($other_cost);
            } else {
                $final_cost = floatval($cost);
            }

            if (empty($original_pickup) || empty($original_destination) || $final_cost <= 0) {
                throw new Exception('Invalid booking data');
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

            $description_to_save = $description_input;

            $sql = "UPDATE bookings SET 
                contact_id = ?, trip_date = ?, start_time = ?, end_time = ?,
                original_pickup = ?, original_destination = ?, was_swapped = ?,
                cost = ?, flight_number = ?, description = ?, payment_method = ?,
                last_confirmed_at = NULL,
                updated_at = NOW()
            WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $contact_id,
                $trip_date,
                $start_time,
                $end_time_formatted,
                $original_pickup,
                $original_destination,
                $swap_locations ? 1 : 0,
                $final_cost,
                $flight_number,
                $description_to_save,
                $payment_method,
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

            if (!empty($bookingDetails['google_calendar_event_id'])) {
                updateBookingInGoogleCalendar($bookingDetails, $start_datetime, $end_datetime);
            }

            $fullMessage = createWhatsAppMessage($bookingDetails);

            jsonResponse([
                'success' => true,
                'message' => "Booking for '" . htmlspecialchars($bookingDetails['client_name'], ENT_QUOTES) . "' updated.",
                'whatsapp' => "https://wa.me/" . formatPhoneNumberForWhatsApp($bookingDetails['client_phone']) . "?text=" . urlencode($fullMessage),
                'fullMessage' => $fullMessage
            ]);

        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid request. No booking ID received.'], 400);
        }

    } catch (Exception $e) {
        logError('BOOKING', 'Failed to update booking', [
            'error' => $e->getMessage(),
            'booking_id' => $booking_id ?? null
        ]);
        jsonResponse(['success' => false, 'message' => 'Update failed.'], 400);
    }
}

function handleDeleteBooking()
{
    global $pdo;

    if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid request or missing booking ID.'], 400);
    }

    $bookingId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($bookingId === false || $bookingId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid booking ID.'], 400);
    }

    try {
        $booking = getBookingById($pdo, $bookingId);
        if (!$booking) {
            jsonResponse(['success' => false, 'message' => "Booking not found or already deleted."], 404);
        }

        $deleted = deleteBookingFromDb($pdo, $bookingId);
        if (!$deleted) {
            jsonResponse(['success' => false, 'message' => "Booking could not be deleted."], 404);
        }

        $googleEventId = $booking['google_calendar_event_id'] ?? null;
        $calendarDeleted = false;
        if (!empty($googleEventId)) {
            $calendarDeleted = deleteBookingFromGoogleCalendar($googleEventId);
        }

        logDebug('BOOKING', 'Booking deleted', [
            'booking_id' => $bookingId,
            'had_calendar_event' => !empty($googleEventId),
            'calendar_deleted' => $calendarDeleted,
            'client' => $booking['client_name'] ?? null
        ]);

        $message = "Booking deleted from database.";
        if (!empty($googleEventId)) {
            $message .= $calendarDeleted
                ? " Google Calendar event also deleted."
                : " (Calendar event may have been removed manually)";
        }

        jsonResponse(['success' => true, 'message' => $message]);

    } catch (PDOException $e) {
        logError('BOOKING', 'Database error during deletion', [
            'error' => $e->getMessage(),
            'booking_id' => $bookingId
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred while deleting.'], 500);
    } catch (Exception $e) {
        logError('BOOKING', 'Unexpected error during deletion', [
            'error' => $e->getMessage(),
            'booking_id' => $bookingId
        ]);
        jsonResponse(['success' => false, 'message' => 'An unexpected error occurred.'], 500);
    }
}

function handleUpdateGateCode()  // ✅ NEW
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    $gate_code = trim($_POST['gate_code'] ?? '');

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid booking ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET gate_code = ? WHERE id = ?");
        $stmt->execute([$gate_code, $id]);

        jsonResponse(['success' => true, 'message' => 'Gate code saved.']);

    } catch (PDOException $e) {
        logError('BOOKING', 'Failed to save gate code', [
            'error' => $e->getMessage(),
            'booking_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to save gate code.'], 500);
    }
}

// ========== REPORTS HANDLERS ==========

function handleWeeklyBookings()
{
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

        $formatted = array_map(function ($week) {
            $start = new DateTime($week['week_start']);
            $end = new DateTime($week['week_end']);
            return [
                'week_label' => $start->format('d M') . ' - ' . $end->format('d M Y'),
                'booking_count' => (int) $week['booking_count'],
                'total_income' => (float) $week['total_income']
            ];
        }, $weeks);

        jsonResponse(['success' => true, 'data' => $formatted]);

    } catch (PDOException $e) {
        logError('BOOKING_REPORT', 'Weekly report generation failed', [
            'error' => $e->getMessage()
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

function handleMonthlyBookings()
{
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

        $formatted = array_map(function ($month) {
            return [
                'month_label' => $month['month_name'],
                'booking_count' => (int) $month['booking_count'],
                'total_income' => (float) $month['total_income']
            ];
        }, $months);

        jsonResponse(['success' => true, 'data' => $formatted]);

    } catch (PDOException $e) {
        logError('BOOKING_REPORT', 'Monthly report generation failed', [
            'error' => $e->getMessage()
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

function handleWeeklyBookingsByMonth()
{
    global $pdo;

    $year  = $_GET['year']  ?? null;
    $month = $_GET['month'] ?? null;

    if (!$year || !$month || !checkdate((int) $month, 1, (int) $year)) {
        jsonResponse(['success' => false, 'message' => 'Invalid date'], 400);
        return;
    }

    try {
        $tz       = new DateTimeZone(TIME_ZONE);
        $firstDay = new DateTime(sprintf('%04d-%02d-01', (int) $year, (int) $month), $tz);
        if ($firstDay->format('N') !== '1') {
            $firstDay->modify('next monday');
        }
        $lastDay = new DateTime(sprintf('%04d-%02d-01', (int) $year, (int) $month), $tz);
        $lastDay->modify('last day of this month');

        $data    = [];
        $current = clone $firstDay;
        while ($current <= $lastDay) {
            $monday = clone $current;
            $monday->setTime(0, 0, 0);
            $sunday = clone $monday;
            $sunday->modify('+6 days');

            $startStr = $monday->format('Y-m-d');
            $endStr   = $sunday->format('Y-m-d');

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(cost), 0) AS total_income, COUNT(*) AS booking_count
                 FROM bookings WHERE trip_date BETWEEN ? AND ?"
            );
            $stmt->execute([$startStr, $endStr]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $data[] = [
                'week_label'    => $monday->format('d M') . ' – ' . $sunday->format('d M Y'),
                'booking_count' => (int)   $row['booking_count'],
                'total_income'  => (float) $row['total_income'],
            ];

            $current->modify('+1 week');
        }

        jsonResponse(['success' => true, 'data' => $data]);

    } catch (PDOException $e) {
        logError('BOOKING_REPORT', 'Weekly-by-month report failed', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

// ========== FEATURE 1: TOMORROW'S BOOKINGS ==========

/**
 * Ensure the whatsapp-related schema additions exist.
 * Safe to call on every request; uses IF NOT EXISTS guards.
 */
function ensureWhatsAppSchema(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS last_confirmed_at DATETIME NULL DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists or DB does not support IF NOT EXISTS — ignore
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NULL,
                contact_id INT NULL,
                message_type VARCHAR(50) NOT NULL DEFAULT 'custom',
                message_content TEXT NOT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_by VARCHAR(100) NOT NULL DEFAULT 'system',
                FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
                INDEX idx_booking_id (booking_id),
                INDEX idx_contact_id (contact_id),
                INDEX idx_sent_at (sent_at)
            )
        ");
    } catch (PDOException $e) {
        // Table already exists — ignore
    }
}

function handleTomorrowsBookings()
{
    global $pdo;

    ensureWhatsAppSchema($pdo);

    try {
        $sql = "
            SELECT b.id, b.trip_date, b.start_time, b.original_pickup, b.original_destination,
                   b.was_swapped, b.cost, b.last_confirmed_at,
                   c.name AS client_name, c.phone AS client_phone
            FROM bookings b
            JOIN contacts c ON b.contact_id = c.id
            WHERE b.trip_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
              AND b.status != 'completed'
            ORDER BY b.start_time ASC
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d'); // ✅ FIX

        $bookings = [];
        foreach ($rows as $row) {
            $row['pickup_location'] = $row['was_swapped'] ? $row['original_destination'] : $row['original_pickup'];
            $row['destination'] = $row['was_swapped'] ? $row['original_pickup'] : $row['original_destination'];

            $message = createEveningConfirmationMessage($row);
            $row['whatsapp_url'] = buildWhatsAppUrl($row['client_phone'], $message);
            $row['message_content'] = $message;
            $row['already_confirmed'] = (
                !empty($row['last_confirmed_at']) &&
                substr($row['last_confirmed_at'], 0, 10) === $today // ✅ FIX: was $tomorrow
            );
            $bookings[] = $row;
        }

        jsonResponse(['success' => true, 'bookings' => $bookings]);

    } catch (PDOException $e) {
        logError('BOOKING_API', 'Failed to fetch tomorrow\'s bookings', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

function handleMarkConfirmed()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    $message = trim($_POST['message_content'] ?? '');

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid booking ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET last_confirmed_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            jsonResponse(['success' => false, 'message' => 'Booking not found or could not be updated.'], 404);
            return;
        }

        // Fetch contact_id for the log entry
        $contactStmt = $pdo->prepare("SELECT contact_id FROM bookings WHERE id = ?");
        $contactStmt->execute([$id]);
        $contactId = $contactStmt->fetchColumn() ?: null;

        // Log the confirmation
        if ($message !== '') {
            $logStmt = $pdo->prepare("
                INSERT INTO whatsapp_log (booking_id, contact_id, message_type, message_content, sent_by)
                VALUES (?, ?, 'evening_confirmation', ?, 'user')
            ");
            $logStmt->execute([$id, $contactId, $message]);
        }

        logDebug('BOOKING_API', 'Booking marked as confirmed', ['booking_id' => $id]);
        jsonResponse(['success' => true]);

    } catch (PDOException $e) {
        logError('BOOKING_API', 'Failed to mark booking confirmed', [
            'error' => $e->getMessage(),
            'booking_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

// ========== FEATURE 3: WHATSAPP LOG ==========

function handleLogWhatsApp()
{
    global $pdo;

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
    $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
    $messageType = trim($_POST['message_type'] ?? 'custom');
    $messageContent = trim($_POST['message_content'] ?? '');
    $sentBy = trim($_POST['sent_by'] ?? 'user');

    if ($messageContent === '') {
        jsonResponse(['success' => false, 'message' => 'Message content is required.'], 400);
    }

    ensureWhatsAppSchema($pdo);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_log (booking_id, contact_id, message_type, message_content, sent_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bookingId, $contactId, $messageType, $messageContent, $sentBy]);

        logDebug('BOOKING_API', 'WhatsApp message logged', [
            'booking_id' => $bookingId,
            'contact_id' => $contactId,
            'message_type' => $messageType
        ]);
        jsonResponse(['success' => true]);

    } catch (PDOException $e) {
        logError('BOOKING_API', 'Failed to log WhatsApp message', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}

function handleGetWhatsAppLog()
{
    global $pdo;

    $bookingId = !empty($_GET['booking_id']) ? intval($_GET['booking_id']) : null;
    $contactId = !empty($_GET['contact_id']) ? intval($_GET['contact_id']) : null;

    if (!$bookingId && !$contactId) {
        jsonResponse(['success' => false, 'message' => 'booking_id or contact_id required.'], 400);
    }

    ensureWhatsAppSchema($pdo);

    try {
        if ($bookingId) {
            $stmt = $pdo->prepare("
                SELECT * FROM whatsapp_log
                WHERE booking_id = ?
                ORDER BY sent_at DESC
                LIMIT 50
            ");
            $stmt->execute([$bookingId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM whatsapp_log
                WHERE contact_id = ?
                ORDER BY sent_at DESC
                LIMIT 50
            ");
            $stmt->execute([$contactId]);
        }

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['success' => true, 'logs' => $logs]);

    } catch (PDOException $e) {
        logError('BOOKING_API', 'Failed to fetch WhatsApp log', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
    }
}
