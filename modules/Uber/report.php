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
require_once ROOT_DIR . '/modules/WhatsApp/helpers.php';

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

// The message that would be sent, and the history of what was sent for this week.
$previewText = '';
$sendLog = [];
$sendLogError = false;
$hasNumber = false;
if ($report !== null) {
    try {
        $previewText = getUberReportMessage($pdo, $report);
    } catch (PDOException $e) {
        logError('UBER', 'Failed to build the fleet report message', ['error' => $e->getMessage()]);
    }
    try {
        $sendLog = getUberReportLog($pdo, (int) $report['id']);
    } catch (PDOException $e) {
        $sendLogError = true;
        logError('UBER', 'Failed to load the fleet report send log', ['error' => $e->getMessage()]);
    }
    $hasNumber = trim((string) getSystemVariable($pdo, 'fleet_manager_whatsapp')) !== '';
}

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

        <div class="at-fleet-send">
            <h3>💬 Send to fleet manager</h3>
            <div id="fleet-send-notice"></div>
            <?php if ($previewText === ''): ?>
                <p class="at-help-text">The report message is not set up yet. Run the pending updates under Services → Database Updates.</p>
            <?php else: ?>
                <pre class="at-fleet-preview"><?= htmlspecialchars($previewText) ?></pre>
                <?php if (!$hasNumber): ?>
                    <p class="at-help-text">No fleet manager number is set yet. Add it under Services → System Variables to enable sending.</p>
                <?php endif; ?>
                <button type="button" id="fleetSendBtn" class="page-action-btn save"<?= $hasNumber ? '' : ' disabled' ?>>📤 Send on WhatsApp</button>
            <?php endif; ?>

            <details class="at-fleet-log">
                <summary>Send history (<?= count($sendLog) ?>)</summary>
                <?php if ($sendLogError): ?>
                    <p class="at-help-text">The send history is not available yet. Run the pending updates under Services → Database Updates.</p>
                <?php elseif (empty($sendLog)): ?>
                    <p class="at-help-text">This week's report has not been sent yet.</p>
                <?php else: ?>
                    <?php foreach ($sendLog as $entry): ?>
                        <div class="at-fleet-log-entry">
                            <strong><?= htmlspecialchars($entry['sent_display']) ?></strong>
                            <span class="at-help-text">· to <?= htmlspecialchars($entry['recipient_phone']) ?></span>
                            <pre class="at-fleet-log-text"><?= htmlspecialchars($entry['message_content']) ?></pre>
                        </div>
                    <?php endforeach; ?>
                    <p class="at-help-text">Recorded when Send was tapped. WhatsApp itself does not confirm delivery.</p>
                <?php endif; ?>
            </details>
        </div>

        <div class="at-fleet-report-actions">
            <a class="action-btn edit-btn" href="<?= BASE_URL ?>/modules/Uber/edit.php?id=<?= (int) $report['id'] ?>">✏️ Edit this week</a>
            <a class="action-btn" href="<?= BASE_URL ?>/modules/Uber/">Uber Reports</a>
        </div>
    </div>
<?php endif; ?>

<?php if ($report !== null && $previewText !== '' && $hasNumber): ?>
<script>
    $(document).ready(function () {
        var btn = $('#fleetSendBtn');
        var notice = $('#fleet-send-notice');

        function showError(text) {
            notice.html($('<div>').addClass('error-message').text(text));
            btn.prop('disabled', false).text('📤 Send on WhatsApp');
        }

        btn.on('click', function () {
            // Open the tab straight away (inside the tap) so the browser does not block it
            var waTab = window.open('', '_blank');
            notice.empty();
            btn.prop('disabled', true).text('Preparing…');
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Uber/api/report.php',
                data: { action: 'send', id: <?= (int) $report['id'] ?> },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.url) {
                        if (waTab) { waTab.location.href = res.url; } else { window.location.href = res.url; }
                        // Reload so the new entry shows in the send history
                        setTimeout(function () { window.location.reload(); }, 800);
                    } else {
                        if (waTab) { waTab.close(); }
                        showError(res.message || 'Could not prepare the message.');
                    }
                },
                error: function (xhr) {
                    if (waTab) { waTab.close(); }
                    var msg = 'Could not prepare the message. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                    showError(msg);
                }
            });
        });
    });
</script>
<?php endif; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
