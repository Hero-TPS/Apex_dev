<?php
// modules/Clients/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Bookings/helpers.php'; // for deleteBookingFromGoogleCalendar() when archiving cancels future bookings

header('Content-Type: application/json');
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            handleGetClients();
            break;
        case 'get_csv':
            handleGetClientsCsv();
            break;
        case 'get_single':
            handleGetSingleClient();
            break;
        case 'add':
            handleAddClient();
            break;
        case 'update':
            handleUpdateClient();
            break;
        case 'delete':
            handleDeleteClient();
            break;
        case 'save_pickup_gps':
            handleSavePickupGps();
            break;
        case 'clear_pickup_gps':
            handleClearPickupGps();
            break;
        case 'update_wa_status':
            handleUpdateWaStatus();
            break;
        case 'toggle_archive':
            handleToggleArchive();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('CLIENT_API', 'Unhandled exception', [
        'error' => $e->getMessage(),
        'action' => $action
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleGetClients()
{
    global $pdo;

    // Support new `filter` param (all | with_bookings | without_bookings | archived)
    // Fall back to legacy `only_with_bookings` for backward-compat.
    $allowedFilters = ['all', 'with_bookings', 'without_bookings', 'archived'];
    $filter = $_GET['filter'] ?? null;
    if ($filter === null) {
        $onlyWithBookings = isset($_GET['only_with_bookings']) && $_GET['only_with_bookings'] == 1;
        $filter = $onlyWithBookings ? 'with_bookings' : 'all';
    } elseif (!in_array($filter, $allowedFilters, true)) {
        $filter = 'all';
    }

    try {
        $sql = buildClientsQuery($filter);
        $stmt = $pdo->query($sql);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Purely a display concern for the "Last Booking" column — MAX(trip_date)
        // is the most recent booking chronologically, which can be an upcoming
        // one rather than a past one. Flagged here so the UI can label it
        // "Upcoming" instead of implying it already happened. Independent of
        // handleToggleArchive()'s own "upcoming" check, which decides what gets
        // deleted on archive — this doesn't touch that.
        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

        foreach ($contacts as &$contact) {
            $contact['whatsapp_phone'] = formatPhoneNumberForWhatsApp($contact['phone'] ?? '');
            if (!empty($contact['last_booking_date'])) {
                $contact['last_booking_is_future'] = $contact['last_booking_date'] >= $today;
                $contact['last_booking_date'] = date('d M Y', strtotime($contact['last_booking_date']));
            } else {
                $contact['last_booking_is_future'] = false;
            }
        }
        unset($contact);

        jsonResponse([
            'success' => true,
            'contacts' => $contacts
        ]);

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to fetch clients', [
            'error' => $e->getMessage(),
            'filter' => $filter
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleGetClientsCsv()
{
    global $pdo;

    $allowedFilters = ['all', 'with_bookings', 'without_bookings', 'archived'];
    $filter = $_GET['filter'] ?? 'all';
    if (!in_array($filter, $allowedFilters, true)) {
        $filter = 'all';
    }
    $labelMap = [
        'all'              => 'All Clients',
        'with_bookings'    => 'Clients With Bookings',
        'without_bookings' => 'Clients Without Bookings',
        'archived'         => 'Archived Clients',
    ];
    $label = $labelMap[$filter] ?? 'Clients';
    $filename = str_replace(' ', '_', $label) . '_' . date('Y-m-d') . '.csv';

    try {
        $sql = buildClientsQuery($filter);
        $stmt = $pdo->query($sql);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send CSV headers (override JSON header set earlier)
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fputs($out, "\xEF\xBB\xBF");
        // Column headers — include WA Status for the without_bookings export
        $headers = ['Name', 'Phone', 'Email', 'Address', 'Additional Info', 'Bookings', 'Last Booking'];
        if ($filter === 'without_bookings') {
            $headers[] = 'WA Status';
        }
        fputcsv($out, $headers);

        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

        foreach ($contacts as $c) {
            $lastBooking = '';
            if (!empty($c['last_booking_date'])) {
                $lastBooking = date('d M Y', strtotime($c['last_booking_date']));
                if ($c['last_booking_date'] >= $today) {
                    $lastBooking = 'Upcoming: ' . $lastBooking;
                }
            }
            $row = [
                $c['name']            ?? '',
                $c['phone']           ?? '',
                $c['email']           ?? '',
                $c['address']         ?? '',
                $c['additional_info'] ?? '',
                $c['booking_count']   ?? 0,
                $lastBooking,
            ];
            if ($filter === 'without_bookings') {
                $row[] = $c['wa_status'] ?? '';
            }
            fputcsv($out, $row);
        }
        fclose($out);
        exit;

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to export clients CSV', [
            'error'  => $e->getMessage(),
            'filter' => $filter
        ]);
        // Restore JSON header for error response
        header('Content-Type: application/json');
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function buildClientsQuery($filter)
{
    switch ($filter) {
        case 'with_bookings':
            return "
                SELECT
                    c.*,
                    COUNT(DISTINCT b.id) AS booking_count,
                    MAX(b.trip_date) AS last_booking_date
                FROM contacts c
                INNER JOIN bookings b ON c.id = b.contact_id
                WHERE c.is_archived = 0
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
        case 'without_bookings':
            return "
                SELECT
                    c.*,
                    0 AS booking_count,
                    NULL AS last_booking_date
                FROM contacts c
                WHERE c.is_archived = 0
                  AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.contact_id = c.id)
                ORDER BY c.name ASC
            ";
        case 'archived':
            return "
                SELECT
                    c.*,
                    (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count,
                    (SELECT MAX(b.trip_date) FROM bookings b WHERE b.contact_id = c.id) AS last_booking_date
                FROM contacts c
                WHERE c.is_archived = 1
                ORDER BY c.name ASC
            ";
        default: // 'all' — active (non-archived) clients only
            return "
                SELECT
                    c.*,
                    (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count,
                    (SELECT MAX(b.trip_date) FROM bookings b WHERE b.contact_id = c.id) AS last_booking_date
                FROM contacts c
                WHERE c.is_archived = 0
                ORDER BY c.name ASC
            ";
    }
}

function handleGetSingleClient()
{
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
            FROM contacts c
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'client' => $client
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to fetch single client', [
            'error' => $e->getMessage(),
            'client_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleAddClient()
{
    global $pdo;
    
    try {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $additional_info = trim($_POST['additionalInfo'] ?? '');
        
        // Validate
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Client name is required'], 400);
        }

        // Normalise phone before storing
        if ($phone !== '') {
            $normalised = formatPhoneNumberForWhatsApp($phone);
            $phone = $normalised !== '' ? $normalised : preg_replace('/\D/', '', $phone);
        }

        // Insert client
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, phone, email, address, additional_info, date_added)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $phone, $email, $address, $additional_info]);
        
        $clientId = $pdo->lastInsertId();
        
        logInfo('CLIENT', 'Client created', [
            'client_id' => $clientId,
            'name' => $name
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Client added successfully',
            'contact_id' => $clientId,
            'client' => [
                'id' => $clientId,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ]
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to create client', [
            'error' => $e->getMessage()
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to add client'], 500);
    }
}

function handleUpdateClient()
{
    global $pdo;
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $additional_info = trim($_POST['additionalInfo'] ?? '');
        
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid client ID'], 400);
        }
        
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Name is required'], 400);
        }

        // Normalise phone before storing
        if ($phone !== '') {
            $normalised = formatPhoneNumberForWhatsApp($phone);
            $phone = $normalised !== '' ? $normalised : preg_replace('/\D/', '', $phone);
        }

        // Update client
        $stmt = $pdo->prepare("
            UPDATE contacts 
            SET name = ?, phone = ?, email = ?, address = ?, additional_info = ?
            WHERE id = ?
        ");
        $updated = $stmt->execute([$name, $phone, $email, $address, $additional_info, $id]);
        
        if ($updated) {
            logInfo('CLIENT', 'Client updated', [
                'client_id' => $id,
                'name' => $name
            ]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Client updated successfully'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Failed to update client'
            ], 400);
        }
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to update client', [
            'error' => $e->getMessage(),
            'client_id' => $id ?? null
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to update client'], 500);
    }
}

function handleDeleteClient()
{    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID'], 400);
    }
    
    try {
        // Check if client has bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE contact_id = ?");
        $stmt->execute([$id]);
        $bookingCount = $stmt->fetchColumn();
        
        if ($bookingCount > 0) {
            jsonResponse([
                'success' => false,
                'message' => "Cannot delete client with {$bookingCount} booking(s). Delete bookings first."
            ], 400);
        }
        
        // Get client name for logging
        $stmt = $pdo->prepare("SELECT name FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $clientName = $stmt->fetchColumn();
        
        // Delete client
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            logInfo('CLIENT', 'Client deleted', [
                'client_id' => $id,
                'name' => $clientName
            ]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Client deleted successfully'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
        }
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to delete client', [
            'error' => $e->getMessage(),
            'client_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete client'], 500);
    }
}

