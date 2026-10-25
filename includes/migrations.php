<?php
// includes/migrations.php
//
// Database update runner. Each DB change is one plain .sql file in /migrations,
// named YYYYMMDD_HHMM_description.sql. Every database (dev, live, itinerary
// server) records the files it has run in its own `schema_migrations` table,
// so each one knows for itself what is pending.
//
// Used by: services/updates.php, services/api/updates.php, dashboard/index.php
// Expects config.php (ROOT_DIR, $pdo) and includes/helpers.php (logger) to be
// loaded by the caller.
//
// Rules for migration files:
//   - Never edit a file once it has run on any database. Add a new file.
//   - Write them so running twice is harmless (IF NOT EXISTS, INSERT IGNORE...).
//   - Plain DDL/DML only: no triggers, stored procedures or DELIMITER, because
//     statements are split on semicolons.

/**
 * Absolute path of the migrations folder.
 */
function migrationsDir(): string
{
    return ROOT_DIR . '/migrations';
}

/**
 * Whether a file name follows the YYYYMMDD_HHMM_description.sql convention.
 */
function isValidMigrationName(string $filename): bool
{
    return (bool) preg_match('/^\d{8}_\d{4}_[a-z0-9_]+\.sql$/', $filename);
}

/**
 * Checksum of a migration's content. Line endings are normalised first so a
 * file checked out with CRLF doesn't look "changed" compared to its LF copy.
 */
function migrationChecksum(string $sql): string
{
    return hash('sha256', str_replace("\r\n", "\n", $sql));
}

/**
 * Create the tracking table if it doesn't exist yet. This is the one piece of
 * schema the runner has to create itself — it can't track itself.
 */
function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(190) NOT NULL,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL,
            source VARCHAR(10) NOT NULL DEFAULT 'run',
            UNIQUE KEY uq_schema_migrations_filename (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Split SQL text into individual statements on semicolons, ignoring semicolons
 * inside quotes, backticks and comments. Comments are dropped.
 *
 * @return string[]
 */
function splitSqlStatements(string $sql): array
{
    $sql = str_replace("\r\n", "\n", $sql);
    $len = strlen($sql);
    $statements = [];
    $current = '';
    $quote = null; // ' " or `

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $len) {
                $current .= $sql[++$i]; // escaped character
            } elseif ($ch === $quote) {
                if ($next === $quote) {
                    $current .= $sql[++$i]; // doubled quote inside the string
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $current .= $ch;
        } elseif ($ch === '#' || ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2])))) {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $current .= "\n";
        } elseif ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;
            $current .= ' ';
        } elseif ($ch === ';') {
            $statement = trim($current);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $current = '';
        } else {
            $current .= $ch;
        }
    }

    $statement = trim($current);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

/**
 * Read the migrations folder.
 *
 * @return array{files: array<string, array{path: string, checksum: string}>, invalid: string[]}
 *         'files' is keyed by file name, sorted oldest first.
 */
