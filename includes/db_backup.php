<?php
// includes/db_backup.php
//
// Database backup and restore, used by services/api/backup.php (shown on services/updates.php).
// PHP only: the backup is built in memory, zipped in memory and streamed to the browser,
// and a restore reads the uploaded file straight from PHP's upload temp file. Nothing is
// kept on the server.
//
// File format (one .sql file inside a .zip):
//   a header of "-- key: value" lines (role, db_name, created, latest_migration, body_sha256,
//   signature), the line "-- END-HEADER", then the body. The body is a list of statements
//   separated by a line "-- @@". Only SET FOREIGN_KEY_CHECKS, DROP TABLE IF EXISTS,
//   CREATE TABLE and INSERT INTO statements are ever accepted by a restore.
//
// Who may restore what (the "role" of a site is live / dev / itinerary):
//   live      <- live only (same database, signed with this site's BACKUP_SIGNING_KEY)
//   dev       <- dev or live
//   itinerary <- itinerary only (it has its own tables; changes reach it through merges from dev)
//
// The system_logs table is never backed up or restored (large, not needed, and it caused problems).
//
// Expects config.php (APP_ENV, DB_NAME, optional SITE_ROLE and BACKUP_SIGNING_KEY) to be loaded.

const DB_BACKUP_DELIMITER = "\n-- @@\n";
const DB_BACKUP_SKIP_TABLE = 'system_logs'; // never backed up or restored
const DB_BACKUP_MAX_UPLOAD = 25 * 1024 * 1024;
const DB_BACKUP_SNAPSHOT_WINDOW = 900; // a restore needs a backup downloaded in the last 15 minutes

/**
 * live / dev / itinerary. A production site is always "live", whatever SITE_ROLE says.
 */
function dbBackupRole(): string
{
    if (defined('APP_ENV') && APP_ENV === 'production') {
        return 'live';
    }
    $role = defined('SITE_ROLE') ? strtolower((string) SITE_ROLE) : 'dev';
    return $role === 'itinerary' ? 'itinerary' : 'dev';
}

/**
 * Roles whose backups a site of the given role may restore.
 *
 * @return string[]
 */
function dbBackupAcceptedRoles(string $role): array
{
    if ($role === 'live') {
        return ['live'];
    }
    if ($role === 'itinerary') {
        return ['itinerary'];
    }
    return ['dev', 'live'];
}

function dbBackupSigningKey(): string
{
    return defined('BACKUP_SIGNING_KEY') ? (string) BACKUP_SIGNING_KEY : '';
}

function dbBackupSign(string $role, string $dbName, string $created, string $bodySha, string $key): string
{
    return hash_hmac('sha256', implode('|', [$role, $dbName, $created, $bodySha]), $key);
}

