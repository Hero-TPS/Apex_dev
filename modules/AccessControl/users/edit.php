<?php
// modules/AccessControl/users/edit.php
$page_title    = 'Edit User';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$userId) {
    header('Location: ' . BASE_URL . '/modules/AccessControl/users/');
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email, is_active FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ' . BASE_URL . '/modules/AccessControl/users/');
    exit;
}

$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Users',          'url' => BASE_URL . '/modules/AccessControl/users/'],
    ['label' => 'Edit: ' . $user['username']],
]);
$page_path = '/modules/AccessControl/users/';
include ROOT_DIR . '/includes/header.php';

$allRoles    = $pdo->query("SELECT id, name, description FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$userRoleIds = getUserRoles($pdo, $userId);
$userRoleIds = array_map('intval', array_column($userRoleIds, 'id'));
?>

<div class="form-container">
    <h2>✏️ Edit User: <?= e($user['username']) ?></h2>

    <form id="editUserForm">
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">

        <div class="form-group">
            <label for="username">Username <span class="required">*</span></label>
            <input type="text" id="username" name="username" required
                   value="<?= e($user['username']) ?>" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" required
                   value="<?= e($user['email']) ?>" autocomplete="off">
        </div>

        <div class="form-group">
            <label for="password">New Password <small class="text-muted">(leave blank to keep current)</small></label>
            <input type="password" id="password" name="password"
                   placeholder="Minimum 8 characters" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm New Password</label>
            <input type="password" id="password_confirm" name="password_confirm"
                   placeholder="Re-enter new password" autocomplete="new-password">
        </div>

        <?php if (!empty($allRoles)): ?>
        <div class="form-group">
            <label>Roles</label>
            <div class="checkbox-group">
                <?php foreach ($allRoles as $role): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"
                        <?= in_array((int) $role['id'], $userRoleIds, true) ? 'checked' : '' ?>>
                    <strong><?= e($role['name']) ?></strong>
                    <?php if ($role['description']): ?>
                        <small class="text-muted"> — <?= e($role['description']) ?></small>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_active" value="1"
                    <?= $user['is_active'] ? 'checked' : '' ?>>
                <strong>Active</strong> <small class="text-muted"> — uncheck to disable this account</small>
            </label>
        </div>

        <button type="submit" class="btn" id="submitBtn">💾 Save Changes</button>
        <a href="<?= BASE_URL ?>/modules/AccessControl/users/" class="page-action-btn">Cancel</a>
    </form>

    <div id="editResult"></div>
</div>

<script>
$(document).ready(function () {
    $('#editUserForm').on('submit', function (e) {
        e.preventDefault();

        var password = $('#password').val();
        var confirm  = $('#password_confirm').val();

        if (password !== '' || confirm !== '') {
            if (password !== confirm) {
                $('#editResult').html('<div class="error-message">❌ Passwords do not match.</div>');
                return;
            }
            if (password.length < 8) {
                $('#editResult').html('<div class="error-message">❌ Password must be at least 8 characters.</div>');
                return;
            }
        }

        var btn = $('#submitBtn');
        btn.prop('disabled', true).text('Saving...');
        $('#editResult').html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: $(this).serialize() + '&action=update_user',
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('💾 Save Changes');
                if (res.success) {
                    $('#editResult').html('<div class="success-message">' + res.message + ' Redirecting...</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/AccessControl/users/';
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
