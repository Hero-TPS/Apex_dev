<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log them

$response = ['success' => false, 'message' => 'Invalid action'];

$action = $_GET['action'] ?? '';

// Log the incoming request
error_log("Logs API called - Action: {$action}, Method: {$_SERVER['REQUEST_METHOD']}");

try {
    if ($action === 'clear_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("Starting clear_all operation");
        
        // Get count before deletion
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_logs");
        $count = $stmt->fetchColumn();
        error_log("Current log count: {$count}");
        
        // Delete all logs
        $result = $pdo->exec("DELETE FROM system_logs");
        error_log("Deleted {$result} rows");
        
        // Log this action
        try {
            logInfo('MAINTENANCE', 'All system logs cleared', [
                'logs_deleted' => $count,
                'cleared_by' => 'admin',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            error_log("Successfully logged the clear action");
        } catch (Exception $logError) {
            error_log("Failed to log clear action: " . $logError->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => "Cleared {$count} log entries"
        ];
        error_log("Clear operation completed successfully");
        
    } else {
        $response['message'] = 'Unsupported action or method';
        error_log("Unsupported action: {$action} or method: {$_SERVER['REQUEST_METHOD']}");
    }
    
} catch (PDOException $e) {
    $errorMsg = 'Database error: ' . $e->getMessage();
    error_log('PDO Exception in clear logs: ' . $errorMsg);
    $response = [
        'success' => false,
        'message' => $errorMsg
    ];
} catch (Exception $e) {
    $errorMsg = 'Failed to clear logs: ' . $e->getMessage();
    error_log('General Exception in clear logs: ' . $errorMsg);
    $response = [
        'success' => false,
        'message' => $errorMsg
    ];
}

error_log("API Response: " . json_encode($response));
echo json_encode($response, JSON_UNESCAPED_UNICODE);