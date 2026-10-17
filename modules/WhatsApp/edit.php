<?php
// modules/WhatsApp/edit.php
// Edit a WhatsApp message (built-in or your own).
$page_title = 'Edit WhatsApp Message';
$page_subtitle = 'Edit WhatsApp Message';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$templateId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$template = null;
try {
    $template = $templateId ? getWhatsAppTemplateById($pdo, $templateId) : null;
} catch (PDOException $e) {
    logError('WHATSAPP', 'Failed to load WhatsApp template', ['id' => $templateId, 'error' => $e->getMessage()]);
}
if (!$template) {
    http_response_code(404);
    die('<div class="error-message">Message not found.</div>');
}

$breadcrumb = buildBreadcrumb([
    ['label' => 'WhatsApp Messages', 'url' => BASE_URL . '/modules/WhatsApp/'],
    ['label' => $template['label']],
]);

include ROOT_DIR . '/includes/header.php';
?>

<h2>✏️ Edit WhatsApp Message</h2>

<?php include __DIR__ . '/partials/template_form.php'; ?>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
