<?php
// =============================================================================
// includes/auth.php — Authentication & session helpers
// =============================================================================

/**
 * Start the PHP session if it hasn't been started yet.
 * Configures secure cookie parameters before starting the session.
 */
function authStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Require the user to be logged in for a regular (HTML) page.
 * Redirects unauthenticated visitors to the login page.
 */
function requireLogin(): void
{
    authStartSession();
    if (!isLoggedIn()) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $redirect = $baseUrl . ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $baseUrl . '/login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

/**
 * Require the user to be logged in for an API endpoint.
 * Returns a 401 JSON response for unauthenticated requests.
 */
function requireApiLogin(): void
{
    authStartSession();
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
        exit;
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

/**
 * Check whether the logged-in user has permission to access a page path.
 * Admins bypass all permission checks.
 * Pages not listed in the `pages` table are accessible to all logged-in users.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $pagePath  Path as stored in the pages table, e.g. '/modules/Bookings/'
 * @return bool
 */
function hasPagePermission(PDO $pdo, int $userId, string $pagePath): bool
{
    $user = getCurrentUser();
    // Admin bypass — verify the session user matches the requested user ID
    if ($user && (int) $user['id'] === $userId && !empty($user['is_admin'])) {
        return true;
    }

    try {
        // Check if this path is a managed page (also fetch module for manage-all check)
        $stmt = $pdo->prepare("SELECT id, module FROM pages WHERE path = ? LIMIT 1");
        $stmt->execute([$pagePath]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            // Path not in the pages table — accessible to all authenticated users
            return true;
        }

        // Check direct page permission OR module-level 'manage' permission
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM role_permissions rp
            JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ? AND (
                rp.page_id = ?
                OR rp.page_id IN (
                    SELECT id FROM pages WHERE module = ? AND operation = 'manage'
                )
            )
        ");
        $stmt->execute([$userId, (int) $page['id'], $page['module']]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log('hasPagePermission error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Require the user to have permission to access a specific page path.
 * Redirects to the dashboard with ?error=forbidden if access is denied.
 *
 * @param PDO    $pdo
 * @param string $pagePath  Path as stored in the pages table, e.g. '/modules/Bookings/'
 */
function requirePagePermission(PDO $pdo, string $pagePath): void
{
    requireLogin();
    $user = getCurrentUser();
    if ($user && !hasPagePermission($pdo, (int) $user['id'], $pagePath)) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $baseUrl . '/dashboard.php?error=forbidden');
        exit;
    }
}

/**
 * Check whether the logged-in user has a specific module-level CRUD permission.
 * Looks up permissions by module name and operation rather than by page path.
 * Admins bypass all checks.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $module     Module identifier, e.g. 'bookings', 'clients'
 * @param string $operation  One of: view, create, edit, delete
 * @return bool
 */
function hasModulePermission(PDO $pdo, int $userId, string $module, string $operation): bool
{
    $user = getCurrentUser();
    // Admin bypass
    if ($user && (int) $user['id'] === $userId && !empty($user['is_admin'])) {
        return true;
    }

    try {
        // Also grant if the user holds the module-level 'manage' (all-operations) permission
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM role_permissions rp
            JOIN user_roles       ur ON ur.role_id = rp.role_id
            JOIN pages             p  ON p.id       = rp.page_id
            WHERE ur.user_id = ? AND p.module = ? AND (p.operation = ? OR p.operation = 'manage')
        ");
        $stmt->execute([$userId, $module, $operation]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log('hasModulePermission error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Require the logged-in user to hold a specific module CRUD permission.
 * Returns a 403 JSON response and exits if the check fails.
 * Intended for use inside API endpoint handler functions.
 *
 * @param PDO    $pdo
 * @param string $module     Module identifier, e.g. 'bookings', 'clients'
 * @param string $operation  One of: view, create, edit, delete
 */
function requireApiModulePermission(PDO $pdo, string $module, string $operation): void
{
    requireApiLogin();
    $user = getCurrentUser();
    if ($user && !hasModulePermission($pdo, (int) $user['id'], $module, $operation)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'You do not have permission to perform this action.']);
        exit;
    }
}
