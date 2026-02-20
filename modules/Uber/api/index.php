<?php
// modules/Uber/api/index.php

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

function jsonResponse(array $payload, int $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

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
        $stmt = $pdo->query("
            SELECT * FROM uber_income 
            ORDER BY week_start DESC
        ");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add week_display for each record
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
        }

        jsonResponse(['success' => true, 'data' => $records]);
        
    } catch (PDOException $e) {
        logError('UBER', 'Failed to fetch Uber income records', [
            'error' => $e->getMessage()
        ]);
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
        
        jsonResponse(['success' => true, 'record' => $record]);
        
    } catch (PDOException $e) {
        logError('UBER', 'Failed to fetch single Uber record', [
            'error' => $e->getMessage(),
            'record_id' => $id
        ]);
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
        logError('UBER', 'Failed to check if week exists', [
            'error' => $e->getMessage(),
            'week_start' => $weekStart
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleAdd()
{
    global $pdo;
    
    try {
        $weekStart = intval($_POST['week_monday_unix'] ?? 0);
        $weekEnd = intval($_POST['week_sunday_unix'] ?? ($weekStart + 604799));
        $totalIncome = floatval($_POST['total_income'] ?? 0);
        $cashReceived = floatval($_POST['cash_received'] ?? 0);
        $mobileDataCost = floatval($_POST['mobile_data_cost'] ?? 0);
        $totalTrips = intval($_POST['total_trips'] ?? 0);
        $totalTimeOnline = floatval($_POST['total_time_online'] ?? 0);

        if ($totalIncome <= 0) {
            jsonResponse(['success' => false, 'message' => 'Total income must be greater than zero'], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO uber_income 
            (week_start, week_end, total_income, cash_received, mobile_data_cost, total_trips, total_time_online, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $weekStart,
            $weekEnd,
            $totalIncome,
            $cashReceived,
            $mobileDataCost,
            $totalTrips,
            $totalTimeOnline
        ]);

        logInfo('UBER', 'Uber income created', [
            'record_id' => $pdo->lastInsertId(),
            'week_start' => date('Y-m-d', $weekStart),
            'total_income' => $totalIncome
        ]);

        jsonResponse(['success' => true, 'message' => 'Uber income saved successfully']);
        
    } catch (PDOException $e) {
        logError('UBER', 'Failed to create Uber income', [
            'error' => $e->getMessage()
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to save Uber income'], 500);
    }
}

function handleUpdate()
{
    global $pdo;
    
    try {
        $id = intval($_POST['id'] ?? 0);
        $totalIncome = floatval($_POST['total_income'] ?? 0);
        $cashReceived = floatval($_POST['cash_received'] ?? 0);
        $mobileDataCost = floatval($_POST['mobile_data_cost'] ?? 0);
        $totalTrips = intval($_POST['total_trips'] ?? 0);
        $totalTimeOnline = floatval($_POST['total_time_online'] ?? 0);

        if ($id <= 0 || $totalIncome <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE uber_income 
            SET total_income = ?, 
                cash_received = ?, 
                mobile_data_cost = ?, 
                total_trips = ?, 
                total_time_online = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $totalIncome,
            $cashReceived,
            $mobileDataCost,
            $totalTrips,
            $totalTimeOnline,
            $id
        ]);

        logInfo('UBER', 'Uber income updated', [
            'record_id' => $id
        ]);

        jsonResponse(['success' => true, 'message' => 'Uber income updated successfully']);
        
    } catch (PDOException $e) {
        logError('UBER', 'Failed to update Uber income', [
            'error' => $e->getMessage(),
            'record_id' => $id ?? null
        ]);
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
        $stmt = $pdo->prepare("DELETE FROM uber_income WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            logInfo('UBER', 'Uber income deleted', [
                'record_id' => $id
            ]);
            
            jsonResponse(['success' => true, 'message' => 'Uber income deleted successfully']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
        }
        
    } catch (PDOException $e) {
        logError('UBER', 'Failed to delete Uber income', [
            'error' => $e->getMessage(),
            'record_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete Uber income'], 500);
    }
}