<?php
// ONE-OFF SCRIPT — run once, then delete this file.
//
// Seeds system_variable_history with a single anchor row for every key
// in SYSTEM_VARIABLES, dated 1 Dec 2025 (when this intranet went live).
// This protects existing history: without an anchor, a past date with
// no history row falls back to the CURRENT live value, which becomes
// wrong the moment that value is ever changed. The anchor row holds
// what the value actually was for all of history up to today.
//
// Safe to run more than once — skips any variable that already has at
// least one history row, so it never creates a duplicate anchor.
//
// Usage:
//   Visit this file's URL directly in your browser WHILE LOGGED IN —
//   auth_api.php requires an active session, so this will not run via
//   CLI (php seed_variable_history_ONEOFF.php) without a valid session
//   cookie; use the browser.
//
// DEV workflow: truncate `system_variable_history` between test runs
// as you normally do, then re-run this script.
// LIVE workflow: run once, after verifying the result on dev.

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth_api.php'; // require login even for CLI/browser access
require_once ROOT_DIR . '/includes/helpers.php';

const ANCHOR_DATE = '2025-12-01';

header('Content-Type: text/plain');

$inserted = [];
$skipped  = [];

foreach (SYSTEM_VARIABLES as $name => $meta) {
    // Skip if this variable already has any history row.
    $checkStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM system_variable_history WHERE variable_name = ?"
    );
    $checkStmt->execute([$name]);
    if ((int) $checkStmt->fetchColumn() > 0) {
        $skipped[] = $name;
        continue;
    }

    $value = getSystemVariable($pdo, $name);

    $insertStmt = $pdo->prepare("
        INSERT INTO system_variable_history (variable_name, value, effective_from)
        VALUES (?, ?, ?)
    ");
    $insertStmt->execute([$name, $value, ANCHOR_DATE]);
    $inserted[] = "{$name} = " . (is_string($value) && strlen($value) > 60
        ? substr($value, 0, 60) . '...'
        : $value);
}

echo "Anchor date used: " . ANCHOR_DATE . "\n\n";

echo "Inserted (" . count($inserted) . "):\n";
foreach ($inserted as $line) {
    echo "  - {$line}\n";
}

echo "\nSkipped, already had history (" . count($skipped) . "):\n";
foreach ($skipped as $name) {
    echo "  - {$name}\n";
}

echo "\nDone. Verify the values above, then delete this file.\n";

