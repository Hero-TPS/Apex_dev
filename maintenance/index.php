<?php
// Maintenance/index.php

$page_title = 'Data Maintenance';
$page_subtitle = 'General Settings';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Maintenance']]);
$page_path = '/maintenance/';
include ROOT_DIR . '/includes/header.php';


// Fetch dropdown data
$current_destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
$current_costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
$current_durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
$uber_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');

$destinations_text = implode("\n", $current_destinations);
$costs_text = implode("\n", $current_costs);
$durations_text = implode("\n", $current_durations);
$uber_reasons_text = implode("\n", $uber_reasons);

// Count overdue bookings for the cleanup preview
$today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');
$overdueStmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE trip_date < ? AND status != 'completed'
");
$overdueStmt->execute([$today]);
$overdueCount = (int) $overdueStmt->fetchColumn();
?>

<!-- Maintenance Form -->
<div class="form-container">
    <h2>🔧 Dropdown Lists</h2>
    <form id="maintenanceForm">
        <div class="form-group">
            <label for="destinations">Destinations</label>
            <textarea id="destinations" name="destinations" rows="8"
                placeholder="Enter one destination per line"><?php echo htmlspecialchars($destinations_text); ?></textarea>
            <small>Used in booking pickup/destination dropdowns</small>
        </div>

        <div class="form-group">
            <label for="costs">Costs (ZAR)</label>
            <textarea id="costs" name="costs" rows="8"
                placeholder="Enter one cost per line, e.g.&#10;250&#10;300.50&#10;400"><?php echo htmlspecialchars($costs_text); ?></textarea>
            <small>Enter amounts without 'R' or commas</small>
        </div>

        <div class="form-group">
            <label for="durations">Durations (hours)</label>
            <textarea id="durations" name="durations" rows="5"
                placeholder="Enter one duration per line, e.g.&#10;0.5 (30 mins)&#10;1&#10;1.5"><?php echo htmlspecialchars($durations_text); ?></textarea>
            <small>Use decimal format: 0.5 = 30 mins</small>
        </div>

        <div class="form-group">
            <label for="uber_cost_reasons">Uber Additional Cost Reasons</label>
            <textarea id="uber_cost_reasons" name="uber_cost_reasons" rows="8"
                placeholder="Enter one reason per line"><?php echo htmlspecialchars($uber_reasons_text); ?></textarea>
            <small>Reasons for additional Uber expenses</small>
        </div>

        <button type="submit" class="page-action-btn save" id="submitBtn">
            💾 Save Changes
        </button>
    </form>
    <div id="listsResult"></div>
</div>

<!-- System Variables -->
<div class="form-container">
    <h2>⚙️ System Variables</h2>
    <form id="variablesForm">
        <?php foreach (SYSTEM_VARIABLES as $name => $def):
            $stmt = $pdo->prepare("SELECT value FROM system_variables WHERE name = ?");
            $stmt->execute([$name]);
            $saved = $stmt->fetchColumn();
            $value = ($saved !== false) ? $saved : $def['default'];
        ?>
            <div class="form-group">
                <label><?= htmlspecialchars($def['label']) ?></label>
                <?php if ($def['type'] === 'number'): ?>
                    <input type="number" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" step="0.01" min="0" required>
                <?php else: ?>
                    <input type="text" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" required>
                <?php endif; ?>
                <small>Default: <?= htmlspecialchars($def['default']) ?></small>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="page-action-btn save" id="varSubmitBtn">
            💾 Save Variables
        </button>
    </form>
    <div id="variablesResult"></div>
</div>

<!-- Cleanup Tools -->
<div class="form-container">
    <h2>🧹 Cleanup Tools</h2>

    <div class="form-group">
        <label>Mark Past Overdue Bookings as Completed</label>
        <p style="margin: 0.25rem 0 0.75rem;">
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
        // Handle dropdown lists form
        $('#maintenanceForm').on('submit', function (e) {
            e.preventDefault();
            var submitBtn = $('#submitBtn');
            var result = $('#listsResult');

            submitBtn.prop('disabled', true).text('Saving...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=update_lists',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    submitBtn.prop('disabled', false).text('💾 Save Changes');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }

                    setTimeout(function () {
                        result.fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function (xhr, status, error) {
                    submitBtn.prop('disabled', false).text('💾 Save Changes');
                    result.html('<div class="error-message">❌ Failed to save. Please try again.</div>');
                    console.error('Error:', error, xhr.responseText);
                }
            });
        });

        // Handle system variables form
        $('#variablesForm').on('submit', function (e) {
            e.preventDefault();
            var submitBtn = $('#varSubmitBtn');
            var result = $('#variablesResult');

            submitBtn.prop('disabled', true).text('Saving...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=update_variables',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    submitBtn.prop('disabled', false).text('💾 Save Variables');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }

                    setTimeout(function () {
                        result.fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function (xhr, status, error) {
                    submitBtn.prop('disabled', false).text('💾 Save Variables');
                    result.html('<div class="error-message">❌ Failed to save. Please try again.</div>');
                    console.error('Error:', error, xhr.responseText);
                }
            });
        });

        // Handle add variable form
        $('#addVariableForm').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#addVarBtn');
            var result = $('#addVariableResult');

            btn.prop('disabled', true).text('Adding...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=add_variable',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    btn.prop('disabled', false).text('➕ Add Variable');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                        $('#addVariableForm')[0].reset();
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function (xhr, status, error) {
                    btn.prop('disabled', false).text('➕ Add Variable');
                    result.html('<div class="error-message">❌ Failed to add variable.</div>');
                    console.error('Error:', error, xhr.responseText);
                }
            });
        });

        // Handle mark overdue as done
        $('#markOverdueBtn').on('click', function () {
            if (!confirm('Mark all past unconfirmed bookings as completed? This cannot be undone.')) return;

            var btn = $(this);
            var result = $('#cleanupResult');

            btn.prop('disabled', true).text('Working...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=mark_overdue_complete',
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