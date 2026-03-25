<?php
// logout.php
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

authStartSession();

if (isLoggedIn()) {
    $user = getCurrentUser();
    logInfo('AUTH', 'User logged out', ['username' => $user['username'] ?? 'unknown']);
    logoutUser();
}

header('Location: ' . BASE_URL . '/login.php');
exit;
