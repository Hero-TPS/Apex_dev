<?php
// includes/header.php
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php //echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>
    <?php //echo htmlspecialchars(BUSINESS_NAME); ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js" crossorigin="anonymous"></script>
    <!--        <script src="assets/js/jquery-3.7.1.min.js"></script>-->
    <script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
    <script src="<?= BASE_URL ?>/assets/js/error-logging.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/whatsapp-menu.js" defer></script>
</head>

<body>

    <div class="container">
        <header class="header">
            <h1>
                <?php echo htmlspecialchars(BUSINESS_NAME); ?>
            </h1>
            <p class="subtitle">
                <?php echo isset($page_subtitle) ? htmlspecialchars($page_subtitle) : 'Management Dashboard'; ?>
            </p>
            <a href="<?= BASE_URL ?>/logout.php" class="logout-link">🔓 Logout</a>
        </header>

        <?php
        // "Logging today" note while a GPS Log session is open. Skipped without a database connection, before the
        // GPS Log update has run, and on the GPS Log page itself.
        $gpsBannerSince = null;
        if (empty($hide_gps_banner) && isset($pdo) && $pdo instanceof PDO && is_file(ROOT_DIR . '/includes/gps_session.php')) {
            require_once ROOT_DIR . '/includes/gps_session.php';
            $gpsBannerSince = gpsSessionBannerSince($pdo);
        }
        ?>
        <?php if ($gpsBannerSince !== null): ?>
            <a class="at-gps-banner" href="<?= BASE_URL ?>/services/gps_sessions.php">📍 Logging today since <?= htmlspecialchars($gpsBannerSince) ?> · open GPS Log</a>
        <?php endif; ?>

        <main class="content">
            <!-- Breadcrumb Navigation -->
            <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
                <div class="breadcrumb">
                    <a href="<?= BASE_URL ?>/dashboard/index.php">Home</a>
                    <?php echo isset($breadcrumb) ? $breadcrumb : ''; ?>
                </div>
            <?php endif; ?>
