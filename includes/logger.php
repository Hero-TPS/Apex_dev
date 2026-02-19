<?php
/**
 * Site-wide Database Logging System
 * Replaces error_log() with database storage
 */

/**
 * Main logging function
 * 
 * @param string $level    DEBUG|INFO|WARNING|ERROR|CRITICAL
 * @param string $category Category prefix, e.g., 'BOOKING', 'CALENDAR'
 * @param string $message  Log message
 * @param array  $context  Additional context data (optional)
 */
function logMessage(string $level, string $category, string $message, array $context = [])
{
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (timestamp, level, category, message, user_id, ip_address, url, context)
            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            strtoupper($level),
            strtoupper($category),
            $message,
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null
        ]);
        
    } catch (PDOException $e) {
        // Fallback: if database logging fails, use PHP error log
        error_log("Logger DB failed: " . $e->getMessage() . " | Original: [$level] $category: $message");
    }
}

/**
 * Log DEBUG level message
 */
function logDebug(string $category, string $message, array $context = [])
{
    logMessage('DEBUG', $category, $message, $context);
}

/**
 * Log INFO level message
 */
function logInfo(string $category, string $message, array $context = [])
{
    logMessage('INFO', $category, $message, $context);
}

/**
 * Log WARNING level message
 */
function logWarning(string $category, string $message, array $context = [])
{
    logMessage('WARNING', $category, $message, $context);
}

/**
 * Log ERROR level message
 */
function logError(string $category, string $message, array $context = [])
{
    logMessage('ERROR', $category, $message, $context);
}

/**
 * Log CRITICAL level message
 */
function logCritical(string $category, string $message, array $context = [])
{
    logMessage('CRITICAL', $category, $message, $context);
}

/**
 * Clean up logs older than specified days
 * 
 * @param int $days Number of days to keep (default 7)
 * @return int Number of deleted records
 */
function cleanOldLogs(int $days = 7): int
{
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM system_logs 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to clean old logs: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get log statistics
 * 
 * @param int $days Number of days to analyze
 * @return array Statistics
 */
function getLogStats(int $days = 7): array
{
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                level,
                category,
                COUNT(*) as count
            FROM system_logs
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY level, category
            ORDER BY level, count DESC
        ");
        $stmt->execute([$days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to get log stats: " . $e->getMessage());
        return [];
    }
}