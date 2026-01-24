<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

$response = ['success' => false, 'message' => 'Invalid action.'];

$action = $_GET['action'] ?? '';

try {
    if ($action === 'find_duplicates') {
        // Find contacts with same phone, NOT in any group
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.phone, c.address, c.email
            FROM contacts c
            LEFT JOIN group_members gm ON c.id = gm.contact_id
            WHERE c.phone IS NOT NULL 
              AND TRIM(c.phone) != ''
              AND gm.contact_id IS NULL
            ORDER BY c.phone
        ");
        $stmt->execute();
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byPhone = [];
        foreach ($all as $c) {
            $norm = formatPhoneNumberForWhatsApp($c['phone']);
            if (!empty($norm)) {
                $byPhone[$norm][] = $c;
            }
        }

        $duplicates = array_filter($byPhone, fn($group) => count($group) > 1);
        $response = ['success' => true, 'duplicates' => $duplicates];
    } elseif ($action === 'create_group') {
        $contact_ids = $_POST['contact_ids'] ?? [];
        $group_name = trim($_POST['group_name'] ?? '');
        $primary_id = intval($_POST['primary_id'] ?? 0);

        if (empty($contact_ids) || $primary_id <= 0) {
            throw new Exception('Missing contact IDs or primary contact.');
        }

        if (empty($group_name)) {
            $group_name = 'Group ' . date('Y-m-d');
        }

        $pdo->beginTransaction();
        // Create group
        $stmt = $pdo->prepare("INSERT INTO groups (name, primary_contact_id) VALUES (?, ?)");
        $stmt->execute([$group_name, $primary_id]);
        $group_id = $pdo->lastInsertId();

        // Add members
        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, contact_id, role) VALUES (?, ?, 'client')");
        foreach ($contact_ids as $id) {
            // Skip if already in group (optional)
            $stmt->execute([$group_id, (int) $id]);
        }
        $pdo->commit();

        $response = ['success' => true, 'message' => 'Group created.', 'group_id' => $group_id];
    } elseif ($action === 'delete_group') {
        $group_id = intval($_POST['group_id'] ?? 0);
        if ($group_id <= 0) {
            throw new Exception('Invalid group ID.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);

        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $pdo->commit();

        $response = ['success' => true, 'message' => 'Group deleted.'];
    } elseif ($action === 'remove_member') {
        $contact_id = intval($_POST['contact_id'] ?? 0);
        if ($contact_id <= 0) {
            throw new Exception('Invalid contact ID.');
        }

        $stmt = $pdo->prepare("DELETE FROM group_members WHERE contact_id = ?");
        $stmt->execute([$contact_id]);

        $response = ['success' => true, 'message' => 'Contact removed from group.'];
    } elseif ($action === 'get_groups') {
        $stmt = $pdo->prepare("
            SELECT 
                g.id, 
                g.name, 
                g.primary_contact_id, 
                c.name as primary_name,
                COUNT(gm.contact_id) as member_count
            FROM groups g
            JOIN contacts c ON g.primary_contact_id = c.id
            LEFT JOIN group_members gm ON g.id = gm.group_id
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute();
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'groups' => $groups];
        
    } elseif ($action === 'get_group_members') {
        $group_id = intval($_GET['group_id'] ?? 0);
        if ($group_id <= 0) {
            throw new Exception('Invalid group ID.');
        }

        $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.phone
        FROM contacts c
        JOIN group_members gm ON c.id = gm.contact_id
        WHERE gm.group_id = ?
        ORDER BY c.name
    ");
        $stmt->execute([$group_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'members' => $members];
    } else {
        $response['message'] = 'Unsupported action.';
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Groups API error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>