<?php
// includes/gps_import.php
/**
 * Imports GPSLogger (mendhak) CSV files from the gps_inbox/ folder into the gps_log table.
 * The phone uploads the files there by FTP (every 10 minutes while GPSLogger is running).
 *
 * Runs on demand: any GPS page calls gpsImportOnDemand() when it loads. The host has no cron, so this is the
 * only way it can be automatic. gps_import_files remembers the size and modified time of every file already
 * read, so a call with nothing new costs one folder listing and one small query. A file that has grown since
 * the last read is read again in full. Points already in gps_log are left alone (recorded_at is unique).
 *
 * Cleanup, on every run: an imported file that has not changed for "GPS Inbox: Keep Imported Files" days
 * (System Variables, 0 = keep forever) is deleted. Files that could not be used and files that are not CSV
 * are never touched, only listed on the page.
 *
 * Columns are found by name in the first row, so their order does not matter. Used columns:
 * timestamp_ms (or time), lat, lon, elevation, accuracy, bearing, speed (m/s), satellites, provider, battery.
 * A row with fewer fields than the header is skipped: it is the end of a file that was still being uploaded,
 * and its values may be cut short. The complete row is picked up on the next read.
 * Speed is kept only when the phone had a satellite fix. A "gps" point with 0 satellites reports a speed of
 * 0.0 even while driving, so that value means "unknown", not "stopped".
 *
 * Needs migrations 20261121_1800_gps_log.sql, 20261123_1600_gps_log_import.sql and 20261125_2200_gps_import_files.sql.
 * Used by: services/gps_import.php, services/api/gps_import.php
 */

/** The folder the phone uploads into. Its .htaccess keeps the files from being served over the web. */
function gpsImportInboxDir(): string
{
    return ROOT_DIR . '/gps_inbox';
}

/**
 * What is in the inbox: CSV files (name, path, size, modified) and the names of everything else.
 * Hidden files (.htaccess, .gitignore) and folders are ignored.
 *
 * @return array{csv: array<int, array{name: string, path: string, size: int, modified: int}>, other: string[]}
 */
