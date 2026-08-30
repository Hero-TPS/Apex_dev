<?php
$page_title = 'System Logs';
$page_subtitle = 'Activity & Error Logs';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Maintenance', 'url' => BASE_URL . '/maintenance/'],
    ['label' => 'Logs'],
]);
include ROOT_DIR . '/includes/header.php';

// Get filter parameters
$level = $_GET['level'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$limit = intval($_GET['limit'] ?? 100);

// Build query
$where = [];
$params = [];

if ($level !== 'all') {
    $where[] = "level = ?";
    $params[] = strtoupper($level);
}

if ($category !== 'all') {
    $where[] = "category = ?";
    $params[] = strtoupper($category);
}

$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Get logs
$sql = "SELECT * FROM system_logs {$whereClause} ORDER BY timestamp DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter
$categoriesStmt = $pdo->query("SELECT DISTINCT category FROM system_logs ORDER BY category");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Filters -->
<div class="filters">
    <div class="log-filter-group">
        <label>Level</label>
        <select id="levelFilter">
            <option value="all" <?= $level === 'all' ? 'selected' : '' ?>>All Levels</option>
            <option value="debug" <?= $level === 'debug' ? 'selected' : '' ?>>Debug</option>
            <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Error</option>
            <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Critical</option>
        </select>
    </div>

    <div class="log-filter-group">
        <label>Category</label>
        <select id="categoryFilter">
            <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="log-filter-group">
        <label>Limit</label>
        <select id="limitFilter">
            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
            <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
            <option value="500" <?= $limit === 500 ? 'selected' : '' ?>>500</option>
        </select>
    </div>

    <div class="action-buttons" style="margin-left: auto;">
        <button id="clearAllBtn" class="btn" style="background: #e74c3c; width: auto;">
            🗑️ Clear All Logs
        </button>
    </div>
</div>

<!-- Logs Table -->
<table class="log-table">
    <thead>
        <tr>
            <th style="width: 140px;">Date & Time</th>
            <th style="width: 80px;">Level</th>
            <th style="width: 100px;">Category</th>
            <th>Message</th>
            <th style="width: 200px;">Context</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                    No logs found
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($log['timestamp'])) ?></td>
                    <td>
                        <span class="log-level log-level-<?= $log['level'] ?>">
                            <?= $log['level'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['category']) ?></td>
                    <td><?= htmlspecialchars($log['message']) ?></td>
                    <td>
                        <?php if (!empty($log['context'])): ?>
                            <div class="log-context" title="Click to expand">
                                <?= htmlspecialchars($log['context']) ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 20px; text-align: center; color: #666;">
    Showing <?= count($logs) ?> log entries
</div>

<script>
$(document).ready(function() {
    // Apply filters
    function applyFilters() {
        const level = $('#levelFilter').val();
        const category = $('#categoryFilter').val();
        const limit = $('#limitFilter').val();
        
        window.location.href = '<?= BASE_URL ?>/maintenance/logs.php' +
            '?level=' + level +
            '&category=' + encodeURIComponent(category) +
            '&limit=' + limit;
    }

    $('#levelFilter, #categoryFilter, #limitFilter').on('change', applyFilters);

    // Clear all logs
    $('#clearAllBtn').on('click', function() {
        if (!confirm('⚠️ This will permanently delete ALL system logs. Are you sure?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text('Clearing...');

        $.ajax({
            url: '<?= BASE_URL ?>/maintenance/api/logs.php?action=clear_all',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('✓ All logs cleared successfully');
                    window.location.reload();
                } else {
                    alert('✗ ' + response.message);
                    btn.prop('disabled', false).text('🗑️ Clear All Logs');
                }
            },
            error: function() {
                alert('❌ Failed to clear logs');
                btn.prop('disabled', false).text('🗑️ Clear All Logs');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
