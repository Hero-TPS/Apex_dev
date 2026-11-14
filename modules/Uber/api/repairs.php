<?php
// modules/Uber/api/repairs.php
// Vehicle repairs API: add, update, close/reopen and delete a repair.
// Payments are not handled here — they are weekly Additional Cost rows
// (see modules/Uber/api/index.php) that point at a repair through repair_id.

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/../helper.php';

header('Content-Type: application/json');
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    switch ($action) {
        case 'add':
            handleAddRepair();
            break;
        case 'update':
            handleUpdateRepair();
            break;
        case 'set_closed':
            handleSetRepairClosed();
            break;
        case 'delete':
            handleDeleteRepair();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (PDOException $e) {
    logError('UBER_REPAIRS', 'Database error', ['error' => $e->getMessage(), 'action' => $action]);
    jsonResponse([
        'success' => false,
        'message' => 'Database error. If this is the first time, run the pending updates under Services → Database Updates.',
    ], 500);
} catch (Throwable $e) {
    logCritical('UBER_REPAIRS', 'Unhandled exception', ['error' => $e->getMessage(), 'action' => $action]);
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
}

// ========== HANDLERS ==========

function handleAddRepair(): void
{
    global $pdo;

    $in = readRepairInput();

    $stmt = $pdo->prepare("
        INSERT INTO uber_vehicle_repairs (description, repair_date, total_amount, weekly_amount, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$in['description'], $in['repair_date'], $in['total_amount'], $in['weekly_amount'], $in['notes']]);

    logInfo('UBER_REPAIRS', 'Vehicle repair added', [
        'repair_id'    => (int) $pdo->lastInsertId(),
        'total_amount' => $in['total_amount'],
    ]);

    jsonResponse(['success' => true, 'message' => 'Repair added.']);
}

function handleUpdateRepair(): void
{
    global $pdo;

    $id = requireRepairId();
    $in = readRepairInput();

    $stmt = $pdo->prepare("
        UPDATE uber_vehicle_repairs
        SET description = ?, repair_date = ?, total_amount = ?, weekly_amount = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$in['description'], $in['repair_date'], $in['total_amount'], $in['weekly_amount'], $in['notes'], $id]);

    logInfo('UBER_REPAIRS', 'Vehicle repair updated', ['repair_id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Repair updated.']);
}

/**
 * Close a repair by hand (e.g. settled some other way) or reopen it.
 * A repair that is fully paid off needs no closing — it shows as paid off by itself.
 *
 * POST id, closed (1 = close, 0 = reopen)
 */
function handleSetRepairClosed(): void
{
    global $pdo;

    $id     = requireRepairId();
    $closed = (string) ($_POST['closed'] ?? '') === '1' ? 1 : 0;

    $pdo->prepare("UPDATE uber_vehicle_repairs SET is_closed = ? WHERE id = ?")->execute([$closed, $id]);

    logInfo('UBER_REPAIRS', $closed ? 'Vehicle repair closed' : 'Vehicle repair reopened', ['repair_id' => $id]);

    jsonResponse(['success' => true, 'message' => $closed ? 'Repair closed.' : 'Repair reopened.']);
}

/**
 * A repair that already has payments linked cannot be deleted — that would
 * leave the weekly entries paying towards nothing. Close it instead, or
 * unlink the payments by editing those weeks.
 */
function handleDeleteRepair(): void
{
    global $pdo;

    $id = requireRepairId();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM uber_additional_costs WHERE repair_id = ?");
    $countStmt->execute([$id]);
    $payments = (int) $countStmt->fetchColumn();

    if ($payments > 0) {
        jsonResponse([
            'success' => false,
            'message' => "This repair has $payments payment" . ($payments === 1 ? '' : 's')
                . ' linked to it, so it cannot be deleted. Close it instead.',
        ], 409);
    }

    $pdo->prepare("DELETE FROM uber_vehicle_repairs WHERE id = ?")->execute([$id]);

    logInfo('UBER_REPAIRS', 'Vehicle repair deleted', ['repair_id' => $id]);

    jsonResponse(['success' => true, 'message' => 'Repair deleted.']);
}

// ========== HELPERS ==========

/**
 * Read the posted repair id and make sure that repair exists.
 */
function requireRepairId(): int
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid repair ID.'], 400);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uber_vehicle_repairs WHERE id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() === 0) {
        jsonResponse(['success' => false, 'message' => 'Repair not found.'], 404);
    }

    return $id;
}

/**
 * Read and validate the repair form fields shared by add and update.
 *
 * @return array{description: string, repair_date: string, total_amount: float, weekly_amount: ?float, notes: ?string}
 */
function readRepairInput(): array
{
    $description = trim((string) ($_POST['description'] ?? ''));
    $dateRaw     = trim((string) ($_POST['repair_date'] ?? ''));
    $totalRaw    = trim((string) ($_POST['total_amount'] ?? ''));
    $weeklyRaw   = trim((string) ($_POST['weekly_amount'] ?? ''));
    $notes       = trim((string) ($_POST['notes'] ?? ''));

    if ($description === '') {
        jsonResponse(['success' => false, 'message' => 'Please describe the repair.'], 400);
    }
    if (mb_strlen($description) > 255) {
        jsonResponse(['success' => false, 'message' => 'The description is too long (255 characters at most).'], 400);
    }

    $date = DateTime::createFromFormat('Y-m-d', $dateRaw);
    if (!$date || $date->format('Y-m-d') !== $dateRaw) {
        jsonResponse(['success' => false, 'message' => 'Please choose a valid repair date.'], 400);
    }

    if (!is_numeric($totalRaw) || (float) $totalRaw <= 0 || (float) $totalRaw > 99999999.99) {
        jsonResponse(['success' => false, 'message' => 'The total cost must be an amount greater than zero.'], 400);
    }

    $weekly = null;
    if ($weeklyRaw !== '') {
        if (!is_numeric($weeklyRaw) || (float) $weeklyRaw < 0 || (float) $weeklyRaw > 99999999.99) {
            jsonResponse(['success' => false, 'message' => 'The suggested weekly amount must be zero or more.'], 400);
        }
        $weekly = (float) $weeklyRaw > 0 ? round((float) $weeklyRaw, 2) : null;
    }

    if (mb_strlen($notes) > 1000) {
        jsonResponse(['success' => false, 'message' => 'The notes are too long (1000 characters at most).'], 400);
    }

    return [
        'description'   => $description,
        'repair_date'   => $dateRaw,
        'total_amount'  => round((float) $totalRaw, 2),
        'weekly_amount' => $weekly,
        'notes'         => $notes !== '' ? $notes : null,
    ];
}