function dbBackupQuoteIdent(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

// ========== EXPORT ==========

/**
 * Build a backup of every table (and its data) in this database, except system_logs.
 *
 * @return array{filename: string, data: string, mime: string, tables: int, rows: int}
 * @throws RuntimeException
 */
function createDbBackup(PDO $pdo): array
{
    @set_time_limit(120);

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);

    $parts = ['SET FOREIGN_KEY_CHECKS=0;'];
    $tableCount = 0;
    $rowTotal = 0;

    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new RuntimeException("Table name \"$table\" has characters a backup cannot keep.");
        }
        if ($table === DB_BACKUP_SKIP_TABLE) {
            continue;
        }
        $q = dbBackupQuoteIdent($table);

        $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM);
        if (!$create || empty($create[1])) {
            throw new RuntimeException("Could not read the structure of $table.");
        }

        // Columns to copy: skip generated ones (they cannot be inserted); binary ones are written as hex
        $columns = [];
        $isBinary = [];
        foreach ($pdo->query("SHOW COLUMNS FROM $q")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            if (stripos((string) $col['Extra'], 'GENERATED') !== false) {
                continue;
            }
            $columns[] = dbBackupQuoteIdent($col['Field']);
            $isBinary[] = (bool) preg_match('/blob|binary/i', (string) $col['Type']);
        }

        $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM $q")->fetchColumn();
        $parts[] = "-- table $table rows=$rowCount\nDROP TABLE IF EXISTS $q;";
        $parts[] = rtrim($create[1], "; \n") . ';';

        $columnList = implode(', ', $columns);
        $rows = $pdo->query("SELECT $columnList FROM $q", PDO::FETCH_NUM);
        $batch = [];
        $batchSize = 0;
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $i => $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif ($isBinary[$i] && $value !== '') {
                    $values[] = '0x' . bin2hex($value);
                } else {
                    $values[] = $pdo->quote((string) $value);
                }
            }
            $line = '(' . implode(',', $values) . ')';
            $batch[] = $line;
            $batchSize += strlen($line);
            if (count($batch) >= 200 || $batchSize > 200000) {
                $parts[] = "INSERT INTO $q ($columnList) VALUES " . implode(",\n", $batch) . ';';
                $batch = [];
                $batchSize = 0;
            }
        }
        if ($batch) {
            $parts[] = "INSERT INTO $q ($columnList) VALUES " . implode(",\n", $batch) . ';';
        }

        $tableCount++;
        $rowTotal += $rowCount;
    }
    $parts[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $body = implode(DB_BACKUP_DELIMITER, $parts) . "\n";

    $role = dbBackupRole();
    $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
    $tz = defined('TIME_ZONE') ? new DateTimeZone(TIME_ZONE) : new DateTimeZone('UTC');
    $now = new DateTime('now', $tz);
    $created = $now->format('Y-m-d H:i:s');

    $latest = '';
    try {
        $latest = (string) $pdo->query("SELECT MAX(filename) FROM schema_migrations")->fetchColumn();
    } catch (PDOException $e) {
        // No updates table yet — the backup simply has no update marker
    }

    $bodySha = hash('sha256', $body);
    $key = dbBackupSigningKey();
    $header = [
        '-- APEX-BACKUP 1',
        '-- role: ' . $role,
        '-- app_env: ' . (defined('APP_ENV') ? APP_ENV : ''),
        '-- db_name: ' . $dbName,
        '-- created: ' . $created,
        '-- latest_migration: ' . $latest,
        '-- body_sha256: ' . $bodySha,
        '-- signature: ' . ($key !== '' ? dbBackupSign($role, $dbName, $created, $bodySha, $key) : ''),
        '-- END-HEADER',
    ];
    $sql = implode("\n", $header) . "\n" . $body;

    $base = 'apex-' . $role . '-' . $now->format('Ymd-Hi');
    $zipped = dbBackupZip($base . '.sql', $sql, $now);
    if ($zipped !== null) {
        return ['filename' => $base . '.zip', 'data' => $zipped, 'mime' => 'application/zip', 'tables' => $tableCount, 'rows' => $rowTotal];
    }
    return ['filename' => $base . '.sql', 'data' => $sql, 'mime' => 'application/sql', 'tables' => $tableCount, 'rows' => $rowTotal];
}

/**
 * A one-file zip built in memory (no temp file). Null when PHP has no zlib.
 */
function dbBackupZip(string $name, string $data, DateTime $when): ?string
{
    if (!function_exists('gzdeflate')) {
        return null;
    }
    $compressed = gzdeflate($data, 6);
    if ($compressed === false) {
        return null;
    }
    $crc = pack('V', crc32($data));
    $dosTime = (((int) $when->format('G')) << 11) | (((int) $when->format('i')) << 5) | ((int) ((int) $when->format('s') / 2));
    $dosDate = ((((int) $when->format('Y')) - 1980) << 9) | (((int) $when->format('n')) << 5) | ((int) $when->format('j'));
    $len = strlen($name);

    $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 8, $dosTime, $dosDate, 0, strlen($compressed), strlen($data), $len, 0);
    // crc32 sits at offset 14 of the local header; rebuild with the real value
    $local = substr($local, 0, 14) . $crc . substr($local, 18) . $name;
    $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate, 0, strlen($compressed), strlen($data), $len, 0, 0, 0, 0, 0, 0);
    $central = substr($central, 0, 16) . $crc . substr($central, 20) . $name;

    $localPart = $local . $compressed;
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, 1, 1, strlen($central), strlen($localPart), 0);

    return $localPart . $central . $end;
}

