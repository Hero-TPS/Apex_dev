<?php
// modules/Prebookings/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/google-auth.php';
require_once ROOT_DIR . '/modules/Bookings/helpers.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'add':
            handleAdd();
            break;
        case 'update':
            handleUpdate();
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

function handleList()
{
    global $pdo;

    try {
        $show = $_GET['show'] ?? 'upcoming';
        $tz   = new DateTimeZone(TIME_ZONE);
        $today = (new DateTime('now', $tz))->format('Y-m-d');

        if ($show === 'all') {
            $stmt = $pdo->query("
                SELECT p.*, c.name AS client_name, c.phone AS client_phone
                FROM prebookings p
                JOIN contacts c ON p.contact_id = c.id
                WHERE p.converted_booking_id IS NULL
                ORDER BY p.trip_date ASC, p.start_time ASC
                LIMIT 200
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS client_name, c.phone AS client_phone
                FROM prebookings p
                JOIN contacts c ON p.contact_id = c.id
                WHERE p.converted_booking_id IS NULL
                  AND p.trip_date >= ?
                ORDER BY p.trip_date ASC, p.start_time ASC
                LIMIT 100
            ");
            $stmt->execute([$today]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prebookings = [];
        $todayObj = new DateTime('today', $tz);

        foreach ($rows as $row) {
            $dateObj  = new DateTime($row['trip_date'], $tz);
            $isPast   = $dateObj < $todayObj;
            $effPickup = !empty($row['was_swapped'])
                ? ($row['original_destination'] ?? '')
                : ($row['original_pickup'] ?? '');
            $effDest   = !empty($row['was_swapped'])
                ? ($row['original_pickup'] ?? '')
                : ($row['original_destination'] ?? '');

            $prebookings[] = [
                'id'            => (int) $row['id'],
                'contact_id'    => (int) $row['contact_id'],
                'trip_date'     => $dateObj->format('d/m/y'),
                'trip_date_raw' => $row['trip_date'],
                'start_time'    => $row['start_time'] ? substr($row['start_time'], 0, 5) : '',
                'client_name'   => $row['client_name'],
                'client_phone'  => formatPhoneNumberForWhatsApp($row['client_phone'] ?? ''),
                'pickup_location' => $effPickup,
                'destination'   => $effDest,
                'cost'          => $row['cost'] ? 'R' . number_format((float) $row['cost'], 2) : '',
                'description'   => $row['description'] ?? '',
                'is_past'       => $isPast,
                'whatsapp_url'  => buildWhatsAppUrl($row['client_phone'] ?? '', createPrebookingWhatsAppMessage([
                    'client_name'          => $row['client_name'],
                    'trip_date'            => $row['trip_date'],
                    'start_time'           => $row['start_time'] ?? '',
                    'original_pickup'      => $row['original_pickup'] ?? '',
                    'original_destination' => $row['original_destination'] ?? '',
                    'was_swapped'          => $row['was_swapped'] ?? 0,
                    'cost'                 => $row['cost'] ?? '',
                    'description'          => $row['description'] ?? '',
                ])),
            ];
        }

        jsonResponse(['success' => true, 'prebookings' => $prebookings]);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to list prebookings', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
    }
}

function handleAdd()
{
    global $pdo;

    $contactId    = intval($_POST['contact_id'] ?? 0);
    $tripDate     = trim($_POST['trip_date'] ?? '');
    $startTime    = trim($_POST['start_time'] ?? '');
    $originalPickup = trim($_POST['original_pickup'] ?? '');
    $originalDest = trim($_POST['original_destination'] ?? '');
    $cost         = trim($_POST['cost'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if ($contactId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Please select a client.'], 400);
    }
    if (!$tripDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        jsonResponse(['success' => false, 'message' => 'A valid date is required.'], 400);
    }

    $startTimeVal = ($startTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ? $startTime : null;
    $pickupVal    = $originalPickup !== '' ? $originalPickup : null;
    $destVal      = $originalDest !== '' ? $originalDest : null;
    $costVal      = ($cost !== '' && is_numeric($cost) && (float)$cost > 0) ? (float)$cost : null;
    $descVal      = $description !== '' ? $description : null;
    $wasSwapped   = isset($_POST['swap_locations']) && $_POST['swap_locations'] === '1' ? 1 : 0;

    try {
        $stmt = $pdo->prepare("SELECT name, phone FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            jsonResponse(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $ins = $pdo->prepare("
            INSERT INTO prebookings (contact_id, trip_date, start_time, original_pickup, original_destination, was_swapped, cost, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$contactId, $tripDate, $startTimeVal, $pickupVal, $destVal, $wasSwapped, $costVal, $descVal]);
        $prebookingId = (int) $pdo->lastInsertId();

        reactivateContactIfArchived($pdo, $contactId);

        $calData = [
            'id'                   => $prebookingId,
            'client_name'          => $contact['name'],
            'client_phone'         => $contact['phone'] ?? '',
            'trip_date'            => $tripDate,
            'start_time'           => $startTimeVal,
            'original_pickup'      => $pickupVal,
            'original_destination' => $destVal,
            'was_swapped'          => $wasSwapped,
            'cost'                 => $costVal,
            'description'          => $descVal,
        ];
        $eventId = createPrebookingInGoogleCalendar($calData);
        if ($eventId) {
            $upd = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = ? WHERE id = ?");
            $upd->execute([$eventId, $prebookingId]);
        }

        logInfo('PREBOOKING', 'Prebooking created', [
            'prebooking_id'  => $prebookingId,
            'contact_id'     => $contactId,
            'trip_date'      => $tripDate,
            'calendar_event' => $eventId ?? 'none',
        ]);

        jsonResponse(['success' => true, 'message' => 'Prebooking saved.', 'prebooking_id' => $prebookingId]);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to create prebooking', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save prebooking.'], 500);
    }
}

function handleUpdate()
{
    global $pdo;

    $id           = intval($_POST['id'] ?? 0);
    $tripDate     = trim($_POST['trip_date'] ?? '');
    $startTime    = trim($_POST['start_time'] ?? '');
    $originalPickup = trim($_POST['original_pickup'] ?? '');
    $originalDest = trim($_POST['original_destination'] ?? '');
    $cost         = trim($_POST['cost'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid prebooking ID.'], 400);
    }
    if (!$tripDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        jsonResponse(['success' => false, 'message' => 'A valid date is required.'], 400);
    }

    $startTimeVal = ($startTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ? $startTime : null;
    $pickupVal    = $originalPickup !== '' ? $originalPickup : null;
    $destVal      = $originalDest !== '' ? $originalDest : null;
    $costVal      = ($cost !== '' && is_numeric($cost) && (float)$cost > 0) ? (float)$cost : null;
    $descVal      = $description !== '' ? $description : null;
    $wasSwapped   = isset($_POST['swap_locations']) && $_POST['swap_locations'] === '1' ? 1 : 0;

    try {
        // Fetch existing record for calendar event ID and client info
        $stmt = $pdo->prepare("
            SELECT p.google_calendar_event_id, c.name AS client_name, c.phone AS client_phone
            FROM prebookings p
            JOIN contacts c ON p.contact_id = c.id
            WHERE p.id = ? AND p.converted_booking_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            jsonResponse(['success' => false, 'message' => 'Prebooking not found.'], 404);
        }

        // Update the database record
        $upd = $pdo->prepare("
            UPDATE prebookings
            SET trip_date = ?, start_time = ?, original_pickup = ?, original_destination = ?, was_swapped = ?, cost = ?, description = ?
            WHERE id = ?
        ");
        $upd->execute([$tripDate, $startTimeVal, $pickupVal, $destVal, $wasSwapped, $costVal, $descVal, $id]);

        // Update Google Calendar event if one exists
        $calData = [
            'id'                   => $id,
            'client_name'          => $existing['client_name'],
            'client_phone'         => $existing['client_phone'] ?? '',
            'trip_date'            => $tripDate,
            'start_time'           => $startTimeVal,
            'original_pickup'      => $pickupVal,
            'original_destination' => $destVal,
            'was_swapped'          => $wasSwapped,
            'cost'                 => $costVal,
            'description'          => $descVal,
        ];

        if (!empty($existing['google_calendar_event_id'])) {
            // Delete old event and create a fresh one (simpler than PATCH for all-day ↔ timed transitions)
            deletePrebookingFromGoogleCalendar($existing['google_calendar_event_id']);
            $newEventId = createPrebookingInGoogleCalendar($calData);
            $updCal = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = ? WHERE id = ?");
            $updCal->execute([$newEventId ?: null, $id]);
        } else {
            // No existing event — create one now
            $newEventId = createPrebookingInGoogleCalendar($calData);
            if ($newEventId) {
                $updCal = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = ? WHERE id = ?");
                $updCal->execute([$newEventId, $id]);
            }
        }

        logInfo('PREBOOKING', 'Prebooking updated', [
            'prebooking_id'  => $id,
            'trip_date'      => $tripDate,
            'calendar_event' => $newEventId ?? 'none',
        ]);

        jsonResponse(['success' => true, 'message' => 'Prebooking updated.']);

    } catch (PDOException $e) {
        logError('PREBOOKING', 'Failed to update prebooking', ['error' => $e->getMessage(), 'id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to update prebooking.'], 500);
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

        if (!empty($pre['google_calendar_event_id'])) {
            deletePrebookingFromGoogleCalendar($pre['google_calendar_event_id']);
            $upd = $pdo->prepare("UPDATE prebookings SET google_calendar_event_id = NULL WHERE id = ?");
            $upd->execute([$id]);
        }

        $params = http_build_query(array_filter([
            'contact_id'      => $pre['contact_id'],
            'contact_name'    => $pre['client_name'],
            'trip_date'       => $pre['trip_date'],
            'start_time'      => $pre['start_time'] ?? '',
            'pickup'          => $pre['original_pickup'] ?? '',
            'destination'     => $pre['original_destination'] ?? '',
            'swap_locations'  => !empty($pre['was_swapped']) ? '1' : '',
            'cost'            => $pre['cost'] ?? '',
            'description'     => $pre['description'] ?? '',
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
