<?php
// modules/Clients/api/index.php
// Unified Clients API router

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

function jsonResponse(array $payload, int $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_REQUEST['action'] ?? 'get';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!$action || $action === 'get') {
    if ($method === 'GET') {
        $action = 'get';
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? 'add';
    }
}

try {
    switch ($action) {
        case 'get':
            handleGetClients();
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
        case 'get_single':
            handleGetSingleClient();
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
    
    $search = $_GET['search'] ?? '';
    
    try {
        $sql = "
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                c.address,
                GROUP_CONCAT(g.name SEPARATOR ', ') as groups,
                COUNT(DISTINCT b.id) as booking_count
            FROM contacts c
            LEFT JOIN contact_groups cg ON c.id = cg.contact_id
            LEFT JOIN groups g ON cg.group_id = g.id
            LEFT JOIN bookings b ON c.id = b.contact_id
        ";
        
        if (!empty($search)) {
            $sql .= " WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.name ASC";
        
        $stmt = $pdo->prepare($sql);
        
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        } else {
            $stmt->execute();
        }
        
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse([
            'success' => true,
            'clients' => $clients
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to fetch clients', [
            'error' => $e->getMessage(),
            'search' => $search
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
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
        }
        
        // Get groups
        $stmt = $pdo->prepare("
            SELECT g.id, g.name 
            FROM groups g
            JOIN contact_groups cg ON g.id = cg.group_id
            WHERE cg.contact_id = ?
        ");
        $stmt->execute([$id]);
        $client['groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $groups = $_POST['groups'] ?? [];
        
        // Validate
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Name is required'], 400);
        }
        
        // Insert client
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, email, phone, address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $phone, $address]);
        
        $clientId = $pdo->lastInsertId();
        
        // Add to groups
        if (!empty($groups)) {
            $stmt = $pdo->prepare("INSERT INTO contact_groups (contact_id, group_id) VALUES (?, ?)");
            foreach ($groups as $groupId) {
                $stmt->execute([$clientId, $groupId]);
            }
        }
        
        logInfo('CLIENT', 'Client created', [
            'client_id' => $clientId,
            'name' => $name
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Client added successfully',
            'client_id' => $clientId
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
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $groups = $_POST['groups'] ?? [];
        
        if ($id <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid client ID'], 400);
        }
        
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Name is required'], 400);
        }
        
        // Update client
        $stmt = $pdo->prepare("
            UPDATE contacts 
            SET name = ?, email = ?, phone = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $address, $id]);
        
        // Update groups
        $pdo->prepare("DELETE FROM contact_groups WHERE contact_id = ?")->execute([$id]);
        
        if (!empty($groups)) {
            $stmt = $pdo->prepare("INSERT INTO contact_groups (contact_id, group_id) VALUES (?, ?)");
            foreach ($groups as $groupId) {
                $stmt->execute([$id, $groupId]);
            }
        }
        
        logInfo('CLIENT', 'Client updated', [
            'client_id' => $id,
            'name' => $name
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Client updated successfully'
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to update client', [
            'error' => $e->getMessage(),
            'client_id' => $id ?? null
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to update client'], 500);
    }
}

function handleDeleteClient()
{
    global $pdo;
    
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
        
        // Delete group associations
        $pdo->prepare("DELETE FROM contact_groups WHERE contact_id = ?")->execute([$id]);
        
        // Delete client
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        
        logInfo('CLIENT', 'Client deleted', [
            'client_id' => $id,
            'name' => $clientName
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Client deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        logError('CLIENT', 'Failed to delete client', [
            'error' => $e->getMessage(),
            'client_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete client'], 500);
    }
}