function gpsImportScan(): array
{
    $scan = ['csv' => [], 'other' => []];
    $dir = gpsImportInboxDir();
    if (!is_dir($dir)) {
        return $scan;
    }
    foreach (scandir($dir) ?: [] as $name) {
        $path = $dir . '/' . $name;
        if ($name[0] === '.' || !is_file($path)) {
            continue;
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'csv') {
            $scan['csv'][] = [
                'name'     => $name,
                'path'     => $path,
                'size'     => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        } else {
            $scan['other'][] = $name;
        }
    }
    usort($scan['csv'], fn($a, $b) => strcmp($a['name'], $b['name']));
    sort($scan['other']);
    return $scan;
}

/**
 * What the import remembers about each file it has read, keyed by file name.
 *
 * @return array<string, array{filename: string, file_size: int|string, file_mtime: int|string, row_count: int|string, new_points: int|string, last_error: ?string, imported_at: int|string}>
 */
function gpsImportState(PDO $pdo): array
{
    $state = [];
    foreach ($pdo->query("SELECT filename, file_size, file_mtime, row_count, new_points, last_error, imported_at FROM gps_import_files") as $row) {
        $state[$row['filename']] = $row;
    }
    return $state;
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
 * Reads one CSV file and inserts its points. 'error' is set (and nothing inserted) when the file is not a
 * GPSLogger CSV or cannot be opened. A PDOException is thrown to the caller.
 *
 * @return array{rows: int, new: int, existing: int, skipped: int, error: ?string}
 */
function gpsImportReadFile(PDOStatement $insert, string $path, int $now): array
{
    $result = ['rows' => 0, 'new' => 0, 'existing' => 0, 'skipped' => 0, 'error' => null];

    $handle = fopen($path, 'r');
    if ($handle === false) {
        $result['error'] = 'could not be opened';
        return $result;
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');
    $names = is_array($header)
        ? array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header)
        : [];
    if (!in_array('lat', $names, true) || !in_array('lon', $names, true)
        || (!in_array('timestamp_ms', $names, true) && !in_array('time', $names, true))) {
        fclose($handle);
        $result['error'] = 'not a GPSLogger CSV (needs lat, lon and time columns)';
        return $result;
    }
    $width = count($names);

    while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($line === [null]) {
            continue; // blank line
        }
        $result['rows']++;
        if (count($line) < $width) {
            $result['skipped']++; // cut short, still being uploaded
            continue;
        }
        $point = gpsImportPoint(array_combine($names, array_slice($line, 0, $width)), $now);
        if ($point === null) {
            $result['skipped']++;
            continue;
        }
        $insert->execute([
            $point['recorded_at'], $now, $point['latitude'], $point['longitude'], $point['speed_kmh'],
            $point['heading'], $point['altitude_m'], $point['accuracy_m'], $point['battery_pct'],
            $point['provider'], $point['satellites'],
        ]);
        if ($insert->rowCount() > 0) {
            $result['new']++;
        } else {
            $result['existing']++;
        }
    }
    fclose($handle);
    return $result;
}

/**
 * Reads the CSV files that are new or have changed since they were last read (all of them when $force is
 * true), then does the cleanup. Each file goes in as one transaction together with its remembered state.
 * A PDOException (for example a missing table because a migration has not run) is thrown to the caller.
 *
 * @return array{files: int, unchanged: int, rows: int, new: int, existing: int, skipped: int, deleted: int, errors: string[]}
 */
function gpsImportRun(PDO $pdo, bool $force = false): array
{
    $summary = ['files' => 0, 'unchanged' => 0, 'rows' => 0, 'new' => 0, 'existing' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => []];
    $now = time();
    $scan = gpsImportScan();
    $state = gpsImportState($pdo);

    $insert = $pdo->prepare("
        INSERT IGNORE INTO gps_log
            (recorded_at, received_at, latitude, longitude, speed_kmh, heading, altitude_m, accuracy_m, battery_pct, provider, satellites)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $remember = $pdo->prepare("
        REPLACE INTO gps_import_files (filename, file_size, file_mtime, row_count, new_points, last_error, imported_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($scan['csv'] as $file) {
        $known = $state[$file['name']] ?? null;
        if (!$force && $known !== null && (int) $known['file_size'] === $file['size'] && (int) $known['file_mtime'] === $file['modified']) {
            $summary['unchanged']++;
            continue;
        }

        // The size and time seen before reading are what is remembered, so a file that grows while it is
        // being read is read again next time.
        $pdo->beginTransaction();
        try {
            $read = gpsImportReadFile($insert, $file['path'], $now);
            $remember->execute([$file['name'], $file['size'], $file['modified'], $read['rows'], $read['new'], $read['error'], $now]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        $state[$file['name']] = [
            'filename' => $file['name'], 'file_size' => $file['size'], 'file_mtime' => $file['modified'],
            'row_count' => $read['rows'], 'new_points' => $read['new'], 'last_error' => $read['error'], 'imported_at' => $now,
        ];
        $summary['files']++;
        $summary['rows'] += $read['rows'];
        $summary['new'] += $read['new'];
        $summary['existing'] += $read['existing'];
        $summary['skipped'] += $read['skipped'];
        if ($read['error'] !== null) {
            $summary['errors'][] = $file['name'] . ': ' . $read['error'];
        }
    }

    $summary['deleted'] = gpsImportCleanup($pdo, $scan['csv'], $state, $now);
    return $summary;
}

/**
 * Deletes imported files that have not changed for the number of days in System Variables
 * ("GPS Inbox: Keep Imported Files", 0 = keep forever), and forgets the remembered state of files that are
 * gone from the folder. A file is only deleted when it is exactly as it was when it was read, and was read
 * without a problem. Returns how many files were deleted.
 *
 * @param array<int, array{name: string, path: string, size: int, modified: int}> $csvFiles
 * @param array<string, array<string, mixed>> $state
 */
function gpsImportCleanup(PDO $pdo, array $csvFiles, array $state, int $now): int
{
    $keepDays = max(0, (int) getSystemVariable($pdo, 'gps_inbox_keep_days'));
    $deleted = 0;
    $present = [];

    foreach ($csvFiles as $file) {
        $known = $state[$file['name']] ?? null;
        $isOld = $keepDays > 0 && ($now - $file['modified']) > $keepDays * 86400;
        $unchanged = $known !== null
            && (int) $known['file_size'] === $file['size']
            && (int) $known['file_mtime'] === $file['modified'];

        if ($isOld && $unchanged && $known['last_error'] === null && @unlink($file['path'])) {
            $deleted++;
            continue; // gone, its state is removed below
        }
        $present[$file['name']] = true;
    }

    $forget = $pdo->prepare("DELETE FROM gps_import_files WHERE filename = ?");
    foreach (array_keys($state) as $name) {
        if (!isset($present[$name])) {
            $forget->execute([$name]);
        }
    }
    return $deleted;
}

/**
 * The automatic import for pages: reads what is new, cleans up, and never breaks the page. Returns the
 * summary, or null when the import could not run (the error is in the system logs).
 *
 * @return array{files: int, unchanged: int, rows: int, new: int, existing: int, skipped: int, deleted: int, errors: string[]}|null
 */
function gpsImportOnDemand(PDO $pdo): ?array
{
    try {
        return gpsImportRun($pdo, false);
    } catch (PDOException $e) {
        logError('GPS', 'Automatic GPS import failed. If a table or column is missing, run Services → Database Updates.', [
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
