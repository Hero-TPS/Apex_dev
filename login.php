<?php
// login.php
$page_title    = 'Login';
$page_subtitle = 'Please sign in to continue';

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

authStartSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $user = loginUser($pdo, $username, $password);
        if ($user) {
            logInfo('AUTH', 'User logged in', ['username' => $user['username']]);
            // Validate redirect to prevent open-redirect attacks (internal paths only)
            $redirect = $_GET['redirect'] ?? '';
            if ($redirect === '' || !str_starts_with($redirect, BASE_URL . '/') || str_contains($redirect, '//')) {
                $redirect = BASE_URL . '/dashboard.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            logWarning('AUTH', 'Failed login attempt', ['username' => $username]);
            $error = 'Invalid username or password.';
        }
    }
}

$require_login = false;
include ROOT_DIR . '/includes/header.php';
?>

<div class="form-container login-container">
    <h2>🔐 Sign In</h2>

    <?php if ($error): ?>
        <div class="error-message"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(BASE_URL) ?>/login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   required autofocus autocomplete="username"
                   placeholder="Enter your username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   required autocomplete="current-password"
                   placeholder="Enter your password">
        </div>
        <button type="submit" class="btn login-btn">🔐 Sign In</button>
    </form>
</div>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
