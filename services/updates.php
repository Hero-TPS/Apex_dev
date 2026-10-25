<?php
// services/updates.php

$page_title = 'Database Updates';
$page_subtitle = 'Database Updates';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/migrations.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'Database Updates'],
]);

if (empty($_SESSION['updates_csrf'])) {
    $_SESSION['updates_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['updates_csrf'];

$status = getMigrationStatus($pdo);
$folder = getMigrationFiles();
$pendingCount = count($status['pending']);
$isProduction = defined('APP_ENV') && APP_ENV === 'production';

include ROOT_DIR . '/includes/header.php';
?>

<div class="form-container">
    <h2>🛠️ Database Updates</h2>

    <p class="at-update-env">
        Environment: <strong><?= e(defined('APP_ENV') ? APP_ENV : 'unknown') ?></strong>
        · Database: <strong><?= e(defined('DB_NAME') ? DB_NAME : 'unknown') ?></strong>
    </p>

    <?php if ($isProduction && $pendingCount > 0): ?>
        <div class="info-message">
            ⚠️ This is the live database. Take a phpMyAdmin export first, then run the updates.
        </div>
    <?php endif; ?>

    <h3>Pending updates (<?= $pendingCount ?>)</h3>

    <?php if ($pendingCount === 0): ?>
        <p class="at-update-empty">✅ This database is up to date.</p>
    <?php else: ?>
        <?php foreach ($status['pending'] as $name):
            $sql = file_get_contents($folder['files'][$name]['path']);
        ?>
            <div class="at-update-item" data-file="<?= e($name) ?>">
                <div class="at-update-head">
                    <span class="at-update-name"><?= e($name) ?></span>
                    <span class="at-update-actions">
                        <button type="button" class="page-action-btn save at-update-run">▶ Run</button>
                        <button type="button" class="page-action-btn toggle at-update-mark"
                            title="Record as applied without running it (use if you already made this change by hand)">
                            ✔ Mark as applied
                        </button>
                    </span>
                </div>
                <details>
                    <summary>Show SQL</summary>
                    <pre class="at-update-sql"><?= e($sql === false ? '' : $sql) ?></pre>
                </details>
            </div>
        <?php endforeach; ?>

        <?php if ($pendingCount > 1): ?>
            <button type="button" class="page-action-btn primary" id="runAllBtn">
                ▶ Run all <?= $pendingCount ?> pending updates
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <div id="updatesResult"></div>

    <?php if (!empty($status['changed'])): ?>
        <h3>⚠️ Edited after they were run</h3>
        <div class="error-message">
            These files no longer match what was run on this database. Never edit a migration that has run
            anywhere — restore the original and add a new file for the change.
            <ul>
                <?php foreach ($status['changed'] as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($status['invalid'])): ?>
        <h3>⚠️ Ignored files</h3>
        <div class="error-message">
            These files in <code>migrations/</code> don't follow the <code>YYYYMMDD_HHMM_description.sql</code>
            naming and are ignored:
            <ul>
                <?php foreach ($status['invalid'] as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($status['missing'])): ?>
        <details class="at-update-section">
            <summary>Run here, but not in this version of the code (<?= count($status['missing']) ?>)</summary>
            <p><small>Normal when another branch's updates were run on this database. They appear here once that
                    branch is merged.</small></p>
            <ul>
                <?php foreach ($status['missing'] as $name): ?>
                    <li><?= e($name) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <details class="at-update-section">
        <summary>Already applied (<?= count($status['applied']) ?>)</summary>
        <?php if (empty($status['applied'])): ?>
            <p><small>Nothing recorded yet on this database.</small></p>
        <?php else: ?>
            <table class="at-update-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Applied</th>
                        <th>How</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['applied'] as $row): ?>
                        <tr>
                            <td><?= e($row['filename']) ?></td>
                            <td><?= e($row['applied_at']) ?></td>
                            <td><?= $row['source'] === 'marked' ? 'Marked (not run)' : 'Run' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </details>

    <details class="at-update-section">
        <summary>How database updates work</summary>
        <ul>
            <li>Each change is one file in <code>migrations/</code>, named
                <code>YYYYMMDD_HHMM_description.sql</code>.</li>
            <li>Every database (dev, live, itinerary) keeps its own record of what it has run.</li>
            <li>Never edit a file once it has run anywhere — add a new one.</li>
            <li>Write updates so running them twice is harmless (<code>IF NOT EXISTS</code>,
                <code>INSERT IGNORE</code>).</li>
            <li>Run stops at the first failing statement and records nothing. Earlier statements in that file stay
                applied, so fix the file and run it again.</li>
            <li>Don't change a database's structure by hand in phpMyAdmin — put it in a file instead.</li>
        </ul>
    </details>
</div>

<script>
    $(document).ready(function () {
        var csrf = <?= json_encode($csrf) ?>;
        var apiUrl = '<?= BASE_URL ?>/services/api/updates.php';
        var result = $('#updatesResult');

        function showResult(success, message, reloadHint) {
            var box = $('<div>').addClass(success ? 'success-message' : 'error-message').text(message);
            if (reloadHint) {
                box.append(' ').append($('<a href="">').text('Reload to refresh the list'));
            }
            result.empty().append(box);
        }

        function send(action, file, confirmText) {
            if (confirmText && !confirm(confirmText)) return;

            $('.page-action-btn').prop('disabled', true);
            result.empty();

            $.ajax({
                type: 'POST',
                url: apiUrl + '?action=' + action,
                data: { csrf: csrf, file: file || '' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showResult(true, response.message, false);
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        showResult(false, response.message, true);
                        $('.page-action-btn').prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    var message = '❌ Request failed. Please reload and try again.';
                    try {
                        var body = JSON.parse(xhr.responseText);
                        if (body && body.message) message = body.message;
                    } catch (ignore) { }
                    showResult(false, message, true);
                    $('.page-action-btn').prop('disabled', false);
                    logJsError('ERROR', 'Database Updates: request failed', { action: action, file: file, response: xhr.responseText });
                }
            });
        }

        $('.at-update-run').on('click', function () {
            var file = $(this).closest('.at-update-item').data('file');
            send('run', file, 'Run ' + file + ' on this database?');
        });

        $('.at-update-mark').on('click', function () {
            var file = $(this).closest('.at-update-item').data('file');
            send('mark', file, 'Record ' + file + ' as applied WITHOUT running it? Only do this if the change was already made by hand on this database.');
        });

        $('#runAllBtn').on('click', function () {
            send('run_all', '', 'Run all pending updates on this database, oldest first?');
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
