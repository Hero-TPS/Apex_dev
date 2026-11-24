<?php
// includes/gps_import.php
/**
 * Imports GPSLogger (mendhak) CSV files from the gps_inbox/ folder into the gps_log table.
 * The phone uploads the files there by FTP. Every run reads every CSV in the folder, and points already
 * in gps_log are left alone (recorded_at is unique), so running it again, or on a file that has grown
 * since the last run, is harmless.
 *
 * Columns are found by name in the first row, so their order does not matter. Used columns:
 * timestamp_ms (or time), lat, lon, elevation, accuracy, bearing, speed (m/s), satellites, provider, battery.
 * Speed is kept only when the phone had a satellite fix. A "gps" point with 0 satellites reports a speed of
 * 0.0 even while driving, so that value means "unknown", not "stopped".
 *
 * Needs migrations 20261121_1800_gps_log.sql and 20261123_1600_gps_log_import.sql.
 * Used by: services/gps_import.php, services/api/gps_import.php
 */

/** The folder the phone uploads into. Its .htaccess keeps the files from being served over the web. */
function gpsImportInboxDir(): string
{
    return ROOT_DIR . '/gps_inbox';
}

/**
 * CSV files waiting in the inbox, oldest name first.
 *
 * @return array<int, array{name: string, path: string, size: int, modified: int}>
 */
function gpsImportListFiles(): array
{
    $dir = gpsImportInboxDir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (scandir($dir) ?: [] as $name) {
        $path = $dir . '/' . $name;
        if (is_file($path) && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'csv') {
            $files[] = [
                'name'     => $name,
                'path'     => $path,
                'size'     => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        }
    }
    usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $files;
}

/** A finite number from a CSV cell, or null when it is empty or not numeric. */
function gpsImportNumber($value): ?float
{
    $value = trim((string) $value);
    return (is_numeric($value) && is_finite((float) $value)) ? (float) $value : null;
}

/**
 * One CSV row (column name => value) as the values to store, or null when the row is unusable
 * (no valid coordinates, or a time before 2000 or more than a day ahead).
 *
 * @return array{recorded_at: int, latitude: float, longitude: float, speed_kmh: ?float, heading: ?int,
 *               altitude_m: ?float, accuracy_m: ?float, battery_pct: ?int, provider: ?string, satellites: ?int}|null
 */
function gpsImportPoint(array $row, int $now): ?array
{
    $lat = gpsImportNumber($row['lat'] ?? '');
    $lon = gpsImportNumber($row['lon'] ?? '');
    if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180 || ($lat == 0 && $lon == 0)) {
        return null;
    }

    $ms = gpsImportNumber($row['timestamp_ms'] ?? '');
    if ($ms !== null) {
        $recordedAt = (int) floor($ms / 1000);
    } else {
        $parsed = strtotime((string) ($row['time'] ?? ''));
        $recordedAt = $parsed === false ? 0 : $parsed;
    }
    if ($recordedAt < 946684800 || $recordedAt > $now + 86400) {
        return null;
    }

    $satellites = gpsImportNumber($row['satellites'] ?? '');
    $satellites = ($satellites !== null && $satellites >= 0 && $satellites <= 255) ? (int) $satellites : null;

    $speed = gpsImportNumber($row['speed'] ?? '');
    $speedKmh = ($speed !== null && $speed >= 0 && ($satellites === null || $satellites > 0) && $speed * 3.6 <= 9999)
        ? round($speed * 3.6, 2)
        : null;

    $bearing = gpsImportNumber($row['bearing'] ?? '');
    $heading = ($bearing !== null && $bearing >= 0) ? ((int) round($bearing)) % 360 : null;

    $altitude = gpsImportNumber($row['elevation'] ?? '');
    $altitude = ($altitude !== null && abs($altitude) < 99999) ? round($altitude, 1) : null;

    $accuracy = gpsImportNumber($row['accuracy'] ?? '');
    $accuracy = ($accuracy !== null && $accuracy >= 0 && $accuracy < 99999) ? round($accuracy, 1) : null;

    $battery = gpsImportNumber($row['battery'] ?? '');
    $battery = ($battery !== null && $battery >= 0) ? min(100, (int) round($battery)) : null;

    $provider = substr(preg_replace('/[^a-z]/', '', strtolower((string) ($row['provider'] ?? ''))), 0, 16);

    return [
        'recorded_at' => $recordedAt,
        'latitude'    => round($lat, 6),
        'longitude'   => round($lon, 6),
        'speed_kmh'   => $speedKmh,
        'heading'     => $heading,
        'altitude_m'  => $altitude,
        'accuracy_m'  => $accuracy,
        'battery_pct' => $battery,
        'provider'    => $provider === '' ? null : $provider,
        'satellites'  => $satellites,
    ];
}

/**
 * Imports every CSV in the inbox. Each file goes in as one transaction. A PDOException (for example a
 * missing column because the migration has not run) is thrown to the caller.
 *
 * @return array{files: int, rows: int, new: int, existing: int, skipped: int, errors: string[]}
 */
function gpsImportRun(PDO $pdo): array
{
    $summary = ['files' => 0, 'rows' => 0, 'new' => 0, 'existing' => 0, 'skipped' => 0, 'errors' => []];
    $now = time();

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO gps_log
            (recorded_at, received_at, latitude, longitude, speed_kmh, heading, altitude_m, accuracy_m, battery_pct, provider, satellites)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach (gpsImportListFiles() as $file) {
        $handle = fopen($file['path'], 'r');
        if ($handle === false) {
            $summary['errors'][] = $file['name'] . ': could not be opened';
            continue;
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        $names = is_array($header)
            ? array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header)
            : [];
        if (!in_array('lat', $names, true) || !in_array('lon', $names, true)
            || (!in_array('timestamp_ms', $names, true) && !in_array('time', $names, true))) {
            $summary['errors'][] = $file['name'] . ': not a GPSLogger CSV (needs lat, lon and time columns)';
            fclose($handle);
            continue;
        }

        $summary['files']++;
        $pdo->beginTransaction();
        try {
            while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($line === [null]) {
                    continue; // blank line
                }
                $row = array_combine($names, array_slice(array_pad($line, count($names), ''), 0, count($names)));
                $summary['rows']++;

                $point = gpsImportPoint($row, $now);
                if ($point === null) {
                    $summary['skipped']++;
                    continue;
                }
                $stmt->execute([
                    $point['recorded_at'], $now, $point['latitude'], $point['longitude'], $point['speed_kmh'],
                    $point['heading'], $point['altitude_m'], $point['accuracy_m'], $point['battery_pct'],
                    $point['provider'], $point['satellites'],
                ]);
                if ($stmt->rowCount() > 0) {
                    $summary['new']++;
                } else {
                    $summary['existing']++;
                }
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            fclose($handle);
            throw $e;
        }
        fclose($handle);
    }

    return $summary;
}