function getMigrationFiles(): array
{
    $files = [];
    $invalid = [];

    foreach (glob(migrationsDir() . '/*.sql') ?: [] as $path) {
        $name = basename($path);
        if (!isValidMigrationName($name)) {
            $invalid[] = $name;
            continue;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        $files[$name] = ['path' => $path, 'checksum' => migrationChecksum($content)];
    }

    ksort($files);
    sort($invalid);

    return ['files' => $files, 'invalid' => $invalid];
}

/**
 * Compare the migrations folder with this database's schema_migrations table.
 * Read-only — never creates the tracking table, so it is safe to call on every
 * dashboard load. If the table doesn't exist yet, nothing counts as applied.
 *
 * @return array{
 *   pending: string[],
 *   applied: array<int, array{filename: string, checksum: string, applied_at: string, source: string}>,
 *   changed: string[],
 *   missing: string[],
 *   invalid: string[],
 *   table_exists: bool
 * }
 *   pending  = files not yet run here (oldest first)
 *   changed  = files edited after they were run here
 *   missing  = run here, but the file isn't in this checkout (normal when another
 *              branch's migrations were run on this database)
 */
function getMigrationStatus(PDO $pdo): array
{
    $folder = getMigrationFiles();
    $applied = [];
    $tableExists = true;

    try {
        $stmt = $pdo->query("SELECT filename, checksum, applied_at, source FROM schema_migrations ORDER BY applied_at DESC, id DESC");
        $applied = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tableExists = false;
    }

    $appliedByName = [];
    foreach ($applied as $row) {
        $appliedByName[$row['filename']] = $row;
    }

    // Pending = every file not recorded here, regardless of how its date
    // compares to what has already run (branch migrations can interleave).
    $pending = array_values(array_diff(array_keys($folder['files']), array_keys($appliedByName)));

    $changed = [];
    foreach ($appliedByName as $name => $row) {
        if (isset($folder['files'][$name]) && $folder['files'][$name]['checksum'] !== $row['checksum']) {
            $changed[] = $name;
        }
    }
    sort($changed);

    $missing = array_values(array_diff(array_keys($appliedByName), array_keys($folder['files'])));
    sort($missing);

    return [
        'pending' => $pending,
        'applied' => $applied,
        'changed' => $changed,
        'missing' => $missing,
        'invalid' => $folder['invalid'],
        'table_exists' => $tableExists,
    ];
}

/**
 * Number of pending migrations, for the dashboard banner. Returns 0 on any
 * error so a database problem can never break the dashboard itself.
 */
function countPendingMigrations(PDO $pdo): int
{
    try {
        return count(getMigrationStatus($pdo)['pending']);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Run a single migration file and record it. Stops at the first failing
 * statement and records nothing. MySQL/MariaDB can't roll back DDL, so
 * statements before the failing one stay applied — fix the file and run it
 * again (it should be written to be safe to repeat).
 *
 * @return array{success: bool, message: string}
 */
function runMigration(PDO $pdo, string $filename): array
{
    $folder = getMigrationFiles();
    if (!isset($folder['files'][$filename])) {
        return ['success' => false, 'message' => "Migration file not found: {$filename}"];
    }

    ensureMigrationsTable($pdo);

    $check = $pdo->prepare("SELECT 1 FROM schema_migrations WHERE filename = ?");
    $check->execute([$filename]);
    if ($check->fetchColumn()) {
        return ['success' => false, 'message' => "{$filename} has already been run on this database."];
    }

    $sql = file_get_contents($folder['files'][$filename]['path']);
    $statements = splitSqlStatements($sql === false ? '' : $sql);
    if (empty($statements)) {
        return ['success' => false, 'message' => "{$filename} contains no SQL statements."];
    }

    $lockStmt = $pdo->query("SELECT GET_LOCK('apex_migrations', 0)");
    if ((int) $lockStmt->fetchColumn() !== 1) {
        return ['success' => false, 'message' => 'Another update is already running. Try again in a moment.'];
    }

    try {
        foreach ($statements as $index => $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                $number = $index + 1;
                $snippet = mb_substr(preg_replace('/\s+/', ' ', $statement), 0, 120);
                logError('DB_UPDATE', 'Migration failed', [
                    'file' => $filename,
                    'statement_number' => $number,
                    'statement' => $snippet,
                    'error' => $e->getMessage(),
                ]);
                return [
                    'success' => false,
                    'message' => "{$filename} failed at statement {$number} of " . count($statements)
                        . ": " . $e->getMessage()
                        . ($index > 0 ? ' — the earlier statements were already applied; fix the file and run it again.' : ''),
                ];
            }
        }

        $record = $pdo->prepare("
            INSERT INTO schema_migrations (filename, checksum, applied_at, source)
            VALUES (?, ?, NOW(), 'run')
        ");
        $record->execute([$filename, $folder['files'][$filename]['checksum']]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('apex_migrations')")->closeCursor();
    }

    logInfo('DB_UPDATE', 'Migration applied', ['file' => $filename, 'statements' => count($statements)]);

    return ['success' => true, 'message' => "{$filename} applied (" . count($statements) . ' statement' . (count($statements) === 1 ? '' : 's') . ').'];
}

/**
 * Run every pending migration, oldest first, stopping at the first failure.
 *
 * @return array{success: bool, message: string, results: array<int, array{file: string, success: bool, message: string}>}
 */
function runPendingMigrations(PDO $pdo): array
{
    $pending = getMigrationStatus($pdo)['pending'];
    if (empty($pending)) {
        return ['success' => true, 'message' => 'Nothing pending — this database is up to date.', 'results' => []];
    }

    $results = [];
    foreach ($pending as $filename) {
        $result = runMigration($pdo, $filename);
        $results[] = ['file' => $filename] + $result;
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => 'Stopped at ' . $filename . '. ' . $result['message'],
                'results' => $results,
            ];
        }
    }

    return [
        'success' => true,
        'message' => count($results) . ' update' . (count($results) === 1 ? '' : 's') . ' applied.',
        'results' => $results,
    ];
}

/**
 * Record a migration as applied without running it — for a change that was
 * already made on this database by hand (e.g. in phpMyAdmin).
 *
 * @return array{success: bool, message: string}
 */
function markMigrationApplied(PDO $pdo, string $filename): array
{
    $folder = getMigrationFiles();
    if (!isset($folder['files'][$filename])) {
        return ['success' => false, 'message' => "Migration file not found: {$filename}"];
    }

    ensureMigrationsTable($pdo);

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO schema_migrations (filename, checksum, applied_at, source)
        VALUES (?, ?, NOW(), 'marked')
    ");
    $stmt->execute([$filename, $folder['files'][$filename]['checksum']]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => "{$filename} is already recorded on this database."];
    }

    logInfo('DB_UPDATE', 'Migration marked as applied (not run)', ['file' => $filename]);

    return ['success' => true, 'message' => "{$filename} marked as applied (not run)."];
}
