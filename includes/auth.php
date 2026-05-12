<?php
// includes/auth.php
// Protects intranet pages. Require this after config.php on every intranet page.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path'     => '/',
        'secure'   => false, // set to true when on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: ' . BASE_URL . '/login.php' . ($redirect ? '?redirect=' . $redirect : ''));
    exit;
}
