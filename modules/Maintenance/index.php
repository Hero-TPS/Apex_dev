<?php
$page_title = 'Maintenance';
$page_subtitle = 'Manage system data';
$show_breadcrumb = true;
$breadcrumb = ' > Maintenance';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';

// Fetch current data
$destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
$costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
$durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
?>

<style>
.maintenance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.maintenance-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.maintenance-card h3 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
}

.maintenance-card textarea {
    width: 100%;
    min-height: 200px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
}

.maintenance-card .helper-text {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.quick-links {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.quick-links h3 {
    margin-top: 0;
}

.quick-links a {
    display: inline-block;
    background: #3498db;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    text-decoration: none;
    margin-right: 10px;
    margin-bottom: 10px;
}

.quick-links a:hover {
    background: #2980b9;
}

#notification-area {
    margin-bottom: 20px;
}

.success-message {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #f5c6cb;
}
</style>

<div class="quick-links">
    <h3>📋 Quick Actions</h3>
    <a href="<?= BASE_URL ?>/modules/Maintenance/logs.php">📊 View System Logs</a>
    <a href="#" onclick="document.getElementById('cleanupForm').submit(); return false;">🗑️ Clean Old Logs</a>
</div>

<form id="cleanupForm" method="POST" action="logs.php" style="display: none;">
    <input type="hidden" name="action" value="cleanup">
    <input type="hidden" name="days" value="7">
</form>

<div id="notification-area"></div>

<form id="maintenanceForm">
    <div class="maintenance-grid">
        <!-- Destinations -->
        <div class="maintenance-card">
            <h3>📍 Destinations</h3>
            <textarea name="destinations" id="destinations" placeholder="One destination per line"><?php
                echo implode("\n", $destinations);
            ?></textarea>
            <div class="helper-text">One destination per line</div>
        </div>

        <!-- Costs -->
        <div class="maintenance-card">
            <h3>💰 Costs</h3>
            <textarea name="costs" id="costs" placeholder="One cost per line (numbers only)"><?php
                echo implode("\n", array_map(function($v) {
                    return number_format((float)$v, 2, '.', '');
                }, $costs));
            ?></textarea>
            <div class="helper-text">One cost per line (e.g., 150.00)</div>
        </div>

        <!-- Durations -->
        <div class="maintenance-card">
            <h3>⏱️ Durations (hours)</h3>
            <textarea name="durations" id="durations" placeholder="One duration per line (in hours)"><?php
                echo implode("\n", array_map(function($v) {
                    return number_format((float)$v, 1, '.', '');
                }, $durations));
            ?></textarea>
            <div class="helper-text">One duration per line (e.g., 1.5)</div>
        </div>
    </div>

    <button type="submit" class="btn" style="width: auto; padding: 12px 30px;">
        💾 Save All Changes
    </button>
</form>

<script>
$(document).ready(function() {
    $('#maintenanceForm').on('submit', function(e) {
        e.preventDefault();
        
        const notificationArea = $('#notification-area');
        notificationArea.html('<div class="info-message">Saving changes...</div>');
        
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Maintenance/api/index.php',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    notificationArea.html('<div class="success-message">✓ ' + response.message + '</div>');
                    setTimeout(() => notificationArea.html(''), 5000);
                } else {
                    notificationArea.html('<div class="error-message">✗ ' + response.message + '</div>');
                }
            },
            error: function() {
                notificationArea.html('<div class="error-message">✗ Failed to save changes. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>