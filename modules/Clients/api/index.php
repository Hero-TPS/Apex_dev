<?php
// modules/Clients/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php'; 

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            handleGetClients();
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
    
    $onlyWithBookings = isset($_GET['only_with_bookings']) && $_GET['only_with_bookings'] == 1;
    
    try {
        if ($onlyWithBookings) {
            $sql = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT b.id) AS booking_count
                FROM contacts c
                INNER JOIN bookings b ON c.id = b.contact_id
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
        } else {
            $sql = "
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
                FROM contacts c
                ORDER BY c.name ASC
            ";
        }
        
        $stmt = $pdo->query($sql);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($contacts as &$contact) {
            $contact['whatsapp_phone'] = formatPhoneNumberForWhatsApp($contact['phone'] ?? '');
        }
        unset($contact);
        
        jsonResponse([
            'success' => true,
            'contacts' => $contacts
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to fetch clients', [
            'error' => $e->getMessage(),
            'only_with_bookings' => $onlyWithBookings
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
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
        
        logDebug('CLIENT', 'Client created', [
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
            logDebug('CLIENT', 'Client updated', [
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
            logDebug('CLIENT', 'Client deleted', [
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

        logDebug('CLIENT', 'Pickup GPS saved', [
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