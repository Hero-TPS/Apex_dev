<?php
// modules/WhatsApp/add.php
// Add a new WhatsApp message to the booking WhatsApp menu.
$page_title = 'Add WhatsApp Message';
$page_subtitle = 'Add WhatsApp Message';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'WhatsApp Messages', 'url' => BASE_URL . '/modules/WhatsApp/'],
    ['label' => 'Add'],
]);

$template = null;

include ROOT_DIR . '/includes/header.php';
?>

<h2>➕ Add WhatsApp Message</h2>

<?php include __DIR__ . '/partials/template_form.php'; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
