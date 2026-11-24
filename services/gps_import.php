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

$files = gpsImportListFiles();
$inboxExists = is_dir(gpsImportInboxDir());

// What is in the log now
$logStats = null;
$logError = false;
try {
    $logStats = $pdo->query("SELECT COUNT(*) AS points, MIN(recorded_at) AS first_at, MAX(recorded_at) AS last_at FROM gps_log")->fetch();
} catch (PDOException $e) {
    $logError = true;
    logError('GPS', 'GPS Import page could not read gps_log', ['error' => $e->getMessage()]);
}

include ROOT_DIR . '/includes/header.php';
?>

<div class="form-container">
    <h2>📍 GPS Import</h2>

    <p>GPSLogger on the phone uploads its CSV files to the <code>gps_inbox</code> folder by FTP.
        <strong>Import now</strong> reads every CSV there and adds the points that are not in the log yet.
        It is safe to run as often as you like.</p>

    <h3>In the log</h3>
    <?php if ($logError): ?>
        <div class="error-message">The GPS log could not be read. Run the pending updates in Services → Database Updates.</div>
    <?php elseif ((int) $logStats['points'] === 0): ?>
        <p class="at-update-empty">No points yet.</p>
    <?php else: ?>
        <p class="at-update-env">
            Points: <strong><?= number_format((int) $logStats['points']) ?></strong>
            · From: <strong><?= e(date('Y-m-d H:i', (int) $logStats['first_at'])) ?></strong>
            · Latest: <strong><?= e(date('Y-m-d H:i', (int) $logStats['last_at'])) ?></strong>
        </p>
    <?php endif; ?>

    <h3>Files in the inbox (<?= count($files) ?>)</h3>
    <?php if (!$inboxExists): ?>
        <div class="error-message">The <code>gps_inbox</code> folder is missing on this server. Deploy the folder from the repo
            (it holds the <code>.htaccess</code> that keeps the files private).</div>
    <?php elseif (empty($files)): ?>
        <p class="at-update-empty">No CSV files yet.</p>
    <?php else: ?>
        <table class="at-update-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Uploaded</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?= e($file['name']) ?></td>
                        <td><?= e(number_format($file['size'] / 1024, 1)) ?> KB</td>
                        <td><?= e(date('Y-m-d H:i', $file['modified'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p>
        <button type="button" class="page-action-btn primary" id="gpsImportBtn" <?= $inboxExists ? '' : 'disabled' ?>>▶ Import now</button>
    </p>
    <div id="gpsImportResult"></div>

    <details class="at-update-section">
        <summary>Setting up GPSLogger</summary>
        <ul>
            <li>Logging details: log to <strong>CSV</strong>, interval 60 seconds, location "Allow all the time".</li>
            <li>Auto send: choose <strong>FTP</strong>, then enter the server, port, user name and password of this
                site's FTP account.</li>
            <li>FTP directory: the <code>gps_inbox</code> folder on the server, usually <code>/htdocs/gps_inbox</code>.</li>
            <li>The folder is kept private by its <code>.htaccess</code>. Opening a CSV file's address in a browser
                should give "403 Forbidden", never the file.</li>
            <li>Old files stay in the folder. Delete the ones already imported by FTP when the folder gets long.</li>
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
                        box.append(' ').append($('<a href="">').text('Reload to refresh the figures'));
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
