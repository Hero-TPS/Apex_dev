<?php
// services/api/index.php
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
        $historyLoggedFor = [];
        $today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

        foreach ($variables as $name => $value) {
            if (!array_key_exists($name, SYSTEM_VARIABLES))
                continue;

            $stmt = $pdo->prepare("
                INSERT INTO system_variables (name, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$name, $value]);
            $updatedCount++;

            // rowCount() is 1 for a genuine insert (variable didn't exist
            // before), 2 for an update where the value actually changed,
            // and 0 for a resave with no real change. Only the first two
            // represent an actual new value taking effect, so only those
            // get a history row — this is the single mechanism that both
            // seeds history for a brand-new variable and logs every future
            // rate change, for any variable.
            if ($stmt->rowCount() > 0) {
                $histStmt = $pdo->prepare("
                    INSERT INTO system_variable_history (variable_name, value, effective_from)
                    VALUES (?, ?, ?)
                ");
                $histStmt->execute([$name, $value, $today]);
                $historyLoggedFor[] = $name;
            }
        }

        $response['success'] = true;
        $response['message'] = "✅ Updated {$updatedCount} variable(s)";

        logInfo('MAINTENANCE', 'System variables updated', [
            'variables' => array_keys($variables),
            'history_logged_for' => $historyLoggedFor
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

        // Check existence with a SELECT rather than rowCount() on the UPDATE:
        // MySQL reports 0 affected rows when the values are unchanged, which
        // would wrongly look like a missing driver.
        $existsStmt = $pdo->prepare("SELECT 1 FROM drivers WHERE id = ?");
        $existsStmt->execute([$id]);
        if (!$existsStmt->fetchColumn()) {
            throw new Exception('Driver not found.');
        }

        $stmt = $pdo->prepare("UPDATE drivers SET name = ?, phone = ?, active = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $active, $id]);

        $response['success'] = true;
        $response['message'] = "✅ Driver updated.";
        logInfo('MAINTENANCE', 'Driver updated', ['id' => $id, 'name' => $name]);

    } elseif ($action === 'check_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Address Check page: ask Google about one saved destination or one client address. Nothing is saved here.
        $type = $_POST['type'] ?? '';
        $id   = intval($_POST['id'] ?? 0);
        if ($type === 'client') {
            $stmt = $pdo->prepare("SELECT address FROM contacts WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($type === 'destination') {
            $stmt = $pdo->prepare("SELECT name FROM destinations WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            throw new Exception('Unknown item type.');
        }
        $text = $stmt->fetchColumn();
        if ($text === false || trim((string) $text) === '') {
            throw new Exception('That item has no address to check.');
        }
        $response = checkAddressWithGoogle($pdo, (string) $text);

    } elseif ($action === 'mark_checked' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Address Check page: record that these items were found by Google
        $type = $_POST['type'] ?? '';
        $ids  = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v) => $v > 0));
        if (empty($ids) || count($ids) > 500) {
            throw new Exception('Nothing to save.');
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        if ($type === 'client') {
            $stmt = $pdo->prepare("UPDATE contacts SET address_verified_at = NOW() WHERE id IN ($marks)");
        } elseif ($type === 'destination') {
            $stmt = $pdo->prepare("UPDATE destinations SET verified_at = NOW() WHERE id IN ($marks)");
        } else {
            throw new Exception('Unknown item type.');
        }
        $stmt->execute($ids);
        $response['success'] = true;
        $response['message'] = '✅ Saved ' . $stmt->rowCount() . ' as checked.';
        logInfo('MAINTENANCE', 'Addresses marked as checked', ['type' => $type, 'count' => $stmt->rowCount()]);

    } elseif ($action === 'merge_destinations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Address Check page: keep one saved destination and fold the duplicates of it into it
        $keep   = trim($_POST['keep'] ?? '');
        $remove = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['remove'] ?? [])), fn($v) => $v !== '' && $v !== trim($_POST['keep'] ?? ''))));
        if ($keep === '' || empty($remove)) {
            throw new Exception('Choose the destination to keep and at least one duplicate to remove.');
        }

        $stmt = $pdo->prepare("SELECT * FROM destinations WHERE name = ?");
        $stmt->execute([$keep]);
        $keepRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$keepRow) {
            throw new Exception('The destination to keep was not found.');
        }
        foreach ($remove as $name) {
            if (normalizeDestinationName($name) !== normalizeDestinationName($keep)) {
                throw new Exception('"' . $name . '" is not a duplicate of "' . $keep . '".');
            }
        }

        $pdo->beginTransaction();
        $changed = 0;
        foreach ($remove as $name) {
            // Anything not yet done uses the kept name; bookings from past days are left as they were
            $refs = [
                "UPDATE bookings SET original_pickup = ? WHERE original_pickup = ? AND trip_date >= CURDATE()",
                "UPDATE bookings SET original_destination = ? WHERE original_destination = ? AND trip_date >= CURDATE()",
                "UPDATE prebookings SET original_pickup = ? WHERE original_pickup = ?",
                "UPDATE prebookings SET original_destination = ? WHERE original_destination = ?",
                "UPDATE itineraries SET pickup = ? WHERE pickup = ?",
                "UPDATE itineraries SET destination = ? WHERE destination = ?",
                "UPDATE itinerary_stops SET location = ? WHERE location = ?",
            ];
            foreach ($refs as $sql) {
                try {
                    $u = $pdo->prepare($sql);
                    $u->execute([$keep, $name]);
                    $changed += $u->rowCount();
                } catch (PDOException $e) {
                    // The itinerary tables may not exist on this database yet: nothing to update there
                    if (strpos($sql, 'itinerar') === false) { throw $e; }
                }
            }

            // A checked duplicate passes its checked location on to the kept one if that is not checked yet
            $stmt = $pdo->prepare("SELECT * FROM destinations WHERE name = ?");
            $stmt->execute([$name]);
            $dup = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dup && empty($keepRow['verified_at']) && !empty($dup['verified_at'])) {
                $pdo->prepare("UPDATE destinations SET place_id = ?, latitude = ?, longitude = ?, verified_at = ? WHERE name = ?")
                    ->execute([$dup['place_id'], $dup['latitude'], $dup['longitude'], $dup['verified_at'], $keep]);
                $keepRow['verified_at'] = $dup['verified_at'];
            }
            $pdo->prepare("DELETE FROM destinations WHERE name = ?")->execute([$name]);
        }
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = '✅ Merged into "' . $keep . '". Removed ' . count($remove) . ' duplicate(s), updated ' . $changed . ' booking/itinerary reference(s).';
        logInfo('MAINTENANCE', 'Destinations merged', ['keep' => $keep, 'removed' => $remove, 'references' => $changed]);

    } elseif ($action === 'check_destination' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Ask Google whether it can find this text. Nothing is saved here.
        $response = checkAddressWithGoogle($pdo, trim($_POST['text'] ?? ''));

    } elseif ($action === 'save_destination_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Mark a preset destination as checked. With a Google place (place_id, lat, lng) it also stores the exact point.
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            throw new Exception('No destination given.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM destinations WHERE name = ?");
        $stmt->execute([$name]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new Exception('That destination is not in the list. Save the list first.');
        }

        $placeId = cleanGooglePlaceId($_POST['place_id'] ?? '');
        $lat = (isset($_POST['lat']) && is_numeric($_POST['lat'])) ? (float) $_POST['lat'] : null;
        $lng = (isset($_POST['lng']) && is_numeric($_POST['lng'])) ? (float) $_POST['lng'] : null;
        if ($lat !== null && ($lat < -90 || $lat > 90)) { $lat = null; }
        if ($lng !== null && ($lng < -180 || $lng > 180)) { $lng = null; }

        if ($placeId !== null) {
            $pdo->prepare("UPDATE destinations SET place_id = ?, latitude = ?, longitude = ?, verified_at = NOW() WHERE name = ?")
                ->execute([$placeId, $lat, $lng, $name]);
        } else {
            $pdo->prepare("UPDATE destinations SET verified_at = NOW() WHERE name = ?")->execute([$name]);
        }

        $response['success'] = true;
        $response['message'] = '✅ Saved.';
        logInfo('MAINTENANCE', 'Destination location checked', ['name' => $name, 'place_id' => $placeId]);

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
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('MAINTENANCE', 'Maintenance operation failed', [
        'error'  => $e->getMessage(),
        'action' => $action
    ]);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