// ========== READ AN UPLOADED FILE ==========

/**
 * The backup text from an uploaded .zip or .sql file.
 *
 * @param array $file  One entry of $_FILES
 * @return array{ok: bool, content?: string, error?: string}
 */
function dbBackupReadUpload(array $file): array
{
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'No file was received. Choose the backup file and try again (it may be larger than the server allows).'];
    }
    if (!is_uploaded_file($file['tmp_name']) && !defined('DB_BACKUP_TESTING')) {
        return ['ok' => false, 'error' => 'The upload was not accepted.'];
    }
    if (filesize($file['tmp_name']) > DB_BACKUP_MAX_UPLOAD) {
        return ['ok' => false, 'error' => 'That file is too large for a backup from this system.'];
    }
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'The file is empty.'];
    }

    if (substr($bytes, 0, 4) === "PK\x03\x04") {
        return dbBackupUnzip($file['tmp_name'], $bytes);
    }
    return ['ok' => true, 'content' => $bytes];
}

/**
 * The .sql text inside a zip: ZipArchive when PHP has it, otherwise a small reader that
 * understands the zips this system writes.
 */
function dbBackupUnzip(string $path, string $bytes): array
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (substr($name, -4) === '.sql') {
                    $content = $zip->getFromIndex($i);
                    $zip->close();
                    if ($content !== false) {
                        return ['ok' => true, 'content' => $content];
                    }
                    return ['ok' => false, 'error' => 'Could not read the .sql file inside the zip.'];
                }
            }
            $zip->close();
            return ['ok' => false, 'error' => 'There is no .sql file inside that zip.'];
        }
    }

    // Fallback: first entry only, sizes must be in the local header (as written by this system)
    $h = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen', substr($bytes, 4, 26));
    if (!$h || ($h['flags'] & 8) || $h['csize'] === 0) {
        return ['ok' => false, 'error' => 'This server cannot open that kind of zip. Unzip it first and upload the .sql file.'];
    }
    $data = substr($bytes, 30 + $h['nlen'] + $h['elen'], $h['csize']);
    if ($h['method'] === 8 && function_exists('gzinflate')) {
        $data = gzinflate($data);
    } elseif ($h['method'] !== 0) {
        return ['ok' => false, 'error' => 'Unsupported zip compression. Unzip it first and upload the .sql file.'];
    }
    if ($data === false) {
        return ['ok' => false, 'error' => 'Could not unpack the zip.'];
    }
    return ['ok' => true, 'content' => $data];
}

// ========== CHECK A BACKUP FILE ==========

/**
 * Replace quoted text and `identifiers` so statements can be checked for stray ";" and
 * unexpected content: strings become 0, identifiers become id.
 */
function dbBackupStripQuoted(string $s): string
{
    $out = '';
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $c = $s[$i];
        if ($c === "'" || $c === '"') {
            for ($i++; $i < $n; $i++) {
                if ($s[$i] === '\\') {
                    $i++;
                } elseif ($s[$i] === $c) {
                    if ($i + 1 < $n && $s[$i + 1] === $c) {
                        $i++;
                    } else {
                        break;
                    }
                }
            }
            $out .= '0';
        } elseif ($c === '`') {
            for ($i++; $i < $n; $i++) {
                if ($s[$i] === '`') {
                    if ($i + 1 < $n && $s[$i + 1] === '`') {
                        $i++;
                    } else {
                        break;
                    }
                }
            }
            $out .= 'id';
        } else {
            $out .= $c;
        }
    }
    return $out;
}

/**
 * Check one statement from a backup file. Only the statements a backup contains are accepted.
 *
 * @return string|null  The table the statement belongs to ('' for SET), or null when not accepted
 */
