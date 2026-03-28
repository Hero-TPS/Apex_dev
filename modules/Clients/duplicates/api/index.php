<?php
// modules/Clients/duplicates/api/index.php

require_once __DIR__ . '/../../../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';

requireApiLogin();
requireApiModulePermission($pdo, 'clients', 'view');

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_clusters':
            handleGetClusters();
            break;
        case 'link':
            handleLink();
            break;
        case 'dismiss':
            handleDismiss();
            break;
        case 'delete_contact':
            handleDeleteContact();
            break;
        case 'reassign_and_delete':
            handleReassignAndDelete();
            break;
        case 'check_duplicate':
            handleCheckDuplicate();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('DUPLICATES_API', 'Unhandled exception', [
        'error' => $e->getMessage(),
        'action' => $action
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleGetClusters()
{
    global $pdo;

    $type = $_GET['type'] ?? '';
    if (!in_array($type, ['phone', 'name', 'address'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid type'], 400);
    }

    try {
        // Fetch dismissed pairs for filtering
        $dismissedStmt = $pdo->query("SELECT contact_id_a, contact_id_b FROM duplicate_dismissals");
        $dismissedPairs = [];
        foreach ($dismissedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $a = (int)$row['contact_id_a'];
            $b = (int)$row['contact_id_b'];
            $dismissedPairs["{$a}_{$b}"] = true;
            $dismissedPairs["{$b}_{$a}"] = true;
        }

        // Fetch all contacts with booking count and link status
        $contacts = fetchContactsWithMeta($pdo);

        // Group contacts
        if ($type === 'phone') {
            $clusters = groupByPhone($contacts);
        } elseif ($type === 'name') {
            $clusters = groupByName($contacts);
        } else {
            $clusters = groupByAddress($contacts);
        }

        // Filter out dismissed pairs
        $filteredClusters = [];
        foreach ($clusters as $cluster) {
            $filtered = filterDismissedCluster($cluster, $dismissedPairs);
            if (count($filtered) >= 2) {
                $filteredClusters[] = array_values($filtered);
            }
        }

        jsonResponse(['success' => true, 'clusters' => $filteredClusters]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to get clusters', ['error' => $e->getMessage(), 'type' => $type]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function fetchContactsWithMeta(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            c.id,
            c.name,
            c.phone,
            c.address,
            c.email,
            c.date_added,
            (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count,
            (SELECT COUNT(*) FROM contact_links cl
             WHERE cl.contact_id_a = c.id OR cl.contact_id_b = c.id) > 0 AS is_linked
        FROM contacts c
        ORDER BY c.name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function groupByPhone(array $contacts): array
{
    $groups = [];
    foreach ($contacts as $c) {
        $normalised = formatPhoneNumberForWhatsApp($c['phone'] ?? '');
        if ($normalised === '') {
            continue;
        }
        $groups[$normalised][] = $c;
    }
    return array_values(array_filter($groups, fn($g) => count($g) >= 2));
}

function groupByName(array $contacts): array
{
    $groups = [];
    foreach ($contacts as $c) {
        $name = trim($c['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $key = soundex($name);
        $groups[$key][] = $c;
    }
    return array_values(array_filter($groups, fn($g) => count($g) >= 2));
}

function groupByAddress(array $contacts): array
{
    $groups = [];
    foreach ($contacts as $c) {
        $addr = strtolower(trim($c['address'] ?? ''));
        if ($addr === '') {
            continue;
        }
        $groups[$addr][] = $c;
    }
    return array_values(array_filter($groups, fn($g) => count($g) >= 2));
}

function filterDismissedCluster(array $cluster, array $dismissedPairs): array
{
    if (count($cluster) !== 2) {
        // For clusters > 2, remove members only if ALL their pairs are dismissed
        return $cluster;
    }
    $a = (int)$cluster[0]['id'];
    $b = (int)$cluster[1]['id'];
    if (isset($dismissedPairs["{$a}_{$b}"])) {
        return [];
    }
    return $cluster;
}

function handleLink()
{
    global $pdo;

    $idA = intval($_POST['id_a'] ?? 0);
    $idB = intval($_POST['id_b'] ?? 0);
    $linkType = trim($_POST['link_type'] ?? 'household');

    if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
        jsonResponse(['success' => false, 'message' => 'Invalid contact IDs'], 400);
    }

    // Normalise order
    if ($idA > $idB) {
        [$idA, $idB] = [$idB, $idA];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO contact_links (contact_id_a, contact_id_b, link_type)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$idA, $idB, $linkType]);

        logInfo('DUPLICATES', 'Contacts linked', [
            'contact_id_a' => $idA,
            'contact_id_b' => $idB,
            'link_type' => $linkType
        ]);

        jsonResponse(['success' => true]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to link contacts', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleDismiss()
{
    global $pdo;

    $idA = intval($_POST['id_a'] ?? 0);
    $idB = intval($_POST['id_b'] ?? 0);

    if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
        jsonResponse(['success' => false, 'message' => 'Invalid contact IDs'], 400);
    }

    // Normalise order
    if ($idA > $idB) {
        [$idA, $idB] = [$idB, $idA];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO duplicate_dismissals (contact_id_a, contact_id_b)
            VALUES (?, ?)
        ");
        $stmt->execute([$idA, $idB]);

        logInfo('DUPLICATES', 'Pair dismissed', [
            'contact_id_a' => $idA,
            'contact_id_b' => $idB
        ]);

        jsonResponse(['success' => true]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to dismiss pair', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleDeleteContact()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid contact ID'], 400);
    }

    try {
        // Check bookings
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE contact_id = ?");
        $stmt->execute([$id]);
        $bookingCount = (int)$stmt->fetchColumn();

        if ($bookingCount > 0) {
            jsonResponse([
                'success' => false,
                'message' => 'Client has bookings. Use Re-assign & Delete.'
            ], 400);
        }

        // Get name for logging
        $stmt = $pdo->prepare("SELECT name FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        if ($name === false) {
            jsonResponse(['success' => false, 'message' => 'Contact not found'], 404);
        }

        // Delete contact
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$id]);

        // Clean up contact_links
        $stmt = $pdo->prepare("DELETE FROM contact_links WHERE contact_id_a = ? OR contact_id_b = ?");
        $stmt->execute([$id, $id]);

        // Clean up duplicate_dismissals
        $stmt = $pdo->prepare("DELETE FROM duplicate_dismissals WHERE contact_id_a = ? OR contact_id_b = ?");
        $stmt->execute([$id, $id]);

        logInfo('DUPLICATES', 'Contact deleted', ['contact_id' => $id, 'name' => $name]);

        jsonResponse(['success' => true]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to delete contact', ['error' => $e->getMessage(), 'contact_id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleReassignAndDelete()
{
    global $pdo;

    $fromId = intval($_POST['from_id'] ?? 0);
    $toId   = intval($_POST['to_id'] ?? 0);

    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
        jsonResponse(['success' => false, 'message' => 'Invalid contact IDs'], 400);
    }

    try {
        // Validate both contacts exist
        $stmt = $pdo->prepare("SELECT id, name FROM contacts WHERE id IN (?, ?)");
        $stmt->execute([$fromId, $toId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!isset($rows[$fromId]) || !isset($rows[$toId])) {
            jsonResponse(['success' => false, 'message' => 'One or both contacts not found'], 404);
        }

        $oldName = $rows[$fromId];
        $date    = date('Y-m-d');

        // Move bookings: update contact_id and append note to description
        $stmt = $pdo->prepare("
            UPDATE bookings
            SET contact_id  = ?,
                description = CONCAT(COALESCE(description, ''), '\n\n[Re-assigned from: ', ?, ' on ', ?, ']'),
                updated_at  = NOW()
            WHERE contact_id = ?
        ");
        $stmt->execute([$toId, $oldName, $date, $fromId]);
        $bookingsMoved = $stmt->rowCount();

        // Delete the duplicate contact
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->execute([$fromId]);

        // Clean up contact_links and duplicate_dismissals
        $stmt = $pdo->prepare("DELETE FROM contact_links WHERE contact_id_a = ? OR contact_id_b = ?");
        $stmt->execute([$fromId, $fromId]);

        $stmt = $pdo->prepare("DELETE FROM duplicate_dismissals WHERE contact_id_a = ? OR contact_id_b = ?");
        $stmt->execute([$fromId, $fromId]);

        logInfo('DUPLICATES', 'Contact reassigned and deleted', [
            'from_id'       => $fromId,
            'to_id'         => $toId,
            'old_name'      => $oldName,
            'bookings_moved' => $bookingsMoved
        ]);

        jsonResponse(['success' => true, 'bookings_moved' => $bookingsMoved]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to reassign and delete contact', [
            'error'   => $e->getMessage(),
            'from_id' => $fromId,
            'to_id'   => $toId
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleCheckDuplicate()
{
    global $pdo;

    $name      = trim($_GET['name'] ?? '');
    $phone     = trim($_GET['phone'] ?? '');
    $excludeId = intval($_GET['exclude_id'] ?? 0);

    if ($name === '' && $phone === '') {
        jsonResponse(['success' => true, 'matches' => []]);
    }

    try {
        $normalisedPhone = $phone !== '' ? formatPhoneNumberForWhatsApp($phone) : '';

        // Collect candidates indexed by id to avoid duplicates
        $candidateMap = [];

        // Name-based candidates via SOUNDEX
        if ($name !== '') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.name, c.phone, c.address,
                       (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
                FROM contacts c
                WHERE SOUNDEX(c.name) = SOUNDEX(?)
                ORDER BY c.name ASC
                LIMIT 50
            ");
            $stmt->execute([$name]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $candidateMap[(int)$row['id']] = $row;
            }
        }

        // Phone-based candidates: fetch only contacts with a non-empty phone, normalise in PHP
        if ($normalisedPhone !== '') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.name, c.phone, c.address,
                       (SELECT COUNT(*) FROM bookings b WHERE b.contact_id = c.id) AS booking_count
                FROM contacts c
                WHERE c.phone IS NOT NULL AND c.phone <> ''
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cp = formatPhoneNumberForWhatsApp($row['phone'] ?? '');
                if ($cp !== '' && $cp === $normalisedPhone) {
                    $candidateMap[(int)$row['id']] = $row;
                }
            }
        }

        // Exclude the contact being edited
        if ($excludeId > 0) {
            unset($candidateMap[$excludeId]);
        }

        // Return up to 3
        $matches = array_slice(array_values($candidateMap), 0, 3);

        jsonResponse(['success' => true, 'matches' => $matches]);

    } catch (PDOException $e) {
        logError('DUPLICATES', 'Failed to check duplicates', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}
