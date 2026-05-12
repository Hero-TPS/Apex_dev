<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth_api.php';
require_once ROOT_DIR . '/includes/helpers.php';

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
                return number_format((float) $v, 2, '.', '');
            }, $formItems);

            $stmt = $pdo->query("SELECT amount FROM costs");
            $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dbDisplay = array_map(function ($v) {
                return number_format((float) $v, 2, '.', '');
            }, $dbFloats);

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
                return number_format((float) $v, 1, '.', '');
            }, $formItems);

            $stmt = $pdo->query("SELECT hours FROM durations");
            $dbFloats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dbDisplay = array_map(function ($v) {
                return number_format((float) $v, 1, '.', '');
            }, $dbFloats);

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
            if (!array_key_exists($name, SYSTEM_VARIABLES))
                continue;

            $stmt = $pdo->prepare("
                INSERT INTO system_variables (name, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$name, $value]);
            $updatedCount++;
        }

        $response['success'] = true;
        $response['message'] = "✅ Updated {$updatedCount} variable(s)";

        logInfo('MAINTENANCE', 'System variables updated', [
            'variables' => array_keys($variables)
        ]);

    } elseif ($action === 'mark_overdue_complete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'completed', updated_at = NOW()
            WHERE trip_date < ? AND status != 'completed'
        ");
        $stmt->execute([$today]);
        $count = $stmt->rowCount();

        $response['success'] = true;
        $response['message'] = $count > 0
            ? "✅ Marked {$count} overdue booking" . ($count !== 1 ? 's' : '') . " as completed."
            : "ℹ️ No overdue bookings found.";

        logInfo('MAINTENANCE', 'Overdue bookings marked as completed', [
            'count' => $count
        ]);

    } elseif ($action === 'add_driver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name  = trim($_POST['name']  ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) {
            throw new Exception('Driver name is required.');
        }

        $stmt = $pdo->prepare("INSERT INTO drivers (name, phone, active) VALUES (?, ?, 1)");
        $stmt->execute([$name, $phone]);

        $response['success'] = true;
        $response['message'] = "✅ Driver '{$name}' added.";
        logInfo('MAINTENANCE', 'Driver added', ['name' => $name]);

    } elseif ($action === 'update_driver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id     = intval($_POST['id']   ?? 0);
        $name   = trim($_POST['name']   ?? '');
        $phone  = trim($_POST['phone']  ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || empty($name)) {
            throw new Exception('Invalid driver data.');
        }

        $stmt = $pdo->prepare("UPDATE drivers SET name = ?, phone = ?, active = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $active, $id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Driver not found.');
        }

        $response['success'] = true;
        $response['message'] = "✅ Driver updated.";
        logInfo('MAINTENANCE', 'Driver updated', ['id' => $id, 'name' => $name]);

    } elseif ($action === 'delete_driver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Invalid driver ID.');
        }

        // Unlink driver from any bookings before deleting to preserve booking records.
        // Clears booking_fee as well since the fee was calculated for this driver.
        $pdo->prepare("UPDATE bookings SET driver_id = NULL, booking_fee = NULL WHERE driver_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = ?");
        $stmt->execute([$id]);

        $response['success'] = true;
        $response['message'] = "✅ Driver deleted.";
        logInfo('MAINTENANCE', 'Driver deleted', ['id' => $id]);

    } else {
        $response['message'] = 'Unsupported action or method';
    }

} catch (Exception $e) {
    logError('MAINTENANCE', 'Maintenance operation failed', [
        'error'  => $e->getMessage(),
        'action' => $action
    ]);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);