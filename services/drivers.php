<?php
// services/drivers.php

$page_title = 'Drivers';
$page_subtitle = 'Drivers';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'Drivers'],
]);
include ROOT_DIR . '/includes/header.php';

// Fetch all drivers (including inactive) for management
$driversStmt = $pdo->query("SELECT id, name, phone, active FROM drivers ORDER BY name ASC");
$allDrivers = $driversStmt->fetchAll(PDO::FETCH_ASSOC);
?>

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
                url: '<?= BASE_URL ?>/services/api/index.php?action=add_driver',
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
                url: '<?= BASE_URL ?>/services/api/index.php?action=update_driver',
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
                url: '<?= BASE_URL ?>/services/api/index.php?action=delete_driver',
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
