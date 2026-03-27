<?php
// modules/AccessControl/roles/index.php
$page_title    = 'Roles';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Roles'],
]);
$page_path = '/modules/AccessControl/roles/';
include ROOT_DIR . '/includes/header.php';

$stmt = $pdo->query("
    SELECT r.id, r.name, r.description, r.created_at,
           COUNT(DISTINCT ur.user_id)  AS user_count,
           COUNT(DISTINCT rp.page_id) AS page_count
    FROM roles r
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    LEFT JOIN role_permissions rp ON rp.role_id = r.id
    GROUP BY r.id
    ORDER BY r.name ASC
");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-actions" style="margin-bottom: 16px;">
    <a href="<?= BASE_URL ?>/modules/AccessControl/roles/add.php" class="page-action-btn save">➕ Add Role</a>
</div>

<?php if (empty($roles)): ?>
    <div class="no-bookings">
        <h3>🏷️ No roles found</h3>
        <p><a href="<?= BASE_URL ?>/modules/AccessControl/roles/add.php" class="btn" style="width:auto;padding:10px 20px;">Add Your First Role</a></p>
    </div>
<?php else: ?>
<div id="notification-area"></div>

<table class="bookings-table">
    <thead>
        <tr>
            <th>Role Name</th>
            <th>Description</th>
            <th>Users</th>
            <th>Permitted Pages</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $role): ?>
        <tr id="role-row-<?= (int) $role['id'] ?>">
            <td data-label="Role Name"><strong><?= e($role['name']) ?></strong></td>
            <td data-label="Description"><?= e($role['description'] ?: '—') ?></td>
            <td data-label="Users"><?= (int) $role['user_count'] ?></td>
            <td data-label="Permitted Pages">
                <?php if ($role['name'] === 'Admin'): ?>
                    <em>All pages (superuser)</em>
                <?php else: ?>
                    <?= (int) $role['page_count'] ?>
                <?php endif; ?>
            </td>
            <td data-label="Actions">
                <div class="actions-container">
                    <a href="<?= BASE_URL ?>/modules/AccessControl/roles/edit.php?id=<?= (int) $role['id'] ?>"
                       class="action-btn edit-btn">Edit</a>
                    <?php if ($role['name'] !== 'Admin'): ?>
                    <button class="action-btn delete-btn"
                            data-id="<?= (int) $role['id'] ?>"
                            data-name="<?= e($role['name']) ?>">Delete</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <h3>Delete Role</h3>
        <p>Are you sure you want to delete <strong id="deleteRoleName"></strong>? This cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var modal = $('#deleteModal');
    var roleIdToDelete = null;

    $(document).on('click', '.delete-btn', function () {
        roleIdToDelete = $(this).data('id');
        $('#deleteRoleName').text($(this).data('name'));
        modal.css('display', 'flex');
    });

    $('#cancelDeleteBtn').on('click', function () {
        modal.hide();
        roleIdToDelete = null;
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!roleIdToDelete) return;

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: { action: 'delete_role', id: roleIdToDelete },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#role-row-' + roleIdToDelete).fadeOut(function () { $(this).remove(); });
                    showNotification('✓ ' + res.message, 'success');
                } else {
                    showNotification('✗ ' + res.message, 'error');
                }
            },
            error: function () { showNotification('❌ Request failed', 'error'); },
            complete: function () { modal.hide(); roleIdToDelete = null; }
        });
    });

    function showNotification(msg, type) {
        var cls = type === 'success' ? 'success-message' : 'error-message';
        var el = $('<div class="' + cls + '">' + msg + '</div>');
        $('#notification-area').html(el);
        setTimeout(function () { el.fadeOut(function () { $(this).remove(); }); }, 5000);
    }
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
