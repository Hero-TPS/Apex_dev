<?php
// services/api/gps_log.php
/**
 * Receives location points from the Traccar Client app on the phone and stores them in gps_log.
 * Public on purpose (the app has no login): it is protected by the token in System Variables
 * ("GPS Log Token"), which the app sends as its Device identifier, and it only saves points while
 * "GPS Log" is switched On.
 *
 * Server URL to enter in Traccar Client: <BASE_URL>/services/api/gps_log.php (use https).
 *
 * Accepts both formats Traccar Client uses:
 *  - the classic one, GET or POST with query/form fields: id, timestamp (unix seconds or ISO),
 *    lat, lon, speed (knots), bearing, altitude, accuracy, batt (percent)
 *  - the newer one, a JSON body: { device_id, location: { timestamp, is_moving, coords: { latitude,
 *    longitude, speed (m/s), heading, altitude, accuracy }, battery: { level 0-1 } } }
 *
 * Needs migration 20261121_1800_gps_log.sql (the gps_log table and the token).
 * Used by: nothing in the intranet yet (the phone calls it directly).
 */

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';

/** A finite number from a request value, or null when it is missing or not numeric. */
function gpsLogNumber($value): ?float
{
    return (is_numeric($value) && is_finite((float) $value)) ? (float) $value : null;
}

/** Unix timestamp from a number (seconds or milliseconds) or an ISO 8601 string, or null. */
function gpsLogTimestamp($value): ?int
{
    if (is_numeric($value)) {
        $seconds = (float) $value;
        if ($seconds > 100000000000) {
            $seconds /= 1000; // milliseconds
        }
        return (int) round($seconds);
    }
    if (is_string($value) && trim($value) !== '') {
        $parsed = strtotime($value);
        return $parsed === false ? null : $parsed;
    }
    return null;
}

/**
 * Reads the request in either Traccar Client format and returns one flat array:
 * id, lat, lon, time, speed_kmh, heading, altitude, accuracy, battery, moving (all null when not sent).
 * The second value is the list of field names that arrived, for the log when a point is refused.
 *
 * @return array{0: array<string, mixed>, 1: array<int, string>}
 */
