<?php
// modules/AccessControl/api/index.php

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

requireApiLogin();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        // --- Users ---
        case 'add_user':    handleAddUser();    break;
        case 'update_user': handleUpdateUser(); break;
        case 'delete_user': handleDeleteUser(); break;

        // --- Roles ---
        case 'add_role':    handleAddRole();    break;
        case 'update_role': handleUpdateRole(); break;
        case 'delete_role': handleDeleteRole(); break;

        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
} catch (Exception $e) {
    logCritical('ACCESS_CONTROL', 'Unhandled exception', [
        'error'  => $e->getMessage(),
        'action' => $action,
    ]);
    jsonResponse(['success' => false, 'message' => 'An internal error occurred'], 500);
}

// =============================================================================
// User handlers
// =============================================================================

function handleAddUser(): void
{
    global $pdo;

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $roleIds  = array_map('intval', (array) ($_POST['roles'] ?? []));

    if ($username === '' || $email === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Username, email and password are required']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email address']);
    }
    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters']);
    }

    // Check uniqueness
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Username or email already exists']);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $isActive]);
    $userId = (int) $pdo->lastInsertId();

    // Assign roles
    assignUserRoles($pdo, $userId, $roleIds);

    logInfo('ACCESS_CONTROL', 'User created', ['username' => $username, 'new_user_id' => $userId]);
    jsonResponse(['success' => true, 'message' => "User '{$username}' created successfully", 'user_id' => $userId]);
}

function handleUpdateUser(): void
{
    global $pdo;

    $userId   = (int) ($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $roleIds  = array_map('intval', (array) ($_POST['roles'] ?? []));

    if (!$userId || $username === '' || $email === '') {
        jsonResponse(['success' => false, 'message' => 'ID, username and email are required']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email address']);
    }

    // Check uniqueness (exclude self)
    $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->execute([$username, $email, $userId]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Username or email already in use by another account']);
    }

    if ($password !== '') {
        if (strlen($password) < 8) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters']);
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$username, $email, $hash, $isActive, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$username, $email, $isActive, $userId]);
    }

    // Sync roles
    assignUserRoles($pdo, $userId, $roleIds);

    logInfo('ACCESS_CONTROL', 'User updated', ['user_id' => $userId, 'username' => $username]);
    jsonResponse(['success' => true, 'message' => "User '{$username}' updated successfully"]);
}

function handleDeleteUser(): void
{
    global $pdo;

    $userId = (int) ($_POST['id'] ?? 0);
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID']);
    }

    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'User not found']);
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    logInfo('ACCESS_CONTROL', 'User deleted', ['user_id' => $userId, 'username' => $user['username']]);
    jsonResponse(['success' => true, 'message' => "User '{$user['username']}' deleted"]);
}

// =============================================================================
// Role handlers
// =============================================================================

function handleAddRole(): void
{
    global $pdo;

    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pageIds     = array_map('intval', (array) ($_POST['pages'] ?? []));

    if ($name === '') {
        jsonResponse(['success' => false, 'message' => 'Role name is required']);
    }

    $check = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $check->execute([$name]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => "A role named '{$name}' already exists"]);
    }

    $stmt = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);
    $roleId = (int) $pdo->lastInsertId();

    assignRolePermissions($pdo, $roleId, $pageIds);

    logInfo('ACCESS_CONTROL', 'Role created', ['role_name' => $name, 'role_id' => $roleId]);
    jsonResponse(['success' => true, 'message' => "Role '{$name}' created successfully", 'role_id' => $roleId]);
}

function handleUpdateRole(): void
{
    global $pdo;

    $roleId      = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pageIds     = array_map('intval', (array) ($_POST['pages'] ?? []));

    if (!$roleId || $name === '') {
        jsonResponse(['success' => false, 'message' => 'ID and role name are required']);
    }

    // Check uniqueness (exclude self)
    $check = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
    $check->execute([$name, $roleId]);
    if ($check->fetch()) {
        jsonResponse(['success' => false, 'message' => "A role named '{$name}' already exists"]);
    }

    // Prevent renaming built-in Admin
    $current = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $current->execute([$roleId]);
    $currentRole = $current->fetch(PDO::FETCH_ASSOC);
    if ($currentRole && $currentRole['name'] === 'Admin') {
        $name = 'Admin'; // Lock the name
    }

    $stmt = $pdo->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $roleId]);

    // Admin role always has all pages — skip permission sync
    if ($currentRole && $currentRole['name'] !== 'Admin') {
        assignRolePermissions($pdo, $roleId, $pageIds);
    }

    logInfo('ACCESS_CONTROL', 'Role updated', ['role_id' => $roleId, 'role_name' => $name]);
    jsonResponse(['success' => true, 'message' => "Role '{$name}' updated successfully"]);
}

function handleDeleteRole(): void
{
    global $pdo;

    $roleId = (int) ($_POST['id'] ?? 0);
    if (!$roleId) {
        jsonResponse(['success' => false, 'message' => 'Invalid role ID']);
    }

    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) {
        jsonResponse(['success' => false, 'message' => 'Role not found']);
    }
    if ($role['name'] === 'Admin') {
        jsonResponse(['success' => false, 'message' => 'The Admin role cannot be deleted']);
    }

    $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);

    logInfo('ACCESS_CONTROL', 'Role deleted', ['role_id' => $roleId, 'role_name' => $role['name']]);
    jsonResponse(['success' => true, 'message' => "Role '{$role['name']}' deleted"]);
}

// =============================================================================
// Helpers
// =============================================================================

/**
 * Replace a user's role assignments.
 */
function assignUserRoles(PDO $pdo, int $userId, array $roleIds): void
{
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
    if (!empty($roleIds)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            if ($rid > 0) {
                $stmt->execute([$userId, $rid]);
            }
        }
    }
}

/**
 * Replace a role's page permissions.
 */
function assignRolePermissions(PDO $pdo, int $roleId, array $pageIds): void
{
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);
    if (!empty($pageIds)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, page_id) VALUES (?, ?)");
        foreach ($pageIds as $pid) {
            if ($pid > 0) {
                $stmt->execute([$roleId, $pid]);
            }
        }
    }
}
