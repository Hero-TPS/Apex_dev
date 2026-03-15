<?php
// modules/Fuel/api/index.php

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
        case 'add':
            handleAdd();
            break;
        case 'update':
            handleUpdate();
            break;
        case 'delete':
            handleDelete();
            break;
        case 'weekly':
            handleWeekly();
            break;
        case 'monthly':
            handleMonthly();
            break;
        case 'weekly_fuel_by_month':
            handleWeeklyFuelByMonth();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('FUEL_API', 'Unhandled exception', [
        'error'  => $e->getMessage(),
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
            SELECT 
                id,
                log_timestamp,
                odo_km,
                trip_km,
                fuel_price,
                total_cost,
                payment_method
            FROM fuel_logs 
            ORDER BY log_timestamp DESC
        ");

        $data = [];
        while ($row = $stmt->fetch()) {
            $dt = new DateTime();
            $dt->setTimestamp($row['log_timestamp']);
            $dt->setTimezone(new DateTimeZone(TIME_ZONE));
            $data[] = [
                'id'             => $row['id'],
                'log_datetime'   => $dt->format('d-m-Y H:i'),
                'odo_km'         => $row['odo_km'],
                'trip_km'        => $row['trip_km'],
                'fuel_price'     => $row['fuel_price'],
                'total_cost'     => $row['total_cost'],
                'payment_method' => $row['payment_method']
            ];
        }

        jsonResponse(['success' => true, 'data' => $data]);

    } catch (PDOException $e) {
        logError('FUEL', 'Failed to fetch fuel logs', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleGetSingle()
{
    global $pdo;

    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid fuel log ID'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM fuel_logs WHERE id = ?");
        $stmt->execute([$id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            jsonResponse(['success' => false, 'message' => 'Fuel log not found'], 404);
        }

        jsonResponse(['success' => true, 'log' => $log]);

    } catch (PDOException $e) {
        logError('FUEL', 'Failed to fetch single fuel log', [
            'error'  => $e->getMessage(),
            'log_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleAdd()
{
    global $pdo;

    try {
        $log_timestamp   = intval($_POST['log_timestamp'] ?? 0);
        $meter_type      = $_POST['meter_type'] ?? 'trip';
        $km_value        = floatval($_POST['km_value'] ?? 0);
        $calculated_trip = floatval($_POST['calculated_trip'] ?? 0);
        $fuel_price      = floatval($_POST['fuel_price'] ?? 0);
        $total_cost      = floatval($_POST['total_cost'] ?? 0);
        $payment_method  = ($_POST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash';

        if ($log_timestamp <= 0 || $km_value <= 0 || $fuel_price <= 0 || $total_cost <= 0) {
            jsonResponse(['success' => false, 'message' => 'All values must be greater than zero'], 400);
        }

        // Get last odometer
        $last_odo = 0;
        $stmt = $pdo->query("SELECT odo_km FROM fuel_logs ORDER BY id DESC LIMIT 1");
        if ($row = $stmt->fetch()) {
            $last_odo = $row['odo_km'];
        }

        // Calculate values
        if ($meter_type === 'trip') {
            $trip_km = $km_value;
            $odo_km  = $last_odo + $km_value;
        } else {
            $odo_km  = $km_value;
            $trip_km = $calculated_trip;
        }

        $stmt = $pdo->prepare("
            INSERT INTO fuel_logs (log_timestamp, odo_km, trip_km, fuel_price, total_cost, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$log_timestamp, $odo_km, $trip_km, $fuel_price, $total_cost, $payment_method]);

        logInfo('FUEL', 'Fuel log created', [
            'log_id'  => $pdo->lastInsertId(),
            'odo_km'  => $odo_km,
            'trip_km' => $trip_km
        ]);

        jsonResponse(['success' => true, 'message' => 'Fuel log saved successfully']);

    } catch (PDOException $e) {
        logError('FUEL', 'Failed to create fuel log', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Failed to save fuel log'], 500);
    }
}

function handleUpdate()
{
    global $pdo;

    try {
        $id             = intval($_POST['id'] ?? 0);
        $log_timestamp  = intval($_POST['log_timestamp'] ?? 0);
        $fuel_price     = floatval($_POST['fuel_price'] ?? 0);
        $total_cost     = floatval($_POST['total_cost'] ?? 0);
        $payment_method = ($_POST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash';

        if ($id <= 0 || $log_timestamp <= 0 || $fuel_price <= 0 || $total_cost <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid data'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE fuel_logs 
            SET log_timestamp = ?, fuel_price = ?, total_cost = ?, payment_method = ? 
            WHERE id = ?
        ");
        $stmt->execute([$log_timestamp, $fuel_price, $total_cost, $payment_method, $id]);

        logInfo('FUEL', 'Fuel log updated', ['log_id' => $id]);

        jsonResponse(['success' => true, 'message' => 'Fuel log updated successfully']);

    } catch (PDOException $e) {
        logError('FUEL', 'Failed to update fuel log', [
            'error'  => $e->getMessage(),
            'log_id' => $id ?? null
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to update fuel log'], 500);
    }
}

function handleDelete()
{
    global $pdo;

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid fuel log ID'], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM fuel_logs WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            logInfo('FUEL', 'Fuel log deleted', ['log_id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Fuel log deleted successfully']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Fuel log not found'], 404);
        }

    } catch (PDOException $e) {
        logError('FUEL', 'Failed to delete fuel log', [
            'error'  => $e->getMessage(),
            'log_id' => $id
        ]);
        jsonResponse(['success' => false, 'message' => 'Failed to delete fuel log'], 500);
    }
}

function handleWeekly()
{
    global $pdo;

    try {
        // Current month + 2 months prior = start of 2 months ago to end of current month
        $stmt = $pdo->query("
            SELECT
                YEAR(FROM_UNIXTIME(log_timestamp))                          AS year,
                WEEK(FROM_UNIXTIME(log_timestamp), 1)                       AS week_number,
                MIN(DATE(FROM_UNIXTIME(log_timestamp)))                     AS week_start,
                MAX(DATE(FROM_UNIXTIME(log_timestamp)))                     AS week_end,
                COUNT(*)                                                    AS fill_count,
                SUM(trip_km)                                                AS total_km,
                SUM(total_cost)                                             AS total_cost,
                SUM(total_cost / NULLIF(fuel_price, 0))                     AS total_liters
            FROM fuel_logs
            WHERE FROM_UNIXTIME(log_timestamp) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01')
              AND FROM_UNIXTIME(log_timestamp) <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
            GROUP BY year, week_number
            ORDER BY year ASC, week_number ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($row) {
            $start  = new DateTime($row['week_start']);
            $end    = new DateTime($row['week_end']);
            $wKm    = (float) $row['total_km'];
            $wCost  = (float) $row['total_cost'];
            $wLiters = (float) ($row['total_liters'] ?? 0);
            return [
                'week_label'  => $start->format('d M') . ' – ' . $end->format('d M Y'),
                'fill_count'  => (int) $row['fill_count'],
                'total_km'    => $wKm,
                'total_cost'  => $wCost,
                'cost_per_km' => ($wKm     > 0) ? ($wCost   / $wKm)        : 0.0,
                'km_per_l'    => ($wLiters > 0) ? ($wKm     / $wLiters)    : 0.0,
                'l_per_100km' => ($wKm     > 0) ? ($wLiters / $wKm * 100)  : 0.0,
            ];
        }, $rows);

        jsonResponse(['success' => true, 'data' => $data]);

    } catch (PDOException $e) {
        logError('FUEL', 'Weekly report failed', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}

function handleMonthly()
{
    global $pdo;

    try {
        // Past 3 months (rolling: current month + 2 prior)
        $stmt = $pdo->query("
            SELECT
                MONTH(FROM_UNIXTIME(log_timestamp))     AS month_number,
                MONTHNAME(FROM_UNIXTIME(log_timestamp)) AS month_name,
                YEAR(FROM_UNIXTIME(log_timestamp))      AS year,
                COUNT(*)                                AS fill_count,
                SUM(trip_km)                            AS total_km,
                SUM(total_cost)                         AS total_cost,
                SUM(total_cost / NULLIF(fuel_price, 0)) AS total_liters
            FROM fuel_logs
            WHERE FROM_UNIXTIME(log_timestamp) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m-01')
              AND FROM_UNIXTIME(log_timestamp) <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
            GROUP BY year, month_number, month_name
            ORDER BY year ASC, month_number ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($row) {
            $mKm    = (float) $row['total_km'];
            $mCost  = (float) $row['total_cost'];
            $mLiters = (float) ($row['total_liters'] ?? 0);
            return [
                'month_label' => $row['month_name'] . ' ' . $row['year'],
                'fill_count'  => (int) $row['fill_count'],
                'total_km'    => $mKm,
                'total_cost'  => $mCost,
                'cost_per_km' => ($mKm     > 0) ? ($mCost   / $mKm)        : 0.0,
                'km_per_l'    => ($mLiters > 0) ? ($mKm     / $mLiters)    : 0.0,
                'l_per_100km' => ($mKm     > 0) ? ($mLiters / $mKm * 100)  : 0.0,
            ];
        }, $rows);

        jsonResponse(['success' => true, 'data' => $data]);

    } catch (PDOException $e) {
        logError('FUEL', 'Monthly report failed', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}
function handleWeeklyFuelByMonth()
{
    global $pdo;

    $year  = $_GET['year']  ?? null;
    $month = $_GET['month'] ?? null;

    if (!$year || !$month || !checkdate((int) $month, 1, (int) $year)) {
        jsonResponse(['success' => false, 'message' => 'Invalid date'], 400);
        return;
    }

    try {
        $tz       = new DateTimeZone(TIME_ZONE);
        $firstDay = new DateTime(sprintf('%04d-%02d-01', (int) $year, (int) $month), $tz);
        if ($firstDay->format('N') !== '1') {
            $firstDay->modify('next monday');
        }
        $lastDay = new DateTime(sprintf('%04d-%02d-01', (int) $year, (int) $month), $tz);
        $lastDay->modify('last day of this month');

        $data    = [];
        $current = clone $firstDay;
        while ($current <= $lastDay) {
            $monday = clone $current;
            $monday->setTime(0, 0, 0);
            $sunday = clone $monday;
            $sunday->modify('+6 days');
            $sunday->setTime(23, 59, 59);

            $startUnix = $monday->getTimestamp();
            $endUnix   = $sunday->getTimestamp();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(COUNT(*), 0) AS fill_count,
                        COALESCE(SUM(trip_km), 0) AS total_km,
                        COALESCE(SUM(total_cost), 0) AS total_cost,
                        COALESCE(SUM(total_cost / NULLIF(fuel_price, 0)), 0) AS total_liters
                 FROM fuel_logs WHERE log_timestamp BETWEEN ? AND ?"
            );
            $stmt->execute([$startUnix, $endUnix]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $wKm     = (float) $row['total_km'];
            $wCost   = (float) $row['total_cost'];
            $wLiters = (float) $row['total_liters'];
            $data[] = [
                'week_label'  => $monday->format('d M') . ' – ' . $sunday->format('d M Y'),
                'fill_count'  => (int) $row['fill_count'],
                'total_km'    => $wKm,
                'total_cost'  => $wCost,
                'cost_per_km' => ($wKm     > 0) ? ($wCost   / $wKm)        : 0.0,
                'km_per_l'    => ($wLiters > 0) ? ($wKm     / $wLiters)    : 0.0,
                'l_per_100km' => ($wKm     > 0) ? ($wLiters / $wKm * 100)  : 0.0,
            ];

            $current->modify('+1 week');
        }

        jsonResponse(['success' => true, 'data' => $data]);

    } catch (PDOException $e) {
        logError('FUEL', 'Weekly-by-month report failed', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'message' => 'Database error'], 500);
    }
}