function handleSavePickupGps()
{
    global $pdo;

    $id  = intval($_POST['id'] ?? 0);
    $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID.'], 400);
    }

    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonResponse(['success' => false, 'message' => 'Invalid GPS coordinates.'], 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE contacts SET pickup_lat = ?, pickup_lng = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $id]);

        logInfo('CLIENT', 'Pickup GPS saved', [
            'client_id' => $id,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        jsonResponse(['success' => true, 'message' => 'GPS location saved.']);

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to save pickup GPS', [
            'error'     => $e->getMessage(),
            'client_id' => $id,
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to save GPS location.'], 500);
    }
}

function handleClearPickupGps()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("UPDATE contacts SET pickup_lat = NULL, pickup_lng = NULL WHERE id = ?");
        $stmt->execute([$id]);

        logInfo('CLIENT', 'Pickup GPS cleared', ['client_id' => $id]);

        jsonResponse(['success' => true, 'message' => 'GPS location cleared.']);

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to clear pickup GPS', [
            'error'     => $e->getMessage(),
            'client_id' => $id,
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to clear GPS location.'], 500);
    }
}

function handleToggleArchive()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT name, is_archived FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            jsonResponse(['success' => false, 'message' => 'Client not found.'], 404);
        }

        $newState = $client['is_archived'] ? 0 : 1;

        $upd = $pdo->prepare("UPDATE contacts SET is_archived = ? WHERE id = ?");
        $upd->execute([$newState, $id]);

        $deletedBookings = 0;
        if ($newState === 1) {
            // Archiving: an archived client shouldn't have upcoming trips on the
            // books. Same "upcoming" definition used by the Bookings list
            // (today or later, not already completed) — past bookings are left
            // alone since they're historical record.
            $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT id, google_calendar_event_id
                FROM bookings
                WHERE contact_id = ? AND trip_date >= ? AND status != 'completed'
            ");
            $stmt->execute([$id, $today]);
            $futureBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($futureBookings)) {
                foreach ($futureBookings as $fb) {
                    if (!empty($fb['google_calendar_event_id'])) {
                        deleteBookingFromGoogleCalendar($fb['google_calendar_event_id']);
                    }
                }
                $ids = array_column($futureBookings, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $del = $pdo->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
                $del->execute($ids);
                $deletedBookings = count($ids);

                logWarning('CLIENT', 'Deleted future booking(s) on client archive', [
                    'client_id'  => $id,
                    'name'       => $client['name'],
                    'booking_ids' => $ids,
                ]);
            }
        }

        logInfo('CLIENT', $newState ? 'Client archived' : 'Client unarchived', [
            'client_id' => $id,
            'name'      => $client['name'],
        ]);

        $message = $newState ? 'Client archived.' : 'Client unarchived.';
        if ($deletedBookings > 0) {
            $message .= " {$deletedBookings} future booking(s) were removed.";
        }

        jsonResponse([
            'success'          => true,
            'message'          => $message,
            'is_archived'      => $newState,
            'deleted_bookings' => $deletedBookings,
        ]);

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to toggle archive state', [
            'error'     => $e->getMessage(),
            'client_id' => $id,
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to update client.'], 500);
    }
}

function handleUpdateWaStatus()
{
    global $pdo;

    $id     = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    $allowed = ['sent', 'positive'];

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid client ID.'], 400);
    }

    if (!in_array($status, $allowed, true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid WA status value.'], 400);
    }

    try {
        if ($status === 'sent') {
            $stmt = $pdo->prepare("UPDATE contacts SET wa_status = ?, wa_sent_date = CURDATE() WHERE id = ?");
            $stmt->execute([$status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE contacts SET wa_status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }

        logInfo('CLIENT', 'WA status updated', [
            'client_id' => $id,
            'wa_status'  => $status,
        ]);

        $waSentDate = null;
        if ($status === 'sent') {
            $waSentDate = date('Y-m-d');
        }

        jsonResponse(['success' => true, 'message' => 'WA status updated.', 'wa_status' => $status, 'wa_sent_date' => $waSentDate]);

    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to update WA status', [
            'error'     => $e->getMessage(),
            'client_id' => $id,
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to update WA status.'], 500);
    }
}
