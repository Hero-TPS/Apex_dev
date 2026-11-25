<?php
// services/gps_import.php

$page_title = 'GPS Import';
$page_subtitle = 'GPS Import';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/gps_import.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'GPS Import'],
]);

if (empty($_SESSION['gps_import_csrf'])) {
    $_SESSION['gps_import_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['gps_import_csrf'];

/** "5 min ago", "2 h 10 min ago", "3 days ago" */
function gpsImportAge(int $timestamp): string
{
    $seconds = max(0, time() - $timestamp);
    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . ' h ' . floor(($seconds % 3600) / 60) . ' min ago';
    }
    $days = (int) floor($seconds / 86400);
    return $days . ($days === 1 ? ' day ago' : ' days ago');
}

// Import whatever is new before anything is shown, so the figures below are current
$import = gpsImportOnDemand($pdo);
$importFailed = ($import === null);

$scan = gpsImportScan();
$inboxExists = is_dir(gpsImportInboxDir());
$state = [];
$logStats = null;
$logError = false;
try {
    $state = gpsImportState($pdo);
    $logStats = $pdo->query("SELECT COUNT(*) AS points, MIN(recorded_at) AS first_at, MAX(recorded_at) AS last_at FROM gps_log")->fetch();
} catch (PDOException $e) {
    $logError = true;
    logError('GPS', 'GPS Import page could not read its tables', ['error' => $e->getMessage()]);
}
$keepDays = max(0, (int) getSystemVariable($pdo, 'gps_inbox_keep_days'));

include ROOT_DIR . '/includes/header.php';
?>

<div class="form-container">
    <h2>📍 GPS Import</h2>

    <p>GPSLogger on the phone uploads its CSV files to the <code>gps_inbox</code> folder by FTP.
        New points are imported automatically whenever this page is opened, so there is nothing to press.</p>

    <?php if ($importFailed || $logError): ?>
        <div class="error-message">The GPS import could not run. Run the pending updates in Services → Database Updates.
            If that does not help, the reason is in Services → System Logs (category GPS).</div>
    <?php elseif ($import['new'] > 0): ?>
        <div class="success-message">Just imported <?= (int) $import['new'] ?> new point(s) from <?= (int) $import['files'] ?> file(s).</div>
    <?php endif; ?>

    <h3>In the log</h3>
    <?php if ($logError): ?>
        <p class="at-update-empty">Not available.</p>
    <?php elseif ((int) $logStats['points'] === 0): ?>
        <p class="at-update-empty">No points yet.</p>
    <?php else: ?>
        <p class="at-update-env">
            Points: <strong><?= number_format((int) $logStats['points']) ?></strong>
            · From: <strong><?= e(date('Y-m-d H:i', (int) $logStats['first_at'])) ?></strong>
            · Latest point: <strong><?= e(date('Y-m-d H:i', (int) $logStats['last_at'])) ?></strong>
            (<?= e(gpsImportAge((int) $logStats['last_at'])) ?>)
        </p>
    <?php endif; ?>

    <h3>Files in the inbox (<?= count($scan['csv']) ?>)</h3>
    <?php if (!$inboxExists): ?>
        <div class="error-message">The <code>gps_inbox</code> folder is missing on this server. Deploy the folder from the repo
            (it holds the <code>.htaccess</code> that keeps the files private).</div>
    <?php elseif (empty($scan['csv'])): ?>
        <p class="at-update-empty">No CSV files in the inbox. Imported files are deleted after
            <?= $keepDays > 0 ? $keepDays . ' day(s)' : 'never (kept forever)' ?>.</p>
    <?php else: ?>
        <table class="at-update-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scan['csv'] as $file):
                    $known = $state[$file['name']] ?? null;
                    if ($known === null) {
                        $status = '⏳ Not read yet';
                    } elseif ($known['last_error'] !== null) {
                        $status = '⚠️ ' . $known['last_error'];
                    } else {
                        $status = '✅ Read ' . gpsImportAge((int) $known['imported_at']) . ', ' . (int) $known['row_count'] . ' rows';
                    }
                ?>
                    <tr>
                        <td><?= e($file['name']) ?></td>
                        <td><?= e(number_format($file['size'] / 1024, 1)) ?> KB</td>
                        <td><?= e(date('Y-m-d H:i', $file['modified'])) ?></td>
                        <td><?= e($status) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><small>Imported files are deleted <?= $keepDays > 0 ? $keepDays . ' day(s) after their last change' : 'never' ?>
                (System Variables → GPS Inbox). Files with a problem are never deleted.</small></p>
    <?php endif; ?>

    <?php if (!empty($scan['other'])): ?>
        <h3>Other files (<?= count($scan['other']) ?>)</h3>
        <p>These are not CSV files, so they are not imported or deleted. Remove them by FTP when you no longer need them.</p>
        <ul>
            <?php foreach ($scan['other'] as $name): ?>
                <li><?= e($name) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <button type="button" class="page-action-btn toggle" id="gpsImportBtn" <?= $inboxExists ? '' : 'disabled' ?>
            title="Reads every file again, even ones already read. Points already in the log are not duplicated.">🔄 Re-read all files</button>
    </p>
    <div id="gpsImportResult"></div>

    <details class="at-update-section">
        <summary>Setting up GPSLogger</summary>
        <ul>
            <li>Logging details: log to <strong>CSV</strong>, interval 60 seconds, location "Allow all the time".</li>
            <li>Auto send: choose <strong>FTP</strong>, then enter the server, port, user name and password of this
                site's FTP account. An interval of 10 minutes keeps the "Latest point" above close to real time.</li>
            <li>FTP directory: the <code>gps_inbox</code> folder on the server, usually <code>/htdocs/gps_inbox</code>.</li>
            <li>The folder is kept private by its <code>.htaccess</code>. Opening a CSV file's address in a browser
                should give "403 Forbidden", never the file.</li>
        </ul>
        <p><small>Speed is only kept for points where the phone had a satellite fix, because a point without one reports
                a speed of 0 even while driving. Each point also records whether it came from GPS or from the cell
                network, and how many satellites were seen.</small></p>
    </details>
</div>

<script>
    $(document).ready(function () {
        var csrf = <?= json_encode($csrf) ?>;
        var apiUrl = '<?= BASE_URL ?>/services/api/gps_import.php';
        var result = $('#gpsImportResult');

        $('#gpsImportBtn').on('click', function () {
            var btn = $(this).prop('disabled', true);
            result.empty();

            $.ajax({
                type: 'POST',
                url: apiUrl + '?action=run',
                data: { csrf: csrf },
                dataType: 'json',
                success: function (response) {
                    var box = $('<div>').addClass(response.success ? 'success-message' : 'error-message').text(response.message);
                    if (response.success) {
                        box.append(' ').append($('<a href="">').text('Reload to refresh the list'));
                    }
                    result.append(box);
                    btn.prop('disabled', false);
                },
                error: function (xhr) {
                    var message = '❌ Request failed. Please reload and try again.';
                    try {
                        var body = JSON.parse(xhr.responseText);
                        if (body && body.message) message = body.message;
                    } catch (ignore) { }
                    result.append($('<div>').addClass('error-message').text(message));
                    btn.prop('disabled', false);
                    logJsError('ERROR', 'GPS Import: request failed', { response: xhr.responseText });
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
