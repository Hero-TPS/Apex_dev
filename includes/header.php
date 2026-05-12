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

        <main class="content">
            <!-- Breadcrumb Navigation -->
            <?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
                <div class="breadcrumb">
                    <a href="<?= BASE_URL ?>/dashboard/index.php">Home</a>
                    <?php echo isset($breadcrumb) ? $breadcrumb : ''; ?>
                </div>
            <?php endif; ?>