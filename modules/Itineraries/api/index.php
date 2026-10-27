<?php
// modules/Itineraries/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';

const ITINERARY_MAX_STOPS = 30;

$action = $_REQUEST['action'] ?? '';

try {
    if (!itineraryMigrationReady($pdo)) {
        jsonResponse([
            'success' => false,
            'message' => 'The itinerary tables are not up to date. Run the pending updates in Services → Database Updates.',
        ], 500);
    }

    switch ($action) {
        case 'add':
            handleAdd();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('ITINERARY_API', 'Unhandled exception', [
        'error'  => $e->getMessage(),
        'action' => $action,
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== INPUT ==========

/**
 * Read and validate the trip details and stops from $_POST.
 * Sends a JSON error and stops when something is invalid.
 */
function readItineraryInput(): array
{
    $tripDate  = trim($_POST['trip_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $pickup    = trim($_POST['pickup'] ?? '');
    $dest      = trim($_POST['destination'] ?? '');
    $cost      = trim($_POST['cost'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $paxName   = trim($_POST['passenger_name'] ?? '');
    $paxPhone  = trim($_POST['passenger_phone'] ?? '');

    if (!$tripDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tripDate)) {
        jsonResponse(['success' => false, 'message' => 'A valid date is required.'], 400);
    }
    if (mb_strlen($pickup) > 255 || mb_strlen($dest) > 255 || mb_strlen($paxName) > 255 || mb_strlen($paxPhone) > 50) {
        jsonResponse(['success' => false, 'message' => 'One of the fields is too long.'], 400);
    }

    $input = [
        'trip_date'       => $tripDate,
        'start_time'      => ($startTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime)) ? $startTime : null,
        'pickup'          => $pickup !== '' ? $pickup : null,
        'destination'     => $dest !== '' ? $dest : null,
        'cost'            => ($cost !== '' && is_numeric($cost) && (float)$cost > 0) ? (float)$cost : null,
        'description'     => $desc !== '' ? $desc : null,
        'passenger_name'  => $paxName !== '' ? $paxName : null,
        'passenger_phone' => $paxPhone !== '' ? $paxPhone : null,
        'stops'           => readItineraryStops(),
    ];
    return $input;
}

/**
 * Read the stops JSON from $_POST['stops'] into a clean, ordered list.
 * The order of the array is the order of the stops.
 */
function readItineraryStops(): array
{
    $raw = $_POST['stops'] ?? '[]';
    $decoded = json_decode(is_string($raw) ? $raw : '[]', true);
    if (!is_array($decoded)) {
        jsonResponse(['success' => false, 'message' => 'The stops could not be read.'], 400);
    }
    if (count($decoded) > ITINERARY_MAX_STOPS) {
        jsonResponse(['success' => false, 'message' => 'An itinerary can have at most ' . ITINERARY_MAX_STOPS . ' stops.'], 400);
    }

    $stops = [];
    foreach (array_values($decoded) as $i => $s) {
        $n = $i + 1;
        if (!is_array($s)) {
            jsonResponse(['success' => false, 'message' => "Stop $n could not be read."], 400);
        }
        $location = trim((string)($s['location'] ?? ''));
        if ($location === '') {
            jsonResponse(['success' => false, 'message' => "Stop $n needs a location."], 400);
        }
        if (mb_strlen($location) > 255) {
            jsonResponse(['success' => false, 'message' => "The location of stop $n is too long."], 400);
        }

        $arrival = trim((string)($s['arrival_time'] ?? ''));
        $arrival = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $arrival) ? $arrival : null;

        $wait = trim((string)($s['wait_minutes'] ?? ''));
        $wait = ($wait !== '' && ctype_digit($wait) && (int)$wait <= 1440) ? (int)$wait : null;

        $client   = trim((string)($s['client_notes'] ?? ''));
        $internal = trim((string)($s['internal_notes'] ?? ''));

        $stops[] = [
            'location'       => $location,
            'arrival_time'   => $arrival,
            'wait_minutes'   => $wait,
            'client_notes'   => $client !== '' ? $client : null,
            'internal_notes' => $internal !== '' ? $internal : null,
        ];
    }
    return $stops;
}

/**
 * Replace all stops of an itinerary with the given list. Call inside a transaction.
 */
function saveItineraryStops(PDO $pdo, int $itineraryId, array $stops): void
{
    $del = $pdo->prepare("DELETE FROM itinerary_stops WHERE itinerary_id = ?");
    $del->execute([$itineraryId]);

    $ins = $pdo->prepare("
        INSERT INTO itinerary_stops (itinerary_id, sort_order, location, arrival_time, wait_minutes, client_notes, internal_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($stops as $i => $s) {
        $ins->execute([
            $itineraryId,
            $i + 1,
            $s['location'],
            $s['arrival_time'],
            $s['wait_minutes'],
            $s['client_notes'],
            $s['internal_notes'],
        ]);
    }
}

// ========== HANDLERS ==========

function handleAdd()
{
    global $pdo;

    $contactId = intval($_POST['contact_id'] ?? 0);
    if ($contactId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Please select a client.'], 400);
    }
    $in = readItineraryInput();

    try {
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO itineraries (contact_id, trip_date, start_time, pickup, destination, passenger_name, passenger_phone, cost, description, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'planning')
        ");
        $ins->execute([
            $contactId, $in['trip_date'], $in['start_time'], $in['pickup'], $in['destination'],
            $in['passenger_name'], $in['passenger_phone'], $in['cost'], $in['description'],
        ]);
        $itineraryId = (int) $pdo->lastInsertId();

        saveItineraryStops($pdo, $itineraryId, $in['stops']);
        $pdo->commit();

        reactivateContactIfArchived($pdo, $contactId);

        logInfo('ITINERARY', 'Itinerary created', [
            'itinerary_id' => $itineraryId,
            'contact_id'   => $contactId,
            'trip_date'    => $in['trip_date'],
            'stops'        => count($in['stops']),
        ]);

        jsonResponse(['success' => true, 'message' => 'Itinerary saved.', 'itinerary_id' => $itineraryId]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError('ITINERARY', 'Failed to create itinerary', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save itinerary.'], 500);
    }
}

function handleUpdate()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid itinerary ID.'], 400);
    }
    $in = readItineraryInput();

    try {
        $stmt = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? AND contact_id IS NOT NULL LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Itinerary not found.'], 404);
        }

        $pdo->beginTransaction();

        $upd = $pdo->prepare("
            UPDATE itineraries
            SET trip_date = ?, start_time = ?, pickup = ?, destination = ?,
                passenger_name = ?, passenger_phone = ?, cost = ?, description = ?
            WHERE id = ?
        ");
        $upd->execute([
            $in['trip_date'], $in['start_time'], $in['pickup'], $in['destination'],
            $in['passenger_name'], $in['passenger_phone'], $in['cost'], $in['description'], $id,
        ]);

        saveItineraryStops($pdo, $id, $in['stops']);
        $pdo->commit();

        logInfo('ITINERARY', 'Itinerary updated', [
            'itinerary_id' => $id,
            'trip_date'    => $in['trip_date'],
            'stops'        => count($in['stops']),
        ]);

        jsonResponse(['success' => true, 'message' => 'Itinerary updated.']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError('ITINERARY', 'Failed to update itinerary', ['error' => $e->getMessage(), 'id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to update itinerary.'], 500);
    }
}

function handleDelete()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid itinerary ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM itineraries WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Itinerary not found.'], 404);
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM itinerary_stops WHERE itinerary_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM itineraries WHERE id = ?")->execute([$id]);
        $pdo->commit();

        logInfo('ITINERARY', 'Itinerary deleted', ['itinerary_id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Itinerary deleted.']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError('ITINERARY', 'Failed to delete itinerary', ['error' => $e->getMessage(), 'id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete itinerary.'], 500);
    }
}
