<?php
// services/cleanup.php

$page_title = 'Cleanup Tools';
$page_subtitle = 'Cleanup Tools';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'Cleanup Tools'],
]);
include ROOT_DIR . '/includes/header.php';

// Count overdue bookings for the cleanup preview
$today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');
$overdueStmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE trip_date < ? AND status != 'completed'
");
$overdueStmt->execute([$today]);
$overdueCount = (int) $overdueStmt->fetchColumn();
?>

<!-- Cleanup Tools -->
<div class="form-container">
    <h2>🧹 Cleanup Tools</h2>

    <div class="form-group">
        <label>Mark Past Overdue Bookings as Completed</label>
        <p class="cleanup-info">
            <?php if ($overdueCount > 0): ?>
                <strong><?= $overdueCount ?></strong> past booking<?= $overdueCount !== 1 ? 's are' : ' is' ?> still marked as <em>confirmed</em> but the trip date has passed.
            <?php else: ?>
                ✅ No overdue bookings found. Everything is up to date.
            <?php endif; ?>
        </p>
        <?php if ($overdueCount > 0): ?>
            <button id="markOverdueBtn" class="page-action-btn toggle">
                ✅ Mark <?= $overdueCount ?> Overdue Booking<?= $overdueCount !== 1 ? 's' : '' ?> as Done
            </button>
        <?php endif; ?>
    </div>
    <div id="cleanupResult"></div>
</div>

<script>
    $(document).ready(function () {
        $('#markOverdueBtn').on('click', function () {
            if (!confirm('Mark all past unconfirmed bookings as completed? This cannot be undone.')) return;

            var btn = $(this);
            var result = $('#cleanupResult');

            btn.prop('disabled', true).text('Working...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/services/api/index.php?action=mark_overdue_complete',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                        btn.fadeOut(); // Button no longer needed
                    } else {
                        btn.prop('disabled', false).text('✅ Mark Overdue Bookings as Done');
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function () {
                    btn.prop('disabled', false).text('✅ Mark Overdue Bookings as Done');
                    result.html('<div class="error-message">❌ Request failed.</div>');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
