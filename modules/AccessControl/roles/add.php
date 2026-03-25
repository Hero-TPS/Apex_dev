<?php
// modules/AccessControl/roles/add.php
$page_title    = 'Add Role';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Roles',          'url' => BASE_URL . '/modules/AccessControl/roles/'],
    ['label' => 'Add Role'],
]);
include ROOT_DIR . '/includes/header.php';

$allPages = $pdo->query("SELECT id, name, path, description FROM pages ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="form-container">
    <h2>🏷️ Add New Role</h2>

    <form id="addRoleForm">
        <div class="form-group">
            <label for="name">Role Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required placeholder="e.g. Manager">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description"
                   placeholder="Brief description of this role">
        </div>

        <?php if (!empty($allPages)): ?>
        <div class="form-group">
            <label>Page Permissions <small class="text-muted">(select which pages this role can access)</small></label>
            <div class="checkbox-group">
                <?php foreach ($allPages as $page): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="pages[]" value="<?= (int) $page['id'] ?>">
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

        <button type="submit" class="btn" id="submitBtn">🏷️ Add Role</button>
    </form>

    <div id="addResult"></div>
</div>

<script>
$(document).ready(function () {
    $('#addRoleForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#submitBtn');
        btn.prop('disabled', true).text('Saving...');
        $('#addResult').html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: $(this).serialize() + '&action=add_role',
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('🏷️ Add Role');
                if (res.success) {
                    $('#addResult').html('<div class="success-message">' + res.message + ' Redirecting...</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/AccessControl/roles/';
                    }, 1500);
                } else {
                    $('#addResult').html('<div class="error-message">❌ ' + res.message + '</div>');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('🏷️ Add Role');
                $('#addResult').html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
