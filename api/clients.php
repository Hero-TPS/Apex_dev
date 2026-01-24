<?php
// api/clients.php

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get':
            $onlyWithBookings = isset($_GET['only_with_bookings']) && $_GET['only_with_bookings'] == 1;

            if ($onlyWithBookings) {
                $sql = "
                    SELECT 
                        c.*,
                        (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
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
            echo json_encode(['success' => true, 'contacts' => $contacts]);
            break;

        case 'add':
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $additional_info = trim($_POST['additionalInfo'] ?? ''); // matches form name

            if (empty($name)) {
                throw new Exception('Client name is required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO contacts (name, phone, email, address, additional_info, date_added)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $phone, $email, $address, $additional_info]);

            echo json_encode([
                'success' => true,
                'message' => 'Client added successfully',
                'contact_id' => $pdo->lastInsertId(),
                'client' => [
                    'id' => $pdo->lastInsertId(),
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address
                ]
            ]);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST required for update');
            }

            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Valid contact ID required');
            }

            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $additional_info = trim($_POST['additionalInfo'] ?? '');

            if (empty($name)) {
                throw new Exception('Name is required');
            }

            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET name = ?, phone = ?, email = ?, address = ?, additional_info = ?
                WHERE id = ?
            ");
            $updated = $stmt->execute([$name, $phone, $email, $address, $additional_info, $id]);

            if ($updated && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Client updated successfully']);
            } else {
                throw new Exception('No changes made or client not found');
            }
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST required for deletion');
            }

            $id = $_POST['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                throw new Exception('Valid contact ID required');
            }

            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
            $deleted = $stmt->execute([$id]);

            if ($deleted && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
            } else {
                throw new Exception('Contact not found');
            }
            break;

        default:
            throw new Exception('Unsupported action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}