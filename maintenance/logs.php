<?php
$page_title = 'System Logs';
$page_subtitle = 'Activity & Error Logs';
$show_breadcrumb = true;
$breadcrumb = ' > Maintenance > Logs';

require_once __DIR__ . '/../config.php';
include ROOT_DIR . '/includes/header.php';

// Get filter parameters
$level = $_GET['level'] ?? 'all';
$module = $_GET['module'] ?? 'all';
$limit = intval($_GET['limit'] ?? 100);

// Build query
$where = [];
$params = [];

if ($level !== 'all') {
    $where[] = "level = ?";
    $params[] = strtoupper($level);
}

if ($module !== 'all') {
    $where[] = "module = ?";
    $params[] = strtoupper($module);
}

$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Get logs
$sql = "SELECT * FROM logs {$whereClause} ORDER BY created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique modules for filter
$modulesStmt = $pdo->query("SELECT DISTINCT module FROM logs ORDER BY module");
$modules = $modulesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
.filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    color: #666;
    font-weight: bold;
}

.filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.log-table {
    width: 100%;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.log-table th {
    background: #2c3e50;
    color: white;
    padding: 12px;
    text-align: left;
    font-size: 13px;
}

.log-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

.log-table tr:hover {
    background: #f8f9fa;
}

.log-level {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}

.log-level-INFO { background: #d4edda; color: #155724; }
.log-level-WARNING { background: #fff3cd; color: #856404; }
.log-level-ERROR { background: #f8d7da; color: #721c24; }
.log-level-CRITICAL { background: #721c24; color: white; }

.log-context {
    font-family: monospace;
    font-size: 11px;
    color: #666;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}

.log-context:hover {
    white-space: normal;
    word-break: break-all;
}

.action-buttons {
    display: flex;
    gap: 10px;
}
</style>

<!-- Filters -->
<div class="filters">
    <div class="filter-group">
        <label>Level</label>
        <select id="levelFilter">
            <option value="all" <?= $level === 'all' ? 'selected' : '' ?>>All Levels</option>
            <option value="info" <?= $level === 'info' ? 'selected' : '' ?>>Info</option>
            <option value="warning" <?= $level === 'warning' ? 'selected' : '' ?>>Warning</option>
            <option value="error" <?= $level === 'error' ? 'selected' : '' ?>>Error</option>
            <option value="critical" <?= $level === 'critical' ? 'selected' : '' ?>>Critical</option>
        </select>
    </div>

    <div class="filter-group">
        <label>Module</label>
        <select id="moduleFilter">
            <option value="all" <?= $module === 'all' ? 'selected' : '' ?>>All Modules</option>
            <?php foreach ($modules as $mod): ?>
                <option value="<?= htmlspecialchars($mod) ?>" <?= $module === $mod ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mod) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
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
            <th style="width: 100px;">Module</th>
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
                    <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                    <td>
                        <span class="log-level log-level-<?= $log['level'] ?>">
                            <?= $log['level'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['module']) ?></td>
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
        const module = $('#moduleFilter').val();
        const limit = $('#limitFilter').val();
        
        window.location.href = '<?= BASE_URL ?>/Maintenance/logs.php' +
            '?level=' + level +
            '&module=' + encodeURIComponent(module) +
            '&limit=' + limit;
    }

    $('#levelFilter, #moduleFilter, #limitFilter').on('change', applyFilters);

    // Clear all logs
    $('#clearAllBtn').on('click', function() {
        if (!confirm('⚠️ This will permanently delete ALL system logs. Are you sure?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text('Clearing...');

        $.ajax({
            url: '<?= BASE_URL ?>/Maintenance/api/logs.php?action=clear_all',
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