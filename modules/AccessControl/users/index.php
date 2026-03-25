<?php
// modules/AccessControl/users/index.php
$page_title    = 'Users';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Users'],
]);
include ROOT_DIR . '/includes/header.php';

// Fetch all users with their roles
$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, u.is_active, u.created_at, u.last_login,
           GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    GROUP BY u.id
    ORDER BY u.username ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-actions" style="margin-bottom: 16px;">
    <a href="<?= BASE_URL ?>/modules/AccessControl/users/add.php" class="page-action-btn save">➕ Add User</a>
</div>

<?php if (empty($users)): ?>
    <div class="no-bookings">
        <h3>👤 No users found</h3>
        <p><a href="<?= BASE_URL ?>/modules/AccessControl/users/add.php" class="btn" style="width:auto;padding:10px 20px;">Add Your First User</a></p>
    </div>
<?php else: ?>
<div id="notification-area"></div>

<table class="bookings-table">
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Roles</th>
            <th>Status</th>
            <th>Last Login</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $user): ?>
        <tr id="user-row-<?= (int) $user['id'] ?>">
            <td data-label="Username"><?= e($user['username']) ?></td>
            <td data-label="Email"><?= e($user['email']) ?></td>
            <td data-label="Roles"><?= e($user['roles'] ?? '—') ?></td>
            <td data-label="Status">
                <?php if ($user['is_active']): ?>
                    <span class="status-badge status-confirmed">Active</span>
                <?php else: ?>
                    <span class="status-badge status-cancelled">Inactive</span>
                <?php endif; ?>
            </td>
            <td data-label="Last Login">
                <?php
                    $lastLogin = '—';
                    if ($user['last_login']) {
                        try {
                            $lastLogin = (new DateTime($user['last_login']))->format('d/m/Y H:i');
                        } catch (Exception $ignored) {
                            $lastLogin = e($user['last_login']);
                        }
                    }
                    echo $lastLogin;
                ?>
            </td>
            <td data-label="Actions">
                <div class="actions-container">
                    <a href="<?= BASE_URL ?>/modules/AccessControl/users/edit.php?id=<?= (int) $user['id'] ?>"
                       class="action-btn edit-btn">Edit</a>
                    <button class="action-btn delete-btn"
                            data-id="<?= (int) $user['id'] ?>"
                            data-username="<?= e($user['username']) ?>">Delete</button>
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
        <h3>Delete User</h3>
        <p>Are you sure you want to delete <strong id="deleteUsername"></strong>? This cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var modal = $('#deleteModal');
    var userIdToDelete = null;

    $(document).on('click', '.delete-btn', function () {
        userIdToDelete = $(this).data('id');
        $('#deleteUsername').text($(this).data('username'));
        modal.css('display', 'flex');
    });

    $('#cancelDeleteBtn').on('click', function () {
        modal.hide();
        userIdToDelete = null;
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!userIdToDelete) return;

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: { action: 'delete_user', id: userIdToDelete },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#user-row-' + userIdToDelete).fadeOut(function () { $(this).remove(); });
                    showNotification('✓ ' + res.message, 'success');
                } else {
                    showNotification('✗ ' + res.message, 'error');
                }
            },
            error: function () { showNotification('❌ Request failed', 'error'); },
            complete: function () { modal.hide(); userIdToDelete = null; }
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
