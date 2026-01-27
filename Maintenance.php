<?php
// Maintenance.php

$page_title = 'Data Maintenance';
$page_subtitle = 'General Settings';
$show_breadcrumb = true;

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';


// Fetch dropdown data
$current_destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
$current_costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
$current_durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');

$destinations_text = implode("\n", $current_destinations);
$costs_text = implode("\n", $current_costs);
$durations_text = implode("\n", $current_durations);
?>

<!-- Maintenance Form -->
<div class="form-container">
    <h2>🔧 Dropdown Lists</h2>
    <form id="maintenanceForm">
        <div class="form-group">
            <label for="destinations">Destinations</label>
            <textarea 
                id="destinations" 
                name="destinations" 
                rows="8" 
                placeholder="Enter one destination per line"><?php echo htmlspecialchars($destinations_text); ?></textarea>
            <small>Used in booking pickup/destination dropdowns</small>
        </div>

        <div class="form-group">
            <label for="costs">Costs (ZAR)</label>
            <textarea 
                id="costs" 
                name="costs" 
                rows="8" 
                placeholder="Enter one cost per line, e.g.&#10;250&#10;300.50&#10;400"><?php echo htmlspecialchars($costs_text); ?></textarea>
            <small>Enter amounts without 'R' or commas</small>
        </div>

        <div class="form-group">
            <label for="durations">Durations (hours)</label>
            <textarea 
                id="durations" 
                name="durations" 
                rows="5" 
                placeholder="Enter one duration per line, e.g.&#10;0.5 (30 mins)&#10;1&#10;1.5"><?php echo htmlspecialchars($durations_text); ?></textarea>
            <small>Use decimal format: 0.5 = 30 mins</small>
        </div>

        <button type="submit" class="page-action-btn save" id="submitBtn">
            💾 Save Changes
        </button>
    </form>
</div>

<!-- System Variables -->
<div class="form-container variables-section">
    <h2>⚙️ System Variables</h2>
    <form id="variablesForm">
        <?php
        // Fetch existing system variables
        $stmt = $pdo->query("SELECT * FROM system_variables ORDER BY label");
        $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($variables as $row):
        ?>
            <div class="form-group">
                <label for="var_<?= htmlspecialchars($row['name']) ?>"><?= htmlspecialchars($row['label']) ?></label>
                <?php if ($row['type'] === 'number'): ?>
                    <input 
                        type="number" 
                        id="var_<?= htmlspecialchars($row['name']) ?>" 
                        name="variables[<?= htmlspecialchars($row['name']) ?>]" 
                        value="<?= htmlspecialchars($row['value']) ?>"
                        step="0.01"
                        min="0"
                        required>
                <?php else: ?>
                    <input 
                        type="text" 
                        id="var_<?= htmlspecialchars($row['name']) ?>" 
                        name="variables[<?= htmlspecialchars($row['name']) ?>]" 
                        value="<?= htmlspecialchars($row['value']) ?>"
                        required>
                <?php endif; ?>
                <button 
                    type="button" 
                    class="page-action-btn delete"
                    data-name="<?= htmlspecialchars($row['name']) ?>"
                    data-label="<?= htmlspecialchars($row['label']) ?>"
                    style="margin-top: 8px; width: auto;"
                    title="Delete this variable">
                    🗑️ Delete
                </button>
            </div>
        <?php endforeach; ?>

        <div id="newVariablesContainer"></div>

        <button type="submit" class="page-action-btn save" id="submitVariablesBtn">
            💾 Save Variables
        </button>
    </form>

    <!-- Add New Variable Button -->
    <div class="form-group" style="margin-top: 20px;">
        <button type="button" id="addVariableBtn" class="page-action-btn rebook">
            ➕ Add New Variable
        </button>
    </div>
</div>

<!-- Hidden template for new variables -->
<div id="variableTemplate" style="display:none;">
    <div class="form-group new-variable">
        <label>Label (User-Friendly Name)</label>
        <input type="text" name="new_variables[__INDEX__][label]" placeholder="e.g., Car Rental Price" required>
        <label>Machine Name (no spaces)</label>
        <input type="text" name="new_variables[__INDEX__][name]" placeholder="e.g., car_rental_price" required>
        <label>Value</label>
        <input type="text" name="new_variables[__INDEX__][value]" placeholder="e.g., 150.00" required>
        <label>Type</label>
        <select name="new_variables[__INDEX__][type]">
            <option value="text">Text</option>
            <option value="number">Number</option>
        </select>
        <button type="button" class="page-action-btn delete">🗑️ Remove</button>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Are you sure?</h3>
        <p id="deleteMessage">This will permanently delete the variable. This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<div id="notification-area"></div>

<script>
$(document).ready(function () {
    // Dropdown lists
    $('#maintenanceForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#submitBtn').text('Saving...').prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: 'api/maintenance.php?action=update_lists',
            // ✅ 'data' is explicitly present
             data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function () {
                $('#notification-area').html('<div class="error-message">❌ Failed to save.</div>');
                btn.text('💾 Save Changes').prop('disabled', false);
            }
        });
    });

    // System Variables
    $('#variablesForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#submitVariablesBtn').text('Saving...').prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: 'api/maintenance.php?action=update_variables',
            // ✅ 'data' is explicitly present
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    location.reload();
                }
            },
            error: function () {
                $('#notification-area').html('<div class="error-message">❌ Failed to save variables.</div>');
                btn.text('💾 Save Variables').prop('disabled', false);
            }
        });
    });

    // Add new variable
    let newVariableIndex = 0;
    $('#addVariableBtn').on('click', function() {
        const template = $('#variableTemplate').html()
            .replace(/__INDEX__/g, newVariableIndex);
        $('#newVariablesContainer').append(template);
        newVariableIndex++;
    });

    // Remove new variable
    $(document).on('click', '.delete-new-variable', function() {
        $(this).closest('.new-variable').remove();
    });

    // Delete system variable
    let variableToDelete = null;
    $(document).on('click', '.delete-variable-btn', function() {
        variableToDelete = {
            name: $(this).data('name'),
            label: $(this).data('label')
        };
        $('#deleteMessage').text(`Delete variable "${variableToDelete.label}"? This cannot be undone.`);
        $('#deleteConfirmationModal').show();
    });

    // Cancel delete
    $('#cancelDeleteBtn').on('click', function() {
        $('#deleteConfirmationModal').hide();
        variableToDelete = null;
    });

    // Confirm delete
    $('#confirmDeleteBtn').on('click', function() {
        if (!variableToDelete) return;
        var btn = $(this).text('Deleting...').prop('disabled', true);

        $.ajax({
            url: 'api/maintenance.php?action=delete_variable',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {
                name: variableToDelete.name
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    $('#notification-area').html('<div class="error-message">' + res.message + '</div>');
                }
            },
            error: function() {
                $('#notification-area').html('<div class="error-message">❌ Failed to delete.</div>');
            },
            complete: function() {
                btn.text('Yes, Delete').prop('disabled', false);
                $('#deleteConfirmationModal').hide();
                variableToDelete = null;
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>