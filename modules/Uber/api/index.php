<?php
// modules/Uber/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php'; 

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'get_all';

try {
    switch ($action) {
        case 'get_all':
            handleGetAll();
            break;
        case 'get_single':
            handleGetSingle();
            break;
        case 'check_exists':
            handleCheckExists();
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
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('UBER_API', 'Unhandled exception', [
        'error' => $e->getMessage(),
        'action' => $action
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleGetAll()
{
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT * FROM uber_income ORDER BY week_start DESC");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each record, add week_display and fetch additional costs
        foreach ($records as &$record) {
            if (isset($record['week_start']) && $record['week_start'] > 0) {
                $start = new DateTime();
                $start->setTimestamp((int) $record['week_start']);
                $start->setTimezone(new DateTimeZone(TIME_ZONE));
                $end = clone $start;
                $end->modify('+6 days');
                $record['week_display'] = $start->format('d M Y') . ' – ' . $end->format('d M Y');
            } else {
                $record['week_display'] = 'Invalid Date';
            }

            // Fetch additional costs for this record
            $costStmt = $pdo->prepare("SELECT id, reason, amount FROM uber_additional_costs WHERE uber_income_id = ? ORDER BY id ASC");
            $costStmt->execute([$record['id']]);
            $record['additional_costs'] = $costStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        jsonResponse(['success' => true, 'data' => $records]);

    } catch (PDOException $e) {
        logError('UBER', 'Failed to fetch Uber income records', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleGetSingle()
{
    global $pdo;

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid record ID'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM uber_income WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
        }

        // Fetch additional costs
        $costStmt = $pdo->prepare("SELECT id, reason, amount FROM uber_additional_costs WHERE uber_income_id = ? ORDER BY id ASC");
        $costStmt->execute([$id]);
        $record['additional_costs'] = $costStmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['success' => true, 'record' => $record]);

    } catch (PDOException $e) {
        logError('UBER', 'Failed to fetch single Uber record', ['error' => $e->getMessage(), 'record_id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleCheckExists()
{
    global $pdo;

    $weekStart = intval($_POST['week_monday_unix'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT id FROM uber_income WHERE week_start = ?");
        $stmt->execute([$weekStart]);
        $exists = $stmt->fetch() !== false;

        jsonResponse(['exists' => $exists]);

    } catch (PDOException $e) {
        logError('UBER', 'Failed to check if week exists', ['error' => $e->getMessage(), 'week_start' => $weekStart]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleAdd()
{
    global $pdo;

    try {
        $weekMonday    = trim($_POST['week_monday'] ?? '');
        $totalIncome   = floatval($_POST['total_income'] ?? 0);
        $cashReceived  = floatval($_POST['cash_received'] ?? 0);
        $totalTrips    = intval($_POST['total_trips'] ?? 0);
        $totalTimeOnline = floatval($_POST['total_time_online'] ?? 0);

        // Additional costs come in as two parallel arrays: reasons[] and amounts[]
        $reasons = $_POST['cost_reasons'] ?? [];
        $amounts = $_POST['cost_amounts'] ?? [];

        if (empty($weekMonday)) {
            jsonResponse(['success' => false, 'message' => 'Week start date is required'], 400);
        }

        if ($totalIncome <= 0) {
            jsonResponse(['success' => false, 'message' => 'Total income must be greater than zero'], 400);
        }

        $tz = new DateTimeZone(TIME_ZONE);
        $dt = new DateTime($weekMonday, $tz);
        $dt->setTime(0, 0, 0);
        $weekStart = $dt->getTimestamp();

        $endDt = clone $dt;
        $endDt->modify('+6 days')->setTime(23, 59, 59);
        $weekEnd = $endDt->getTimestamp();

        // Insert uber_income record
        $stmt = $pdo->prepare("
            INSERT INTO uber_income 
            (week_start, week_end, total_income, cash_received, total_trips, total_time_online, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$weekStart, $weekEnd, $totalIncome, $cashReceived, $totalTrips, $totalTimeOnline]);

        $uberIncomeId = $pdo->lastInsertId();

        // Insert additional costs
        saveAdditionalCosts($pdo, $uberIncomeId, $reasons, $amounts);

        logInfo('UBER', 'Uber income created', [
            'record_id'  => $uberIncomeId,
            'week_start' => date('Y-m-d', $weekStart),
            'total_income' => $totalIncome
        ]);

        jsonResponse(['success' => true, 'message' => 'Uber income saved successfully']);

    } catch (PDOException $e) {
        logError('UBER', 'Failed to create Uber income', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save Uber income'], 500);
    }
}

function handleUpdate()
{
    global $pdo;

    try {
        $id            = intval($_POST['id'] ?? 0);
        $totalIncome   = floatval($_POST['total_income'] ?? 0);
        $cashReceived  = floatval($_POST['cash_received'] ?? 0);
        $totalTrips    = intval($_POST['total_trips'] ?? 0);
        $totalTimeOnline = floatval($_POST['total_time_online'] ?? 0);

        // Additional costs come in as two parallel arrays: reasons[] and amounts[]
        $reasons = $_POST['cost_reasons'] ?? [];
        $amounts = $_POST['cost_amounts'] ?? [];

        if ($id <= 0 || $totalIncome <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE uber_income 
            SET total_income = ?, cash_received = ?, total_trips = ?, total_time_online = ?
            WHERE id = ?
        ");
        $stmt->execute([$totalIncome, $cashReceived, $totalTrips, $totalTimeOnline, $id]);

        // Delete existing additional costs and re-save
        $pdo->prepare("DELETE FROM uber_additional_costs WHERE uber_income_id = ?")->execute([$id]);
        saveAdditionalCosts($pdo, $id, $reasons, $amounts);

        logInfo('UBER', 'Uber income updated', ['record_id' => $id]);

        jsonResponse(['success' => true, 'message' => 'Uber income updated successfully']);

    } catch (PDOException $e) {
        logError('UBER', 'Failed to update Uber income', ['error' => $e->getMessage(), 'record_id' => $id ?? null]);
        jsonResponse(['success' => false, 'message' => 'Failed to update Uber income'], 500);
    }
}

function handleDelete()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid record ID'], 400);
    }

    try {
        // Delete additional costs first (no foreign key cascade)
        $pdo->prepare("DELETE FROM uber_additional_costs WHERE uber_income_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM uber_income WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            logInfo('UBER', 'Uber income deleted', ['record_id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Uber income deleted successfully']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
        }

    } catch (PDOException $e) {
        logError('UBER', 'Failed to delete Uber income', ['error' => $e->getMessage(), 'record_id' => $id]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete Uber income'], 500);
    }
}

// ========== HELPERS ==========

function saveAdditionalCosts(PDO $pdo, int $uberIncomeId, array $reasons, array $amounts): void
{
    $stmt = $pdo->prepare("INSERT INTO uber_additional_costs (uber_income_id, reason, amount) VALUES (?, ?, ?)");
    foreach ($reasons as $i => $reason) {
        $reason = trim($reason);
        $amount = floatval($amounts[$i] ?? 0);
        if ($reason !== '' && $amount > 0) {
            $stmt->execute([$uberIncomeId, $reason, $amount]);
        }
    }
}