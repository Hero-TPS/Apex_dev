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
$page_path = '/modules/AccessControl/roles/';
include ROOT_DIR . '/includes/header.php';

// Exclude virtual 'manage' rows — those are DB-only; individual page grants are used in the UI
$allPages        = $pdo->query("SELECT id, name, path, module, operation, description FROM pages WHERE operation != 'manage' ORDER BY FIELD(operation,'view','create','edit','delete'), name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
            <label>Permissions</label>
            <p class="text-muted" style="margin-top:0;font-size:0.9em;">Select the pages this role may access.</p>
            <div style="margin-bottom: 10px;">
                <button type="button" id="selectAllPages" class="page-action-btn toggle" style="font-size:0.85em;padding:4px 10px;">✅ Grant All</button>
                <button type="button" id="deselectAllPages" class="page-action-btn" style="font-size:0.85em;padding:4px 10px;">❌ Revoke All</button>
            </div>

            <table class="permissions-grid" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="text-align:center;padding:8px 6px;border-bottom:2px solid #ddd;width:90px;">Operation</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #ddd;">Page</th>
                        <th style="text-align:center;padding:8px 6px;border-bottom:2px solid #ddd;width:70px;">Grant</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allPages as $page):
                        $opBadge = [
                            'view'   => '<span style="background:#17a2b8;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">view</span>',
                            'create' => '<span style="background:#28a745;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">create</span>',
                            'edit'   => '<span style="background:#ffc107;color:#212529;padding:2px 7px;border-radius:4px;font-size:0.78em;">edit</span>',
                            'delete' => '<span style="background:#dc3545;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">delete</span>',
                        ][$page['operation']] ?? e($page['operation']);
                        $isChecked = in_array((int) $page['id'], $permittedPageIds, true);
                ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="text-align:center;padding:8px 6px;"><?= $opBadge ?></td>
                        <td style="padding:8px 12px;">
                            <strong><?= e($page['name']) ?></strong>
                            <?php if ($page['description']): ?>
                            <br><small class="text-muted"><?= e($page['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;padding:8px 6px;">
                            <input type="checkbox" name="pages[]"
                                   value="<?= (int) $page['id'] ?>"
                                   <?= $isChecked ? 'checked' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
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
