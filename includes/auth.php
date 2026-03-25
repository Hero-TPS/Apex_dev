<?php
// =============================================================================
// includes/auth.php — Authentication & session helpers
// =============================================================================

/**
 * Start the PHP session if it hasn't been started yet.
 */
function authStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Return the currently logged-in user array (from session), or null.
 *
 * @return array|null  Keys: id, username, email, is_admin
 */
function getCurrentUser(): ?array
{
    authStartSession();
    return $_SESSION['auth_user'] ?? null;
}

/**
 * Return true if a user is currently logged in.
 */
function isLoggedIn(): bool
{
    return getCurrentUser() !== null;
}

/**
 * Return true if the logged-in user has the Admin role.
 */
function isAdmin(): bool
{
    $user = getCurrentUser();
    return $user !== null && !empty($user['is_admin']);
}

/**
 * Log a user in: verify credentials, populate session.
 *
 * @param PDO    $pdo
 * @param string $username
 * @param string $password  Plain-text password
 * @return array|null       User array on success, null on failure
 */
function loginUser(PDO $pdo, string $username, string $password): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, username, email, password_hash, is_active
        FROM users
        WHERE username = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Update last_login
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    // Check if user has Admin role
    $roleStmt = $pdo->prepare("
        SELECT r.name FROM roles r
        JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = ?
    ");
    $roleStmt->execute([$user['id']]);
    $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

    authStartSession();
    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id'       => (int) $user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'is_admin' => in_array('Admin', $roles, true),
        'roles'    => $roles,
    ];

    return $_SESSION['auth_user'];
}

/**
 * Log the current user out and destroy the session.
 */
function logoutUser(): void
{
    authStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Get all roles assigned to a user.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array  Array of role arrays (id, name, description)
 */
function getUserRoles(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.description
        FROM roles r
        JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = ?
        ORDER BY r.name ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all pages permitted for a role.
 *
 * @param PDO $pdo
 * @param int $roleId
 * @return array  Array of page IDs
 */
function getRolePermissions(PDO $pdo, int $roleId): array
{
    $stmt = $pdo->prepare("
        SELECT page_id FROM role_permissions WHERE role_id = ?
    ");
    $stmt->execute([$roleId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