function dbBackupValidateStatement(string $stmt): ?string
{
    if (preg_match('/^SET FOREIGN_KEY_CHECKS=[01]$/', $stmt)) {
        return '';
    }
    if (preg_match('/^DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`$/', $stmt, $m)) {
        return $m[1];
    }

    $stripped = dbBackupStripQuoted($stmt);
    if (strpos($stripped, ';') !== false) {
        return null;
    }

    if (preg_match('/^CREATE TABLE `([A-Za-z0-9_]+)` \(/', $stmt, $m)) {
        if (!preg_match('/^CREATE TABLE id \(/', $stripped) || preg_match('/\bSELECT\b/i', $stripped)) {
            return null;
        }
        return $m[1];
    }

    if (preg_match('/^INSERT INTO `([A-Za-z0-9_]+)` \(/', $stmt, $m)) {
        if (!preg_match('/^INSERT INTO id \(id(?:, ?id)*\) VALUES (.+)$/s', $stripped, $v)) {
            return null;
        }
        // The values may only be strings (now 0), numbers, NULL and hex, in brackets and commas
        $rest = preg_replace('/NULL|0x[0-9A-Fa-f]+|-?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?/', '', $v[1]);
        if (!preg_match('/^[\s(),]*$/', $rest)) {
            return null;
        }
        return $m[1];
    }

    return null;
}

/**
 * Read and verify a backup file: header, integrity, and that it only contains accepted statements.
 *
 * @return array{ok: bool, error?: string, header?: array, statements?: string[], tables?: array<string,int>}
 */
function dbBackupParse(string $content): array
{
    $content = str_replace("\r\n", "\n", $content);
    $marker = "\n-- END-HEADER\n";
    $pos = strpos($content, $marker);
    if (strncmp($content, '-- APEX-BACKUP 1', 16) !== 0 || $pos === false) {
        return ['ok' => false, 'error' => 'This is not a backup made by this system. Only backups from "Back up now" can be restored here.'];
    }

    $header = [];
    foreach (explode("\n", substr($content, 0, $pos)) as $line) {
        if (preg_match('/^-- ([a-z_0-9]+): ?(.*)$/', $line, $m)) {
            $header[$m[1]] = $m[2];
        }
    }
    foreach (['role', 'db_name', 'created', 'body_sha256'] as $need) {
        if (!isset($header[$need])) {
            return ['ok' => false, 'error' => 'The backup header is incomplete.'];
        }
    }

    $body = substr($content, $pos + strlen($marker));
    if (!hash_equals($header['body_sha256'], hash('sha256', $body))) {
        return ['ok' => false, 'error' => 'The backup is damaged or has been changed (its checksum does not match).'];
    }

    $statements = [];
    $tables = [];
    foreach (explode(DB_BACKUP_DELIMITER, $body) as $chunk) {
        $lines = explode("\n", $chunk);
        while ($lines && (trim($lines[0]) === '' || strncmp($lines[0], '-- ', 3) === 0)) {
            if (preg_match('/^-- table ([A-Za-z0-9_]+) rows=(\d+)$/', $lines[0], $m) && $m[1] !== DB_BACKUP_SKIP_TABLE) {
                $tables[$m[1]] = (int) $m[2];
            }
            array_shift($lines);
        }
        $stmt = rtrim(trim(implode("\n", $lines)), ';');
        if ($stmt === '') {
            continue;
        }
        $table = dbBackupValidateStatement($stmt);
        if ($table === null) {
            return ['ok' => false, 'error' => 'The backup contains something that is not a normal backup statement, so it was refused: ' . substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . '…'];
        }
        if ($table === DB_BACKUP_SKIP_TABLE) {
            continue; // system_logs is never restored, even if a file contains it
        }
        $statements[] = $stmt;
    }
    if (count($statements) < 3) {
        return ['ok' => false, 'error' => 'The backup has no data in it.'];
    }

    return ['ok' => true, 'header' => $header, 'statements' => $statements, 'tables' => $tables];
}

/**
 * Whether THIS site may restore the given backup, and what it would change.
 *
 * @return array{ok: bool, error?: string, allowed?: bool, reason?: string, header?: array, statements?: string[], role?: string,
 *               tables?: array<int,array{name:string,file:int,now:?int}>, warnings?: string[]}
 */
