<?php
// modules/Budgeting/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Budgeting/helper.php';

header('Content-Type: application/json');
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? 'get_recommendation';

try {
    switch ($action) {
        case 'get_recommendation':
            handleGetRecommendation();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('BUDGETING_API', 'Unhandled exception', [
        'error'  => $e->getMessage(),
        'action' => $action,
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
}

// ========== HANDLERS ==========

function handleGetRecommendation()
{
    global $pdo;

    $forceRefresh = !empty($_POST['force_refresh']);

    $forecast = getSevenDayForecast($pdo);
    $pace = getMonthlyPace($pdo);
    $result = getAiFactualBriefing($pdo, $forecast, $pace, $forceRefresh);

    jsonResponse($result);
}
