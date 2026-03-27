<?php
// index.php — Public visitor / marketing homepage
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

authStartSession();

$require_login  = false;
$hide_hamburger = true;
$page_title     = 'Welcome';
$page_subtitle  = 'Transport Management System';
include ROOT_DIR . '/includes/header.php';
?>

<div class="visitor-home">
    <div class="visitor-hero">
        <h2>Welcome to <?= htmlspecialchars(BUSINESS_NAME) ?></h2>
        <p class="visitor-tagline">Streamlined transport booking and fleet management — all in one place.</p>
        <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn visitor-signin-btn">📊 Go to Dashboard</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php" class="btn visitor-signin-btn">🔑 Sign In</a>
        <?php endif; ?>
    </div>

</div>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
