<?php
// login.php
// NOTE: Add to config.php:
// define('ADMIN_USERNAME', 'your_username');
// define('ADMIN_PASSWORD_HASH', password_hash('your_password', PASSWORD_DEFAULT));

require_once __DIR__ . '/config.php';

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

// Already logged in — redirect to dashboard
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $redirect = $_GET['redirect'] ?? '/dashboard/index.php';
    header('Location: ' . BASE_URL . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['logged_in'] = true;
        $redirect = $_GET['redirect'] ?? '/dashboard/index.php';
        header('Location: ' . BASE_URL . $redirect);
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — <?= htmlspecialchars(BUSINESS_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
</head>
<body>
<div class="form-container">
    <h1><?= htmlspecialchars(BUSINESS_NAME) ?></h1>
    <h2>Staff Login</h2>
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= BASE_URL ?>/login.php<?= isset($_GET['redirect']) ? '?redirect=' . htmlspecialchars(urlencode($_GET['redirect'])) : '' ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="submit-btn">Login</button>
    </form>
</div>
</body>
</html>
