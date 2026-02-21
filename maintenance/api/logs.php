<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

$response = ['success' => false, 'message' => 'Invalid action'];

$action = $_GET['action'] ?? '';

try {
    if ($action === 'clear_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get count before deletion
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_logs");
        $count = $stmt->fetchColumn();
        
        // Delete all logs
        $pdo->exec("TRUNCATE TABLE system_logs");
        
        // Log this action (will be the only log after truncate)
        logInfo('MAINTENANCE', 'All system logs cleared', [
            'logs_deleted' => $count,
            'cleared_by' => 'admin'
        ]);
        
        $response = [
            'success' => true,
            'message' => "Cleared {$count} log entries"
        ];
        
    } else {
        $response['message'] = 'Unsupported action or method';
    }
    
} catch (Exception $e) {
    logError('MAINTENANCE', 'Failed to clear logs', [
        'error' => $e->getMessage()
    ]);
    $response = [
        'success' => false,
        'message' => 'Failed to clear logs: ' . $e->getMessage()
    ];64
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);