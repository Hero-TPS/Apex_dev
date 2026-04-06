<?php
// modules/Prebookings/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/modules/Bookings/helpers.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            handleAdd();
            break;
        case 'delete':
            handleDelete();
            break;
        case 'convert':
            handleConvert();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('PREBOOKING_API', 'Unhandled exception', [
        'error'  => $e->getMessage(),
        'action' => $action,
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleAdd()
{
    global $pdo;

    $contactId          = intval($_POST['contact_id'] ?? 0);
    $tripDate           = trim($_POST['trip_date'] ?? '');
    $startTime          = trim($_POST['start_time'] ?? '');
    $originalDest       = trim($_POST['original_destination'] ?? '');
    $cost               = trim($_POST['cost'] ?? '');
    $description        = trim($_POST['description'] ?? '');

    if ($contactId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Please select a client.'], 400);
    }
    if (!$tripDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        jsonResponse(['success' => false, 'message' => 'A valid date is required.'], 400);
    }

    // Validate optional fields
    $startTimeVal = ($startTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ? $startTime : null;
    $destVal      = $originalDest !== '' ? $originalDest : null;
    $costVal      = ($cost !== '' && is_numeric($cost) && (float)$cost > 0) ? (float)$cost : null;
    $descVal      = $description !== '' ? $description : null;

    try {
        // Fetch client info for calendar event
        $stmt = $pdo->prepare("SELECT name, phone FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            jsonResponse(['success' => false, 'message' => 'Client not found.'], 404);
        }

        // Insert prebooking
        $ins = $pdo->prepare("
            INSERT INTO prebookings (contact_id, trip_date, start_time, original_destination, cost, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$contactId, $tripDate, $startTimeVal, $destVal, $costVal, $descVal]);
        $prebookingId = (int) $pdo->lastInsertId();

        // Create Google Calendar event
        $calData = [
            'id'                   => $prebookingId,
            'client_name'          => $contact['name'],
            'client_phone'         => $contact['phone'] ?? '',
            'trip_date'            => $tripDate,
            'start_time'           => $startTimeVal,
            'original_destination' => $destVal,
            'cost'                 => $costVal,
            'description'          => $descVal,
        ];
        $eventId = createPrebookingInGoogleCalendar($calData);
        if ($eventId) {
            $upd = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = ? WHERE id = ?");
            $upd->execute([$eventId, $prebookingId]);
        }

        logInfo('PREBOOKING', 'Prebooking created', [
            'prebooking_id' => $prebookingId,
            'contact_id'    => $contactId,
            'trip_date'     => $tripDate,
            'calendar_event'=> $eventId ?? 'none',
        ]);

        jsonResponse(['success' => true, 'message' => 'Prebooking saved.', 'prebooking_id' => $prebookingId]);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to create prebooking', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save prebooking.'], 500);
    }
}

function handleDelete()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid prebooking ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT google_calendar_event_id FROM prebookings WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse(['success' => false, 'message' => 'Prebooking not found.'], 404);
        }

        if (!empty($row['google_calendar_event_id'])) {
            deletePrebookingFromGoogleCalendar($row['google_calendar_event_id']);
        }

        $del = $pdo->prepare("DELETE FROM prebookings WHERE id = ?");
        $del->execute([$id]);

        logInfo('PREBOOKING', 'Prebooking deleted', ['prebooking_id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Prebooking deleted.']);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to delete prebooking', ['error' => $e->getMessage(), 'id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete prebooking.'], 500);
    }
}

function handleConvert()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid prebooking ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS client_name, c.phone AS client_phone
            FROM prebookings p
            JOIN contacts c ON p.contact_id = c.id
            WHERE p.id = ? AND p.converted_booking_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $pre = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pre) {
            jsonResponse(['success' => false, 'message' => 'Prebooking not found or already converted.'], 404);
        }

        // Delete Google Calendar tentative event
        if (!empty($pre['google_calendar_event_id'])) {
            deletePrebookingFromGoogleCalendar($pre['google_calendar_event_id']);
            $upd = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = NULL WHERE id = ?");
            $upd->execute([$id]);
        }

        // Build redirect URL for booking add form with prefilled values
        $params = http_build_query(array_filter([
            'contact_id'    => $pre['contact_id'],
            'contact_name'  => $pre['client_name'],
            'trip_date'     => $pre['trip_date'],
            'start_time'    => $pre['start_time'] ?? '',
            'destination'   => $pre['original_destination'] ?? '',
            'cost'          => $pre['cost'] ?? '',
            'description'   => $pre['description'] ?? '',
            'from_prebooking' => $id,
        ], fn($v) => $v !== '' && $v !== null));

        $redirectUrl = BASE_URL . '/modules/Bookings/add.php?' . $params;

        logInfo('PREBOOKING', 'Prebooking conversion started', ['prebooking_id' => $id]);
        jsonResponse(['success' => true, 'redirect_url' => $redirectUrl]);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to convert prebooking', ['error' => $e->getMessage(), 'id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to convert prebooking.'], 500);
    }
}
