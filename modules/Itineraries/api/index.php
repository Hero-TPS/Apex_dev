<?php
// modules/Itineraries/api/index.php
// Itineraries API router. Step 1: stops of a prebooking's itinerary.

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/../helpers.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_prebooking_stops':
            handleGetPrebookingStops();
            break;
        case 'save_prebooking_stops':
            handleSavePrebookingStops();
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

// ========== HANDLERS ==========

/**
 * True when the prebooking exists and has not been converted to a booking yet.
 */
function openPrebookingExists(PDO $pdo, int $prebookingId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM prebookings WHERE id = ? AND converted_booking_id IS NULL LIMIT 1");
    $stmt->execute([$prebookingId]);
    return (bool) $stmt->fetchColumn();
}

function handleGetPrebookingStops()
{
    global $pdo;

    $prebookingId = intval($_GET['prebooking_id'] ?? 0);
    if ($prebookingId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid prebooking ID.'], 400);
    }

    try {
        if (!openPrebookingExists($pdo, $prebookingId)) {
            jsonResponse(['success' => false, 'message' => 'Prebooking not found.'], 404);
        }

        $itinerary = getItineraryForPrebooking($pdo, $prebookingId);

        jsonResponse([
            'success' => true,
            'status'  => $itinerary['status'] ?? 'planning',
            'stops'   => $itinerary ? getItineraryStops($pdo, (int) $itinerary['id']) : [],
        ]);

    } catch (PDOException $e) {
        logError('ITINERARY', 'Failed to load prebooking stops', ['error' => $e->getMessage(), 'prebooking_id' => $prebookingId]);
        jsonResponse(['success' => false, 'message' => 'Database error.'], 500);
    }
}

function handleSavePrebookingStops()
{
    global $pdo;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST required.'], 405);
    }

    $prebookingId = intval($_POST['prebooking_id'] ?? 0);
    if ($prebookingId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid prebooking ID.'], 400);
    }

    $decoded = json_decode($_POST['stops'] ?? '', true);
    $result  = normaliseItineraryStops($decoded);
    if ($result['error'] !== null) {
        jsonResponse(['success' => false, 'message' => $result['error']], 400);
    }

    try {
        if (!openPrebookingExists($pdo, $prebookingId)) {
            jsonResponse(['success' => false, 'message' => 'Prebooking not found.'], 404);
        }

        $itineraryId = saveItineraryStopsForPrebooking($pdo, $prebookingId, $result['stops']);

        logInfo('ITINERARY', 'Prebooking stops saved', [
            'prebooking_id' => $prebookingId,
            'itinerary_id'  => $itineraryId,
            'stop_count'    => count($result['stops']),
        ]);

        jsonResponse([
            'success' => true,
            'message' => count($result['stops']) === 0 ? 'Itinerary cleared.' : 'Stops saved.',
            'stops'   => array_map(function ($s) {
                return [
                    'location'       => $s['location'],
                    'arrival_time'   => $s['arrival_time'] !== null ? substr($s['arrival_time'], 0, 5) : '',
                    'wait_minutes'   => $s['wait_minutes'] ?? '',
                    'internal_notes' => $s['internal_notes'] ?? '',
                    'client_notes'   => $s['client_notes'] ?? '',
                ];
            }, $result['stops']),
        ]);

    } catch (PDOException $e) {
        logError('ITINERARY', 'Failed to save prebooking stops', ['error' => $e->getMessage(), 'prebooking_id' => $prebookingId]);
        jsonResponse(['success' => false, 'message' => 'Failed to save stops.'], 500);
    }
}