function dbBackupCheck(PDO $pdo, string $content): array
{
    $parsed = dbBackupParse($content);
    if (!$parsed['ok']) {
        return $parsed;
    }
    $header = $parsed['header'];
    $myRole = dbBackupRole();
    $fileRole = $header['role'];
    $allowed = true;
    $reason = '';

    if (!in_array($fileRole, dbBackupAcceptedRoles($myRole), true)) {
        $allowed = false;
        $reason = $myRole === 'live'
            ? 'The live site only restores its own backups. This backup came from the ' . $fileRole . ' site.'
            : 'This ' . $myRole . ' site does not restore backups from the ' . $fileRole . ' site.';
    } elseif ($myRole === 'live') {
        $key = dbBackupSigningKey();
        if ($key === '') {
            $allowed = false;
            $reason = 'No BACKUP_SIGNING_KEY is set in this site\'s config.php, so a restore on live cannot be verified. Add it, then make a new backup.';
        } elseif (($header['db_name'] ?? '') !== (defined('DB_NAME') ? DB_NAME : '')) {
            $allowed = false;
            $reason = 'This backup is from a different database (' . ($header['db_name'] ?? '?') . ').';
        } elseif (empty($header['signature'])
            || !hash_equals(dbBackupSign($fileRole, $header['db_name'], $header['created'], $header['body_sha256'], $key), $header['signature'])) {
            $allowed = false;
            $reason = 'This backup was not signed by this live site (or was changed afterwards), so it was refused.';
        }
    }

    $tables = [];
    foreach ($parsed['tables'] as $name => $fileRows) {
        $now = null;
        try {
            $now = (int) $pdo->query('SELECT COUNT(*) FROM ' . dbBackupQuoteIdent($name))->fetchColumn();
        } catch (PDOException $e) {
            // Table does not exist here yet
        }
        $tables[] = ['name' => $name, 'file' => $fileRows, 'now' => $now];
    }

    $warnings = [];
    $current = '';
    try {
        $current = (string) $pdo->query("SELECT MAX(filename) FROM schema_migrations")->fetchColumn();
    } catch (PDOException $e) {
        // none
    }
    $fileLatest = $header['latest_migration'] ?? '';
    if ($fileLatest !== '' && $current !== '' && strcmp($fileLatest, $current) < 0) {
        $warnings[] = 'This backup is older than this database\'s latest update (' . $current . '). After restoring, run the pending updates.';
    } elseif ($fileLatest !== '' && $current !== '' && strcmp($fileLatest, $current) > 0) {
        $warnings[] = 'This backup contains updates this site\'s code does not have yet (' . $fileLatest . '). Restore only if the code is up to date too.';
    }

    return [
        'ok' => true,
        'allowed' => $allowed,
        'reason' => $reason,
        'role' => $myRole,
        'header' => $header,
        'statements' => $parsed['statements'],
        'tables' => $tables,
        'warnings' => $warnings,
    ];
}

// ========== RESTORE ==========

/**
 * Run a backup that passed dbBackupCheck(). Tables in the backup are dropped and recreated;
 * tables not in it (e.g. system_logs left out of the backup) are left alone.
 * Not atomic (MySQL cannot undo table changes), which is why a fresh backup is taken first.
 *
 * @param string[] $statements
 * @return array{ok: bool, message: string}
 */
function dbBackupRestore(PDO $pdo, array $statements): array
{
    @set_time_limit(300);
    $done = 0;
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
            $done++;
        }
    } catch (PDOException $e) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (PDOException $ignore) {
        }
        return ['ok' => false, 'message' => 'The restore stopped at statement ' . ($done + 1) . ' of ' . count($statements)
            . ': ' . $e->getMessage() . ' The database may be partly restored — restore the backup that was just downloaded to put it back.'];
    }
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (PDOException $e) {
    }
    return ['ok' => true, 'message' => 'Restored (' . $done . ' statements).'];
}
