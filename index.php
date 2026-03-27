<?php
// index.php — Public visitor / marketing homepage
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';

authStartSession();

$require_login  = false;
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

    <div class="visitor-features">
        <div class="visitor-feature">
            <span class="visitor-feature-icon">📅</span>
            <h3>Bookings</h3>
            <p>Manage transport bookings, track confirmations, and generate invoices.</p>
        </div>
        <div class="visitor-feature">
            <span class="visitor-feature-icon">👥</span>
            <h3>Clients</h3>
            <p>Maintain a complete client directory with booking history.</p>
        </div>
        <div class="visitor-feature">
            <span class="visitor-feature-icon">💰</span>
            <h3>Financials</h3>
            <p>Monitor income, expenses, and generate financial reports.</p>
        </div>
        <div class="visitor-feature">
            <span class="visitor-feature-icon">⛽</span>
            <h3>Fleet</h3>
            <p>Track fuel usage and Uber income to keep your fleet running efficiently.</p>
        </div>
    </div>
</div>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
