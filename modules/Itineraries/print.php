<?php
// modules/Itineraries/print.php
// Printable (and "Save as PDF") client version of an itinerary: times, places, client notes and the total cost.
// Internal notes are never shown. Standalone page without the site header, so it prints clean.

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/modules/Itineraries/helpers.php';

if (!itineraryMigrationReady($pdo)) {
    echo itineraryMigrationNotice();
    exit;
}

$id  = intval($_GET['id'] ?? 0);
$itn = $id > 0 ? fetchItinerary($pdo, $id) : null;
if (!$itn) {
    echo '<div class="error-message">Itinerary not found.</div>';
    exit;
}
$stops = fetchItineraryStops($pdo, $id);

$returns   = !empty($itn['return_to_pickup']);
$cost      = (float) ($itn['cost'] ?? 0);
$dateText  = !empty($itn['trip_date']) ? (new DateTime($itn['trip_date'], new DateTimeZone(TIME_ZONE)))->format('l, j F Y') : '';
$startText = !empty($itn['start_time']) ? substr($itn['start_time'], 0, 5) : '';

$passenger = trim((string) ($itn['passenger_name'] ?? ''));
if ($passenger !== '' && strcasecmp($passenger, (string) $itn['client_name']) === 0) {
    $passenger = '';
}

/** "1:30" for 90 minutes */
$waitText = function ($minutes): string {
    $m = (int) $minutes;
    return intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Itinerary - <?= e($itn['client_name']) ?><?= $dateText !== '' ? ' - ' . e($dateText) : '' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body class="at-itn-print-body">
<div class="at-itn-print">
    <div class="at-itn-print-actions">
        <button type="button" class="page-action-btn primary" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <a href="<?= BASE_URL ?>/modules/Itineraries/edit.php?id=<?= (int) $itn['id'] ?>" class="page-action-btn back">Back to itinerary</a>
    </div>

    <h1><?= e(BUSINESS_NAME) ?></h1>
    <h2>Trip itinerary</h2>

    <table class="at-itn-print-meta">
        <tr><th>Client</th><td><?= e($itn['client_name']) ?></td></tr>
        <?php if ($passenger !== ''): ?>
            <tr><th>Passenger</th><td><?= e($passenger) ?></td></tr>
        <?php endif; ?>
        <?php if ($dateText !== ''): ?>
            <tr><th>Date</th><td><?= e($dateText) ?></td></tr>
        <?php endif; ?>
    </table>

    <ol class="at-itn-print-stops">
        <li>
            <span class="at-itn-print-time"><?= e($startText) ?></span>
            <span class="at-itn-print-place"><strong>Pickup:</strong> <?= e($itn['pickup'] ?? '') ?></span>
        </li>
        <?php foreach ($stops as $s): ?>
            <li>
                <span class="at-itn-print-time"><?= !empty($s['arrival_time']) ? e(substr($s['arrival_time'], 0, 5)) : '' ?></span>
                <span class="at-itn-print-place">
                    <?= e($s['location']) ?>
                    <?php if ((int) ($s['wait_minutes'] ?? 0) > 0): ?>
                        <em>(wait <?= e($waitText($s['wait_minutes'])) ?>)</em>
                    <?php endif; ?>
                    <?php if (trim((string) ($s['client_notes'] ?? '')) !== ''): ?>
                        <span class="at-itn-print-note"><?= nl2br(e(trim($s['client_notes']))) ?></span>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
        <li>
            <span class="at-itn-print-time"></span>
            <span class="at-itn-print-place">
                <?php if ($returns): ?>
                    <strong>Return to pickup:</strong> <?= e($itn['pickup'] ?? '') ?>
                <?php else: ?>
                    <strong>Destination:</strong> <?= e($itn['destination'] ?? '') ?>
                <?php endif; ?>
            </span>
        </li>
    </ol>

    <?php if ($cost > 0): ?>
        <p class="at-itn-print-cost">Total cost: <strong>R <?= number_format($cost, 2) ?></strong></p>
    <?php endif; ?>
</div>
</body>
</html>
