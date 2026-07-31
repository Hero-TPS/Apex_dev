<?php
// Maintenance/index.php

$page_title = 'Data Maintenance';
$page_subtitle = 'General Settings';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Maintenance']]);
include ROOT_DIR . '/includes/header.php';

// Fetch dropdown data
$current_destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
$current_costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
$current_durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
$uber_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');

// Fetch all drivers (including inactive) for management
$driversStmt = $pdo->query("SELECT id, name, phone, active FROM drivers ORDER BY name ASC");
$allDrivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                <?php elseif ($def['type'] === 'textarea'): ?>
                    <textarea name="variables[<?= htmlspecialchars($name) ?>]" rows="12"
                        style="width: 100%; font-family: monospace;" required><?= htmlspecialchars($value) ?></textarea>
                    <small>Placeholders like <code>{{car_rental}}</code> are filled in automatically — keep the token names intact.</small>
                <?php else: ?>
                    <input type="text" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" required>
                    <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                <?php endif; ?>
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

<!-- Drivers Management -->
<div class="form-container">
    <h2>🚗 Drivers</h2>

    <?php if (!empty($allDrivers)): ?>
    <table class="bookings-table drivers-table">
        <thead>
            <tr>
                <th class="driver-th">Name</th>
                <th class="driver-th">Phone</th>
                <th class="driver-td-center">Active</th>
                <th class="driver-td-center">Actions</th>
            </tr>
        </thead>
        <tbody id="driversTableBody">
            <?php foreach ($allDrivers as $driver): ?>
            <tr id="driver-row-<?= (int) $driver['id'] ?>">
                <td class="driver-td"><?= htmlspecialchars($driver['name']) ?></td>
                <td class="driver-td"><?= htmlspecialchars($driver['phone']) ?></td>
                <td class="driver-td-center">
                    <?= $driver['active'] ? '✅' : '❌' ?>
                </td>
                <td class="driver-td-center">
                    <button class="page-action-btn edit edit-driver-btn driver-action-btn"
                        data-id="<?= (int) $driver['id'] ?>"
                        data-name="<?= htmlspecialchars($driver['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($driver['phone'], ENT_QUOTES) ?>"
                        data-active="<?= (int) $driver['active'] ?>">
                        ✏️ Edit
                    </button>
                    <button class="page-action-btn delete delete-driver-btn driver-action-btn"
                        data-id="<?= (int) $driver['id'] ?>"
                        data-name="<?= htmlspecialchars($driver['name'], ENT_QUOTES) ?>">
                        🗑️ Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty-state-small">No drivers added yet.</p>
    <?php endif; ?>

    <h3>➕ Add Driver</h3>
    <form id="addDriverForm">
        <div class="form-row">
            <div class="form-group">
                <label for="newDriverName">Name <span class="required">*</span></label>
                <input type="text" id="newDriverName" name="name" placeholder="Driver name" required>
            </div>
            <div class="form-group">
                <label for="newDriverPhone">Phone</label>
                <input type="tel" id="newDriverPhone" name="phone" placeholder="e.g. 0821234567">
            </div>
        </div>
        <button type="submit" class="page-action-btn save" id="addDriverBtn">➕ Add Driver</button>
    </form>
    <div id="addDriverResult"></div>
</div>

<!-- Edit Driver Modal -->
<div id="editDriverModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <h3>✏️ Edit Driver</h3>
        <form id="editDriverForm">
            <input type="hidden" id="editDriverId" name="id">
            <div class="form-group">
                <label for="editDriverName">Name <span class="required">*</span></label>
                <input type="text" id="editDriverName" name="name" required>
            </div>
            <div class="form-group">
                <label for="editDriverPhone">Phone</label>
                <input type="tel" id="editDriverPhone" name="phone">
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="editDriverActive" name="active" value="1">
                    Active
                </label>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="modal-btn confirm-btn">💾 Save</button>
                <button type="button" id="cancelEditDriverBtn" class="modal-btn cancel-btn">Cancel</button>
            </div>
        </form>
        <div id="editDriverResult" class="driver-edit-result"></div>
    </div>
</div>

<script>
    // Drivers management
    $(document).ready(function () {

        // Add driver
        $('#addDriverForm').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#addDriverBtn');
            var result = $('#addDriverResult');
            btn.prop('disabled', true).text('Adding...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=add_driver',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    btn.prop('disabled', false).text('➕ Add Driver');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                        $('#addDriverForm')[0].reset();
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function () {
                    btn.prop('disabled', false).text('➕ Add Driver');
                    result.html('<div class="error-message">❌ Request failed.</div>');
                }
            });
        });

        // Open edit modal
        $(document).on('click', '.edit-driver-btn', function () {
            var btn = $(this);
            $('#editDriverId').val(btn.data('id'));
            $('#editDriverName').val(btn.data('name'));
            $('#editDriverPhone').val(btn.data('phone'));
            $('#editDriverActive').prop('checked', btn.data('active') == 1);
            $('#editDriverResult').html('');
            $('#editDriverModal').show();
        });

        $('#cancelEditDriverBtn').on('click', function () {
            $('#editDriverModal').hide();
        });

        // Save edit
        $('#editDriverForm').on('submit', function (e) {
            e.preventDefault();
            var result = $('#editDriverResult');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=update_driver',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }
                },
                error: function () {
                    result.html('<div class="error-message">❌ Request failed.</div>');
                }
            });
        });

        // Delete driver
        $(document).on('click', '.delete-driver-btn', function () {
            var driverName = $(this).data('name');
            var driverId   = $(this).data('id');
            if (!confirm('Delete driver "' + driverName + '"? This cannot be undone.')) return;

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/maintenance/api/index.php?action=delete_driver',
                data: { id: driverId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#driver-row-' + driverId).fadeOut(300, function () { $(this).remove(); });
                    } else {
                        alert('❌ ' + response.message);
                    }
                },
                error: function () {
                    alert('❌ Delete request failed.');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
