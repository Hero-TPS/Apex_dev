<?php
// modules/AccessControl/index.php
$page_title    = 'Access Control';
$page_subtitle = 'Users & Roles';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([['label' => 'Access Control']]);
include ROOT_DIR . '/includes/header.php';

// Summary counts
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$roleCount = (int) $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
$pageCount = (int) $pdo->query("SELECT COUNT(*) FROM pages")->fetchColumn();
?>

<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-number"><?= $userCount ?></div>
        <div class="stat-label">Users</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $roleCount ?></div>
        <div class="stat-label">Roles</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $pageCount ?></div>
        <div class="stat-label">Managed Pages</div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-section">
        <h3>👤 User Management</h3>
        <p>Create and manage user accounts, set active status and assign roles.</p>
        <div class="action-links">
            <a href="<?= BASE_URL ?>/modules/AccessControl/users/" class="page-action-btn view">View Users</a>
            <a href="<?= BASE_URL ?>/modules/AccessControl/users/add.php" class="page-action-btn save">➕ Add User</a>
        </div>
    </div>

    <div class="dashboard-section">
        <h3>🏷️ Role Management</h3>
        <p>Define roles and configure which pages each role may access.</p>
        <div class="action-links">
            <a href="<?= BASE_URL ?>/modules/AccessControl/roles/" class="page-action-btn view">View Roles</a>
            <a href="<?= BASE_URL ?>/modules/AccessControl/roles/add.php" class="page-action-btn save">➕ Add Role</a>
        </div>
    </div>
</div>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
