<?php

require_once '../config.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');
error_log("API Request: action=" . ($action ?? 'none') . ", POST=" . print_r($_POST, true));
$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'check_exists':
            $weekStart = (int) ($_POST['week_monday_unix'] ?? 0);
            $stmt = $pdo->prepare("SELECT id FROM uber_income WHERE week_start = ?");
            $stmt->execute([$weekStart]);
            $exists = $stmt->fetch() !== false;
            echo json_encode(['exists' => $exists]);
            break;

        case 'add':
            $weekStart = (int) ($_POST['week_monday_unix'] ?? 0);
            $weekEnd = (int) ($_POST['week_sunday_unix'] ?? ($weekStart + 604799));
            $totalIncome = (float) ($_POST['total_income'] ?? 0);
            $cashReceived = (float) ($_POST['cash_received'] ?? 0);
            $mobileDataCost = (float) ($_POST['mobile_data_cost'] ?? 0);
            $totalTrips = (int) ($_POST['total_trips'] ?? 0);
            $totalTimeOnline = (float) ($_POST['total_time_online'] ?? 0);

            if ($totalIncome <= 0) {
                throw new Exception('Total income must be greater than zero');
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

            echo json_encode(['success' => true, 'message' => 'Uber income saved']);
            break;

        case 'update':
            error_log("UPDATE received: " . print_r($_POST, true)); // 🔍 DEBUG
            $id = (int) ($_POST['id'] ?? 0);
            $totalIncome = (float) ($_POST['total_income'] ?? 0);
            $cashReceived = (float) ($_POST['cash_received'] ?? 0);
            $mobileDataCost = (float) ($_POST['mobile_data_cost'] ?? 0);
            $totalTrips = (int) ($_POST['total_trips'] ?? 0);
            $totalTimeOnline = (float) ($_POST['total_time_online'] ?? 0);

            if ($id <= 0 || $totalIncome <= 0) {
                throw new Exception('Invalid data');
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

            echo json_encode(['success' => true, 'message' => 'Uber income updated']);
            break;

        case 'get_all':
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

            echo json_encode(['success' => true, 'data' => $records]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported action']);
    }
} catch (Exception $e) {
    error_log('Uber API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>