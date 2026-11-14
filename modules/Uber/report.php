<?php
// modules/Uber/report.php
// Fleet manager report for one Uber week (Mon–Sun): where things stand with
// everything due. Shows the most recent week entered unless ?id= picks another.
// The figures come from getUberWeekReport() in helper.php.
$page_title = 'Fleet Manager Report';
$page_subtitle = 'Weekly Uber report';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'Uber', 'url' => BASE_URL . '/modules/Uber/'],
    ['label' => 'Fleet Report'],
]);

$requestedId = intval($_GET['id'] ?? 0);
$report = null;
$loadError = false;
try {
    $report = getUberWeekReport($pdo, $requestedId > 0 ? $requestedId : null);
} catch (PDOException $e) {
    $loadError = true;
    logError('UBER', 'Failed to build the fleet report', ['error' => $e->getMessage(), 'requested_id' => $requestedId]);
}

$money = function (float $amount): string {
    return 'R' . number_format($amount, 2);
};
// Negative = behind, positive = ahead (same meaning as the Balance on the Uber reports)
$signedMoney = function (float $amount): string {
    return ($amount < 0 ? '-' : '') . 'R' . number_format(abs($amount), 2);
};

include ROOT_DIR . '/includes/header.php';
?>

<h2>📋 Fleet Manager Report</h2>

<?php if ($loadError): ?>
    <div class="error-message">Could not build the report. If this is the first time, run the pending updates under Services → Database Updates.</div>
<?php elseif ($report === null): ?>
    <div class="no-bookings">
        <h3>🚗 <?= $requestedId > 0 ? 'Week not found' : 'No Uber weeks entered yet' ?></h3>
        <p><a href="<?= BASE_URL ?>/modules/Uber/">Back to Uber Reports</a></p>
    </div>
<?php else: ?>
    <div class="at-fleet-report">
        <div class="at-fleet-report-nav">
            <?php if ($report['prev_id'] !== null): ?>
                <a class="action-btn" href="?id=<?= (int) $report['prev_id'] ?>">◀ Earlier week</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($report['next_id'] !== null): ?>
                <a class="action-btn" href="?id=<?= (int) $report['next_id'] ?>">Later week ▶</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>

        <div class="financial-month-block at-fleet-report-card">
            <div class="month-header">
                <h3>🚗 Uber week</h3>
                <span><strong><?= htmlspecialchars($report['week_display']) ?></strong></span>
            </div>

            <div class="metric-row"><span>💳 Card income:</span>        <strong><?= $money($report['card_income']) ?></strong></div>
            <div class="metric-row"><span>Car rental:</span>            <span><?= $money($report['car_rental']) ?></span></div>
            <div class="metric-row"><span>🚨 Fines:</span>              <span><?= $money($report['fines']) ?></span></div>
            <div class="metric-row"><span>🔧 Vehicle repairs paid:</span> <span><?= $money($report['vehicle_repairs']) ?></span></div>
            <div class="metric-row"><span>Net this week:</span>         <strong class="net-amount <?= $report['net'] >= 0 ? 'profit' : 'loss' ?>"><?= $signedMoney($report['net']) ?></strong></div>
            <div class="metric-row"><span>💰 Paid in:</span>            <strong><?= $money($report['paid_in']) ?></strong></div>
            <?php if ($report['balance'] !== null): ?>
                <div class="metric-row">
                    <span>📊 Current balance:</span>
                    <strong class="net-amount <?= $report['balance'] >= 0 ? 'profit' : 'loss' ?>"><?= $signedMoney($report['balance']) ?></strong>
                </div>
                <div class="metric-row">
                    <span></span>
                    <span class="at-help-text"><?= $report['balance'] < 0 ? 'Behind — still to be paid in' : 'Ahead' ?><?= $report['balance_is_override'] ? ' · ⚠️ manually corrected this week' : '' ?></span>
                </div>
            <?php else: ?>
                <div class="metric-row"><span>📊 Current balance:</span> <span class="at-help-text">No starting balance set yet</span></div>
            <?php endif; ?>

            <h4 class="at-fleet-report-heading">🔧 Vehicle repairs</h4>
            <?php if (empty($report['repairs']) && $report['repairs_unlinked'] <= 0): ?>
                <p class="at-help-text">Nothing owing and no payments this week.</p>
            <?php else: ?>
                <ul class="at-repair-payments-list">
                    <?php foreach ($report['repairs'] as $repair): ?>
                        <li>
                            <span><?= htmlspecialchars($repair['description']) ?></span>
                            <span>
                                paid <?= $money($repair['paid_this_week']) ?> ·
                                <strong><?= $money($repair['remaining']) ?></strong> still owing
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($report['repairs_unlinked'] > 0): ?>
                        <li>
                            <span>Other repair costs (not linked to a repair)</span>
                            <span>paid <?= $money($report['repairs_unlinked']) ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
                <?php if (!empty($report['repairs'])): ?>
                    <div class="metric-row"><span>Total repairs still owing:</span> <strong><?= $money($report['repairs_owing']) ?></strong></div>
                <?php endif; ?>
            <?php endif; ?>

            <h4 class="at-fleet-report-heading">📝 Notes</h4>
            <?php if ($report['notes'] !== ''): ?>
                <p class="at-fleet-report-notes"><?= nl2br(htmlspecialchars($report['notes'])) ?></p>
            <?php else: ?>
                <p class="at-help-text">No notes for this week.</p>
            <?php endif; ?>

            <div class="metric-row">
                <span>🚙 Last odo (fuel log):</span>
                <span>
                    <?php if ($report['odo'] !== null): ?>
                        <strong><?= number_format($report['odo']['km'], 0) ?> km</strong>
                        <span class="at-help-text">· <?= htmlspecialchars($report['odo']['date']) ?></span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="at-fleet-report-actions">
            <a class="action-btn edit-btn" href="<?= BASE_URL ?>/modules/Uber/edit.php?id=<?= (int) $report['id'] ?>">✏️ Edit this week</a>
            <a class="action-btn" href="<?= BASE_URL ?>/modules/Uber/">Uber Reports</a>
        </div>
    </div>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
