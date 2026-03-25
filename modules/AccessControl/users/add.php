<?php
// modules/AccessControl/users/add.php
$page_title    = 'Add User';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Users',          'url' => BASE_URL . '/modules/AccessControl/users/'],
    ['label' => 'Add User'],
]);
include ROOT_DIR . '/includes/header.php';

$allRoles = $pdo->query("SELECT id, name, description FROM roles ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="form-container">
    <h2>👤 Add New User</h2>

    <form id="addUserForm">
        <div class="form-group">
            <label for="username">Username <span class="required">*</span></label>
            <input type="text" id="username" name="username" required
                   placeholder="e.g. johndoe" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" required
                   placeholder="user@example.com" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" required
                   placeholder="Minimum 8 characters" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password <span class="required">*</span></label>
            <input type="password" id="password_confirm" name="password_confirm" required
                   placeholder="Re-enter password" autocomplete="new-password">
        </div>

        <?php if (!empty($allRoles)): ?>
        <div class="form-group">
            <label>Roles</label>
            <div class="checkbox-group">
                <?php foreach ($allRoles as $role): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>">
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
                <input type="checkbox" name="is_active" value="1" checked>
                <strong>Active</strong> <small class="text-muted"> — uncheck to disable this account</small>
            </label>
        </div>

        <button type="submit" class="btn" id="submitBtn">👤 Add User</button>
    </form>

    <div id="addResult"></div>
</div>

<script>
$(document).ready(function () {
    $('#addUserForm').on('submit', function (e) {
        e.preventDefault();

        var password = $('#password').val();
        var confirm  = $('#password_confirm').val();
        if (password !== confirm) {
            $('#addResult').html('<div class="error-message">❌ Passwords do not match.</div>');
            return;
        }
        if (password.length < 8) {
            $('#addResult').html('<div class="error-message">❌ Password must be at least 8 characters.</div>');
            return;
        }

        var btn = $('#submitBtn');
        btn.prop('disabled', true).text('Saving...');
        $('#addResult').html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: $(this).serialize() + '&action=add_user',
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('👤 Add User');
                if (res.success) {
                    $('#addResult').html('<div class="success-message">' + res.message + ' Redirecting...</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/AccessControl/users/';
                    }, 1500);
                } else {
                    $('#addResult').html('<div class="error-message">❌ ' + res.message + '</div>');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('👤 Add User');
                $('#addResult').html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
