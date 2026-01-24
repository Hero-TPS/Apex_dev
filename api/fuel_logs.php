<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

try {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $log_timestamp = intval($_POST['log_timestamp'] ?? 0);
        $meter_type = $_POST['meter_type'] ?? 'trip';
        $km_value = floatval($_POST['km_value'] ?? 0);
        $calculated_trip = floatval($_POST['calculated_trip'] ?? 0);
        $fuel_price = floatval($_POST['fuel_price'] ?? 0);
        $total_cost = floatval($_POST['total_cost'] ?? 0);
        $payment_method = ($_POST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash';

        if ($log_timestamp <= 0 || $km_value <= 0 || $fuel_price <= 0 || $total_cost <= 0) {
            throw new Exception('All values must be greater than zero.');
        }

        // Get last odometer
        $last_odo = 0;
        $stmt = $pdo->query("SELECT odo_km FROM fuel_logs ORDER BY id DESC LIMIT 1");
        if ($row = $stmt->fetch()) {
            $last_odo = $row['odo_km'];
        }

        // Calculate values
        if ($meter_type === 'trip') {
            $trip_km = $km_value;
            $odo_km = $last_odo + $km_value;
        } else {
            $odo_km = $km_value;
            $trip_km = $calculated_trip;
        }

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO fuel_logs (log_timestamp, odo_km, trip_km, fuel_price, total_cost, payment_method) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$log_timestamp, $odo_km, $trip_km, $fuel_price, $total_cost, $payment_method]);

        $response = ['success' => true, 'message' => 'Log saved.'];

    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $log_timestamp = intval($_POST['log_timestamp'] ?? 0);
        $fuel_price = floatval($_POST['fuel_price'] ?? 0);
        $total_cost = floatval($_POST['total_cost'] ?? 0);
        $payment_method = ($_POST['payment_method'] ?? 'cash') === 'eft' ? 'eft' : 'cash';

        if ($id <= 0 || $log_timestamp <= 0 || $fuel_price <= 0 || $total_cost <= 0) {
            throw new Exception('Invalid data.');
        }

        $stmt = $pdo->prepare("
            UPDATE fuel_logs 
            SET log_timestamp = ?, fuel_price = ?, total_cost = ?, payment_method = ? 
            WHERE id = ?
        ");
        $stmt->execute([$log_timestamp, $fuel_price, $total_cost, $payment_method, $id]);

        $response = ['success' => true, 'message' => 'Fuel log updated.'];

    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Invalid ID.');
        $stmt = $pdo->prepare("DELETE FROM fuel_logs WHERE id = ?");
        $stmt->execute([$id]);
        $response = ['success' => true];

    } elseif ($_GET['action'] === 'get_all') {
        $stmt = $pdo->query("
            SELECT 
                id,
                log_timestamp,
                odo_km,
                trip_km,
                fuel_price,
                total_cost,
                payment_method
            FROM fuel_logs 
            ORDER BY log_timestamp DESC
        ");
        $data = [];
        while ($row = $stmt->fetch()) {
            $dt = new DateTime();
            $dt->setTimestamp($row['log_timestamp']);
            $dt->setTimezone(new DateTimeZone(TIME_ZONE));
            $data[] = [
                'id' => $row['id'],
                'log_datetime' => $dt->format('d-m-Y H:i'),
                'odo_km' => $row['odo_km'],
                'trip_km' => $row['trip_km'],
                'fuel_price' => $row['fuel_price'],
                'total_cost' => $row['total_cost'],
                'payment_method' => $row['payment_method']
            ];
        }
        $response = ['success' => true, 'data' => $data];

    } else {
        $response['message'] = 'Unsupported action.';
    }

} catch (Exception $e) {
    error_log('Fuel log error: ' . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>