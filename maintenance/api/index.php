<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$response = ['success' => false, 'message' => 'Invalid action.'];

$action = $_GET['action'] ?? '';

if ($action === 'update_lists' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Destinations
    if (isset($_POST['destinations'])) {
        $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['destinations'])));
        $stmt = $pdo->query("SELECT name FROM destinations");
        $dbItems = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $itemsToAdd = array_diff($formItems, $dbItems);
        $itemsToDelete = array_diff($dbItems, $formItems);

        if (!empty($itemsToAdd)) {
            $sql = "INSERT IGNORE INTO destinations (name) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToAdd as $item) {
                $stmt->execute([trim($item)]);
            }
        }
        if (!empty($itemsToDelete)) {
            $sql = "DELETE FROM destinations WHERE name = ?";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToDelete as $item) {
                $stmt->execute([$item]);
            }
        }
    }

    // Costs
    if (isset($_POST['costs'])) {
        $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['costs'])));
        $formItems = array_map('trim', $formItems);
        $formDisplay = array_map(function($v) { return number_format((float)$v, 2, '.', ''); }, $formItems);

        $stmt = $pdo->query("SELECT amount FROM costs");
        $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $dbDisplay = array_map(function($v) { return number_format((float)$v, 2, '.', ''); }, $dbFloats);

        $itemsToAdd = array_diff($formDisplay, $dbDisplay);
        $itemsToDelete = array_diff($dbDisplay, $formDisplay);

        if (!empty($itemsToAdd)) {
            $sql = "INSERT IGNORE INTO costs (amount) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToAdd as $item) {
                $stmt->execute([floatval($item)]);
            }
        }
        if (!empty($itemsToDelete)) {
            $sql = "DELETE FROM costs WHERE amount = ?";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToDelete as $item) {
                $stmt->execute([floatval($item)]);
            }
        }
    }

    // Durations
    if (isset($_POST['durations'])) {
        $formItems = array_unique(array_filter(preg_split('/\r\n|\r|\n/', $_POST['durations'])));
        $formItems = array_map('trim', $formItems);
        $formDisplay = array_map(function($v) { return number_format((float)$v, 1, '.', ''); }, $formItems);

        $stmt = $pdo->query("SELECT hours FROM durations");
        $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $dbDisplay = array_map(function($v) { return number_format((float)$v, 1, '.', ''); }, $dbFloats);

        $itemsToAdd = array_diff($formDisplay, $dbDisplay);
        $itemsToDelete = array_diff($dbDisplay, $formDisplay);

        if (!empty($itemsToAdd)) {
            $sql = "INSERT IGNORE INTO durations (hours) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToAdd as $item) {
                $stmt->execute([floatval($item)]);
            }
        }
        if (!empty($itemsToDelete)) {
            $sql = "DELETE FROM durations WHERE hours = ?";
            $stmt = $pdo->prepare($sql);
            foreach ($itemsToDelete as $item) {
                $stmt->execute([floatval($item)]);
            }
        }
    }

    $response = ['success' => true];

} elseif ($action === 'update_variables' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $variables = $_POST['variables'] ?? [];
    foreach ($variables as $name => $value) {
        $stmt = $pdo->prepare("SELECT type FROM system_variables WHERE name = ?");
        $stmt->execute([$name]);
        $type = $stmt->fetchColumn();
        
        if (!$type) continue;
        if ($type === 'number' && !is_numeric($value)) {
            $response = ['success' => false, 'message' => "Invalid value for {$name}"];
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE system_variables SET value = ? WHERE name = ?");
        $stmt->execute([$value, $name]);
    }

    // Handle new variables
    $newVars = $_POST['new_variables'] ?? [];
    foreach ($newVars as $var) {
        if (empty($var['name']) || empty($var['label']) || empty($var['value'])) continue;
        if (!preg_match('/^[a-z0-9_]+$/', $var['name'])) continue;
        $type = in_array($var['type'], ['text', 'number']) ? $var['type'] : 'text';
        $stmt = $pdo->prepare("
            INSERT INTO system_variables (name, label, value, type) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                label = VALUES(label), 
                value = VALUES(value),
                type = VALUES(type)
        ");
        $stmt->execute([$var['name'], $var['label'], $var['value'], $type]);
    }

    $response = ['success' => true];

} elseif ($action === 'delete_variable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    
    if (empty($name)) {
        $response = ['success' => false, 'message' => 'No variable name provided.'];
    } else {
        $protected = ['car_rental_price'];
        if (in_array($name, $protected)) {
            $response = ['success' => false, 'message' => 'This variable cannot be deleted.'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM system_variables WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->rowCount() > 0) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'Variable not found.'];
            }
        }
    }

} else {
    $response['message'] = 'Unsupported action or method.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>