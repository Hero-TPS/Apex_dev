<?php
// modules/Maintenance/api/index.php

require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];
$messages = [];

/**
 * Sync a table with plain-text list input (one item per line)
 */
function syncTable(PDO $pdo, string $tableName, string $columnName, string $postedData): string
{
    // Normalize line endings and remove empty/duplicate lines
    $formItems = array_unique(
        array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $postedData)),
            fn($item) => !empty($item)
        )
    );

    // Fetch current DB items
    $stmt = $pdo->query("SELECT `$columnName` FROM `$tableName`");
    $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $itemsToAdd = array_diff($formItems, $dbItems);
    $itemsToDelete = array_diff($dbItems, $formItems);

    $added_count = 0;
    if (!empty($itemsToAdd)) {
        $sql_insert = "INSERT IGNORE INTO `$tableName` (`$columnName`) VALUES (?)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($itemsToAdd as $item) {
            $stmt_insert->execute([$item]);
            if ($stmt_insert->rowCount() > 0) {
                $added_count++;
            }
        }
    }

    $deleted_count = 0;
    if (!empty($itemsToDelete)) {
        $sql_delete = "DELETE FROM `$tableName` WHERE `$columnName` = ?";
        $stmt_delete = $pdo->prepare($sql_delete);

        foreach ($itemsToDelete as $item) {
            $stmt_delete->execute([$item]);
            $deleted_count += $stmt_delete->rowCount();
        }
    }

    $singular = rtrim($tableName, 's');
    return "Added {$added_count} and removed {$deleted_count} {$singular}(s).";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $destinations = $_POST['destinations'] ?? '';
        $costs = $_POST['costs'] ?? '';
        $durations = $_POST['durations'] ?? '';

        if (!empty($destinations)) {
            $messages[] = syncTable($pdo, 'destinations', 'name', $destinations);
            logInfo('MAINTENANCE', 'Updated destinations', [
                'count' => count(array_filter(explode("\n", $destinations)))
            ]);
        }
        
        if (!empty($costs)) {
            $messages[] = syncTable($pdo, 'costs', 'amount', $costs);
            logInfo('MAINTENANCE', 'Updated costs', [
                'count' => count(array_filter(explode("\n", $costs)))
            ]);
        }
        
        if (!empty($durations)) {
            $messages[] = syncTable($pdo, 'durations', 'hours', $durations);
            logInfo('MAINTENANCE', 'Updated durations', [
                'count' => count(array_filter(explode("\n", $durations)))
            ]);
        }

        if (empty(trim($destinations)) && empty(trim($costs)) && empty(trim($durations))) {
            $response['message'] = "No data was provided to update.";
            $response['success'] = false;
        } else {
            $response['success'] = true;
            $response['message'] = implode(" ", $messages);
        }
        
    } catch (Exception $e) {
        logError('MAINTENANCE', 'Failed to update maintenance data', [
            'error' => $e->getMessage()
        ]);
        $response['message'] = 'An error occurred while updating data.';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);