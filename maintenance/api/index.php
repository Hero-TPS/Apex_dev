<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';

$response = ['success' => false, 'message' => 'Invalid action.'];

$action = $_GET['action'] ?? '';

try {
    if ($action === 'update_lists' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $messages = [];

        // Destinations
        if (isset($_POST['destinations'])) {
            $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['destinations'])));
            $formItems = array_map('trim', $formItems);

            $stmt = $pdo->query("SELECT name FROM destinations");
            $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $itemsToAdd = array_diff($formItems, $dbItems);
            $itemsToDelete = array_diff($dbItems, $formItems);

            $addedCount = 0;
            $deletedCount = 0;

            if (!empty($itemsToAdd)) {
                $sql = "INSERT IGNORE INTO destinations (name) VALUES (?)";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToAdd as $item) {
                    $stmt->execute([$item]);
                    $addedCount++;
                }
            }
            if (!empty($itemsToDelete)) {
                $sql = "DELETE FROM destinations WHERE name = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToDelete as $item) {
                    $stmt->execute([$item]);
                    $deletedCount++;
                }
            }

            if ($addedCount > 0 || $deletedCount > 0) {
                $messages[] = "Destinations: added {$addedCount}, removed {$deletedCount}";
            }
        }

        // Costs
        if (isset($_POST['costs'])) {
            $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['costs'])));
            $formItems = array_map('trim', $formItems);
            $formDisplay = array_map(function ($v) {
                return number_format((float) $v, 2, '.', ''); }, $formItems);

            $stmt = $pdo->query("SELECT amount FROM costs");
            $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dbDisplay = array_map(function ($v) {
                return number_format((float) $v, 2, '.', ''); }, $dbFloats);

            $itemsToAdd = array_diff($formDisplay, $dbDisplay);
            $itemsToDelete = array_diff($dbDisplay, $formDisplay);

            $addedCount = 0;
            $deletedCount = 0;

            if (!empty($itemsToAdd)) {
                $sql = "INSERT IGNORE INTO costs (amount) VALUES (?)";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToAdd as $item) {
                    $stmt->execute([floatval($item)]);
                    $addedCount++;
                }
            }
            if (!empty($itemsToDelete)) {
                $sql = "DELETE FROM costs WHERE amount = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToDelete as $item) {
                    $stmt->execute([floatval($item)]);
                    $deletedCount++;
                }
            }

            if ($addedCount > 0 || $deletedCount > 0) {
                $messages[] = "Costs: added {$addedCount}, removed {$deletedCount}";
            }
        }

        // Durations
        if (isset($_POST['durations'])) {
            $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['durations'])));
            $formItems = array_map('trim', $formItems);
            $formDisplay = array_map(function ($v) {
                return number_format((float) $v, 1, '.', ''); }, $formItems);

            $stmt = $pdo->query("SELECT hours FROM durations");
            $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dbDisplay = array_map(function ($v) {
                return number_format((float) $v, 1, '.', ''); }, $dbFloats);

            $itemsToAdd = array_diff($formDisplay, $dbDisplay);
            $itemsToDelete = array_diff($dbDisplay, $formDisplay);

            $addedCount = 0;
            $deletedCount = 0;

            if (!empty($itemsToAdd)) {
                $sql = "INSERT IGNORE INTO durations (hours) VALUES (?)";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToAdd as $item) {
                    $stmt->execute([floatval($item)]);
                    $addedCount++;
                }
            }
            if (!empty($itemsToDelete)) {
                $sql = "DELETE FROM durations WHERE hours = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToDelete as $item) {
                    $stmt->execute([floatval($item)]);
                    $deletedCount++;
                }
            }

            if ($addedCount > 0 || $deletedCount > 0) {
                $messages[] = "Durations: added {$addedCount}, removed {$deletedCount}";
            }
        }

        // Uber Cost Reasons
        if (isset($_POST['uber_cost_reasons'])) {
            $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['uber_cost_reasons'])));
            $formItems = array_map('trim', $formItems);

            $stmt = $pdo->query("SELECT reason FROM uber_cost_reasons");
            $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $itemsToAdd = array_diff($formItems, $dbItems);
            $itemsToDelete = array_diff($dbItems, $formItems);

            $addedCount = 0;
            $deletedCount = 0;

            if (!empty($itemsToAdd)) {
                $sql = "INSERT IGNORE INTO uber_cost_reasons (reason) VALUES (?)";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToAdd as $item) {
                    $stmt->execute([$item]);
                    $addedCount++;
                }
            }
            if (!empty($itemsToDelete)) {
                $sql = "DELETE FROM uber_cost_reasons WHERE reason = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($itemsToDelete as $item) {
                    $stmt->execute([$item]);
                    $deletedCount++;
                }
            }

            if ($addedCount > 0 || $deletedCount > 0) {
                $messages[] = "Uber Cost Reasons: added {$addedCount}, removed {$deletedCount}";
            }
        }
        $response['success'] = true;
        $response['message'] = empty($messages)
            ? 'No changes made'
            : '✅ ' . implode('. ', $messages);

        logInfo('MAINTENANCE', 'Dropdown lists updated', [
            'changes' => $messages
        ]);

    } elseif ($action === 'update_variables' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $variables = $_POST['variables'] ?? [];

        if (empty($variables)) {
            throw new Exception('No variables provided');
        }

        $updatedCount = 0;
        foreach ($variables as $name => $value) {
            $stmt = $pdo->prepare("UPDATE system_variables SET value = ? WHERE name = ?");
            $stmt->execute([$value, $name]);
            if ($stmt->rowCount() > 0) {
                $updatedCount++;
            }
        }

        $response['success'] = true;
        $response['message'] = "✅ Updated {$updatedCount} system variable(s)";

        logInfo('MAINTENANCE', 'System variables updated', [
            'count' => $updatedCount,
            'variables' => array_keys($variables)
        ]);

        } elseif ($action === 'add_variable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $name  = trim($_POST['name']  ?? '');
            $label = trim($_POST['label'] ?? '');
            $type  = trim($_POST['type']  ?? 'text');
            $value = trim($_POST['value'] ?? '');
    
            if (empty($name) || empty($label) || empty($value)) {
                throw new Exception('All fields are required.');
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                throw new Exception('Key must contain only letters, numbers, and underscores.');
            }
            if (!in_array($type, ['number', 'text'])) {
                $type = 'text';
            }
    
            // Check for duplicate key
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_variables WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("A variable with key '{$name}' already exists.");
            }
    
            $stmt = $pdo->prepare("INSERT INTO system_variables (name, label, type, value) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $label, $type, $value]);
    
            $response['success'] = true;
            $response['message'] = "✅ Variable '{$label}' added.";
    
            logInfo('MAINTENANCE', 'System variable added', [
                'name'  => $name,
                'label' => $label,
                'type'  => $type
            ]);
    } else {
        $response['message'] = 'Unsupported action or method';
    }

} catch (Exception $e) {
    logError('MAINTENANCE', 'Maintenance operation failed', [
        'error' => $e->getMessage(),
        'action' => $action
    ]);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);