function gpsLogReadRequest(): array
{
    $fields = array_merge($_GET, $_POST);
    $fieldNames = array_keys($fields);

    $body = json_decode((string) file_get_contents('php://input'), true);
    $coords = [];
    $location = [];
    $battery = [];
    if (is_array($body)) {
        $fieldNames = array_merge($fieldNames, array_keys($body));
        $location = is_array($body['location'] ?? null) ? $body['location'] : [];
        $coords = is_array($location['coords'] ?? null) ? $location['coords'] : [];
        $battery = is_array($location['battery'] ?? null) ? $location['battery'] : [];
    }

    // Query format: speed is in knots. JSON format: speed is in m/s. A negative speed means unknown.
    $speedKmh = null;
    if (isset($coords['speed'])) {
        $speed = gpsLogNumber($coords['speed']);
        $speedKmh = ($speed !== null && $speed >= 0) ? $speed * 3.6 : null;
    } elseif (isset($fields['speed'])) {
        $speed = gpsLogNumber($fields['speed']);
        $speedKmh = ($speed !== null && $speed >= 0) ? $speed * 1.852 : null;
    }

    $batteryPct = null;
    if (isset($battery['level'])) {
        $level = gpsLogNumber($battery['level']);
        $batteryPct = ($level !== null && $level >= 0) ? $level * 100 : null;
    } elseif (isset($fields['batt'])) {
        $batteryPct = gpsLogNumber($fields['batt']);
    }

    $moving = null;
    if (isset($location['is_moving'])) {
        $moving = filter_var($location['is_moving'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    $data = [
        'id'        => (string) ($body['device_id'] ?? $fields['id'] ?? $fields['device_id'] ?? $fields['token'] ?? ''),
        'lat'       => gpsLogNumber($coords['latitude'] ?? $fields['lat'] ?? null),
        'lon'       => gpsLogNumber($coords['longitude'] ?? $fields['lon'] ?? null),
        'time'      => gpsLogTimestamp($location['timestamp'] ?? $fields['timestamp'] ?? null),
        'speed_kmh' => $speedKmh,
        'heading'   => gpsLogNumber($coords['heading'] ?? $fields['bearing'] ?? $fields['heading'] ?? null),
        'altitude'  => gpsLogNumber($coords['altitude'] ?? $fields['altitude'] ?? null),
        'accuracy'  => gpsLogNumber($coords['accuracy'] ?? $fields['accuracy'] ?? null),
        'battery'   => $batteryPct,
        'moving'    => $moving,
    ];
    return [$data, array_values(array_unique(array_map('strval', $fieldNames)))];
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    jsonResponse(['success' => false, 'message' => 'GET or POST required'], 405);
}

[$data, $fieldNames] = gpsLogReadRequest();

try {
    // 1. Token: refuse anything without the right Device identifier (checked before anything else is revealed)
    $token = (string) getSystemVariable($pdo, 'gps_log_token');
    if ($token === '' || $data['id'] === '' || !hash_equals($token, $data['id'])) {
        logWarning('GPS', 'Point refused: wrong or missing device identifier');
        jsonResponse(['success' => false, 'message' => 'Not allowed'], 403);
    }

    // 2. Switch: the whole feature is off unless GPS Log is On
    if ((string) getSystemVariable($pdo, 'gps_log_enabled') !== '1') {
        jsonResponse(['success' => false, 'message' => 'GPS logging is switched off'], 503);
    }

    // 3. Coordinates
    $lat = $data['lat'];
    $lon = $data['lon'];
    if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180 || ($lat == 0 && $lon == 0)) {
        logWarning('GPS', 'Point refused: no valid coordinates', ['fields' => $fieldNames]);
        jsonResponse(['success' => false, 'message' => 'No valid coordinates'], 400);
    }

    // 4. Time the phone took the point. A missing or impossible time (before 2000, or more than a day
    //    ahead of the server) is replaced with the time received.
    $now = time();
    $recordedAt = $data['time'];
    if ($recordedAt === null || $recordedAt < 946684800 || $recordedAt > $now + 86400) {
        $recordedAt = $now;
    }

    // Values the columns cannot hold, or that mean "unknown", are stored as NULL
    $speed = ($data['speed_kmh'] !== null && $data['speed_kmh'] <= 9999) ? round($data['speed_kmh'], 2) : null;
    $heading = ($data['heading'] !== null && $data['heading'] >= 0) ? ((int) round($data['heading'])) % 360 : null;
    $altitude = ($data['altitude'] !== null && abs($data['altitude']) < 99999) ? round($data['altitude'], 1) : null;
    $accuracy = ($data['accuracy'] !== null && $data['accuracy'] >= 0 && $data['accuracy'] < 99999) ? round($data['accuracy'], 1) : null;
    $battery = ($data['battery'] !== null && $data['battery'] >= 0) ? min(100, (int) round($data['battery'])) : null;
    $moving = $data['moving'] === null ? null : ($data['moving'] ? 1 : 0);

    // INSERT IGNORE: a point sent twice (recorded_at is unique) is stored once
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO gps_log
            (recorded_at, received_at, latitude, longitude, speed_kmh, heading, altitude_m, accuracy_m, battery_pct, is_moving)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$recordedAt, $now, round($lat, 6), round($lon, 6), $speed, $heading, $altitude, $accuracy, $battery, $moving]);

    jsonResponse(['success' => true, 'message' => $stmt->rowCount() > 0 ? 'Saved' : 'Already saved']);

} catch (PDOException $e) {
    logError('GPS', 'Could not save point. If the gps_log table is missing, run Services → Database Updates.', [
        'error' => $e->getMessage(),
    ]);
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
