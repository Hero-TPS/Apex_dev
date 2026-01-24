<?php
// financials/api.php
require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/financials/helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_weeks':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        $year = $_GET['year'] ?? null;
        $month = $_GET['month'] ?? null;
        if (!$year || !$month || !checkdate((int)$month, 1, (int)$year)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date']);
            break;
        }
        try {
            $weeks = getWeeklyBreakdownForMonth($pdo, (int)$year, (int)$month);
            echo json_encode(['success' => true, 'weeks' => $weeks]);
        } catch (Exception $e) {
            error_log('Financials API error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unsupported action: ' . htmlspecialchars($action)]);
}

