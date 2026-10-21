<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // Ensure $pdo is defined here

$response = ['success' => false, 'message' => ''];
$messages = [];

/**
 * Sync a table with plain-text list input (one item per line)
 * ⚠️ Only call with hardcoded, trusted table/column names!
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
    $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Returns flat array

    $itemsToAdd = array_diff($formItems, $dbItems);
    $itemsToDelete = array_diff($dbItems, $formItems);

    $added_count = 0;
    if (!empty($itemsToAdd)) {
        // Use INSERT IGNORE to skip duplicates
        $sql_insert = "INSERT IGNORE INTO `$tableName` (`$columnName`) VALUES (?)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($itemsToAdd as $item) {
            // All values treated as strings — PDO handles type binding safely
            // (Even for 'amount' or 'hours', MariaDB will cast string to numeric if column is numeric)
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

    $singular = rtrim($tableName, 's'); // e.g., "destinations" → "destination"
    return "Added {$added_count} and removed {$deleted_count} {$singular}(s).";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate and sanitize input early
    $destinations = $_POST['destinations'] ?? '';
    $costs = $_POST['costs'] ?? '';
    $durations = $_POST['durations'] ?? '';

    if (!empty($destinations)) {
        $messages[] = syncTable($pdo, 'destinations', 'name', $destinations);
    }
    if (!empty($costs)) {
        $messages[] = syncTable($pdo, 'costs', 'amount', $costs);
    }
    if (!empty($durations)) {
        $messages[] = syncTable($pdo, 'durations', 'hours', $durations);
    }

    if (empty(trim($destinations)) && empty(trim($costs)) && empty(trim($durations))) {
        $response['message'] = "⚠️ No data was provided to update.";
        $response['success'] = false;
    } else {
        $response['success'] = true;
        $response['message'] = "✅ " . implode(" ", $messages);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);