
<?php
$page_title = 'System Logs';
$page_subtitle = 'View system activity and errors';
$show_breadcrumb = true;
$breadcrumb = ' > Maintenance > System Logs';

require_once __DIR__ . '/../config.php';
include ROOT_DIR . '/includes/header.php';

// Handle cleanup action
if (isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $days = intval($_POST['days'] ?? 7);
    $deleted = cleanOldLogs($days);
    $success_message = "Deleted $deleted log entries older than $days days.";
}

// Get filter parameters
$level_filter = $_GET['level'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$days_filter = intval($_GET['days'] ?? 7);

// Build query
$sql = "SELECT * FROM system_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params = [$days_filter];

if ($level_filter !== 'all') {
    $sql .= " AND level = ?";
    $params[] = $level_filter;
}

if ($category_filter !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $sql .= " AND message LIKE ?";
    $params[] = '%' . $search . '%';
}

$sql .= " ORDER BY timestamp DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter
$categories = $pdo->query("
    SELECT DISTINCT category 
    FROM system_logs 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$stats = getLogStats($days_filter);
?>

<?php if (isset($success_message)): ?>
    <div class="success-message">✓ <?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<!-- Statistics -->
<?php if (!empty($stats)): ?>
<div class="stats-grid">
    <?php
    $total_logs = array_sum(array_column($stats, 'count'));
    $error_count = array_sum(array_column(array_filter($stats, fn($s) => in_array($s['level'], ['ERROR', 'CRITICAL'])), 'count'));
    ?>
    <div class="stat-card">
        <h4>Total Logs (<?= $days_filter ?> days)</h4>
        <div class="number"><?= number_format($total_logs) ?></div>
    </div>
    <div class="stat-card" style="border-left-color: #e74c3c;">
        <h4>Errors & Critical</h4>
        <div class="number"><?= number_format($error_count) ?></div>
    </div>
    <div class="stat-card" style="border-left-color: #f39c12;">
        <h4>Most Active Category</h4>
        <div class="number" style="font-size: 16px;">
            <?= !empty($stats) ? htmlspecialchars($stats[0]['category']) : 'N/A' ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="logs-filters">
    <form method="GET" action="">
        <div class="filter-row">
            <div class="filter-group">
                <label>Time Period</label>
                <select name="days">
                    <option value="1" <?= $days_filter == 1 ? 'selected' : '' ?>>Last 24 hours</option>
                    <option value="3" <?= $days_filter == 3 ? 'selected' : '' ?>>Last 3 days</option>
                    <option value="7" <?= $days_filter == 7 ? 'selected' : '' ?>>Last 7 days</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Level</label>
                <select name="level">
                    <option value="all" <?= $level_filter === 'all' ? 'selected' : '' ?>>All Levels</option>
                    <option value="DEBUG" <?= $level_filter === 'DEBUG' ? 'selected' : '' ?>>Debug</option>
                    <option value="INFO" <?= $level_filter === 'INFO' ? 'selected' : '' ?>>Info</option>
                    <option value="WARNING" <?= $level_filter === 'WARNING' ? 'selected' : '' ?>>Warning</option>
                    <option value="ERROR" <?= $level_filter === 'ERROR' ? 'selected' : '' ?>>Error</option>
                    <option value="CRITICAL" <?= $level_filter === 'CRITICAL' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search message..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="filter-group" style="flex: 0;">
                <button type="submit" class="btn">Apply Filters</button>
            </div>
        </div>
    </form>
</div>

<!-- Actions -->
<div class="actions-bar">
    <form method="POST" action="" onsubmit="return confirm('Delete logs older than 7 days?');" style="display: inline;">
        <input type="hidden" name="action" value="cleanup">
        <input type="hidden" name="days" value="7">
        <button type="submit" class="btn">🗑️ Clean Logs > 7 Days</button>
    </form>
    
    <a href="?export=csv&<?= http_build_query($_GET) ?>" class="btn">📥 Export CSV</a>
</div>

<!-- Logs Table -->
<table class="logs-table">
    <thead>
        <tr>
            <th style="width: 140px;">Timestamp</th>
            <th style="width: 90px;">Level</th>
            <th style="width: 120px;">Category</th>
            <th>Message</th>
            <th style="width: 100px;">IP</th>
            <th style="width: 80px;">Context</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px;">
                    No logs found for the selected filters.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                    <td>
                        <span class="level-badge level-<?= $log['level'] ?>">
                            <?= $log['level'] ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['category']) ?></td>
                    <td><?= htmlspecialchars($log['message']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($log['context'])): ?>
                            <button class="context-btn" onclick="showContext(<?= $log['id'] ?>)">View</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- Context Modal -->
<div id="contextModal" class="context-modal" onclick="if(event.target===this) this.style.display='none'">
    <div class="context-content">
        <h3>Log Context <span style="float: right; cursor: pointer;" onclick="document.getElementById('contextModal').style.display='none'">✕</span></h3>
        <div id="contextData"></div>
    </div>
</div>

<script>
const contexts = <?= json_encode(array_column($logs, 'context', 'id')) ?>;

function showContext(id) {
    const modal = document.getElementById('contextModal');
    const dataDiv = document.getElementById('contextData');
    
    if (contexts[id]) {
        try {
            const parsed = JSON.parse(contexts[id]);
            dataDiv.innerHTML = '<pre>' + JSON.stringify(parsed, null, 2) + '</pre>';
        } catch(e) {
            dataDiv.innerHTML = '<pre>' + contexts[id] + '</pre>';
        }
    } else {
        dataDiv.innerHTML = '<p>No context data</p>';
    }
    
    modal.style.display = 'block';
}
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>