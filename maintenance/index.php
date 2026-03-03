<?php
// Maintenance/index.php

$page_title = 'Data Maintenance';
$page_subtitle = 'General Settings';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
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
                        // Reload page after short delay so new var appears in the edit list
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
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>