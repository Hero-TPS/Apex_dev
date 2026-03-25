<?php
// modules/AccessControl/roles/edit.php
$page_title    = 'Edit Role';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

$roleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$roleId) {
    header('Location: ' . BASE_URL . '/modules/AccessControl/roles/');
    exit;
}

$stmt = $pdo->prepare("SELECT id, name, description FROM roles WHERE id = ?");
$stmt->execute([$roleId]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    header('Location: ' . BASE_URL . '/modules/AccessControl/roles/');
    exit;
}

$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Roles',          'url' => BASE_URL . '/modules/AccessControl/roles/'],
    ['label' => 'Edit: ' . $role['name']],
]);
include ROOT_DIR . '/includes/header.php';

$allPages        = $pdo->query("SELECT id, name, path, description FROM pages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$permittedPageIds = getRolePermissions($pdo, $roleId);
$permittedPageIds = array_map('intval', $permittedPageIds);

$isAdmin = $role['name'] === 'Admin';
?>

<div class="form-container">
    <h2>✏️ Edit Role: <?= e($role['name']) ?></h2>

    <?php if ($isAdmin): ?>
        <div class="info-message">ℹ️ The <strong>Admin</strong> role is a superuser role and always has access to all pages. Permissions cannot be restricted for this role.</div>
    <?php endif; ?>

    <form id="editRoleForm">
        <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">

        <div class="form-group">
            <label for="name">Role Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required
                   value="<?= e($role['name']) ?>"
                   <?= $isAdmin ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description"
                   value="<?= e($role['description']) ?>"
                   placeholder="Brief description of this role">
        </div>

        <?php if (!$isAdmin && !empty($allPages)): ?>
        <div class="form-group">
            <label>Page Permissions <small class="text-muted">(select which pages this role can access)</small></label>
            <div style="margin-bottom: 8px;">
                <button type="button" id="selectAllPages" class="page-action-btn toggle" style="font-size:0.85em;padding:4px 10px;">Select All</button>
                <button type="button" id="deselectAllPages" class="page-action-btn" style="font-size:0.85em;padding:4px 10px;">Deselect All</button>
            </div>
            <div class="checkbox-group">
                <?php foreach ($allPages as $page): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="pages[]" value="<?= (int) $page['id'] ?>"
                        <?= in_array((int) $page['id'], $permittedPageIds, true) ? 'checked' : '' ?>>
                    <strong><?= e($page['name']) ?></strong>
                    <small class="text-muted"> — <?= e($page['path']) ?></small>
                    <?php if ($page['description']): ?>
                        <small class="text-muted">(<?= e($page['description']) ?>)</small>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn" id="submitBtn">💾 Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/AccessControl/roles/" class="page-action-btn">Cancel</a>
    </form>

    <div id="editResult"></div>
</div>

<script>
$(document).ready(function () {
    $('#selectAllPages').on('click', function () {
        $('input[name="pages[]"]').prop('checked', true);
    });
    $('#deselectAllPages').on('click', function () {
        $('input[name="pages[]"]').prop('checked', false);
    });

    $('#editRoleForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#submitBtn');
        btn.prop('disabled', true).text('Saving...');
        $('#editResult').html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: $(this).serialize() + '&action=update_role',
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('💾 Save Changes');
                if (res.success) {
                    $('#editResult').html('<div class="success-message">' + res.message + ' Redirecting...</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/AccessControl/roles/';
                    }, 1500);
                } else {
                    $('#editResult').html('<div class="error-message">❌ ' + res.message + '</div>');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('💾 Save Changes');
                $('#editResult').html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
