<?php
// modules/WhatsApp/api/index.php
// WhatsApp templates API: save (add or edit) and delete a message.
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/../helpers.php';
require_once ROOT_DIR . '/includes/auth_api.php';

$action = $_REQUEST['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    switch ($action) {
        case 'save':
            handleSaveTemplate();
            break;
        case 'delete':
            handleDeleteTemplate();
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    logError('WHATSAPP', 'WhatsApp API error', [
        'action' => $action,
        'error' => $e->getMessage(),
    ]);
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
}

/**
 * Add a new message (no id posted) or update an existing one (id posted).
 * A new message always goes in the booking WhatsApp menu. Built-in messages keep their
 * key and placement; only the name, text and (for menu messages) visibility change.
 */
function handleSaveTemplate(): void
{
    global $pdo;

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $label = trim((string) ($_POST['label'] ?? ''));
    $body = trim(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['body'] ?? '')));
    $active = !empty($_POST['active']) ? 1 : 0;

    if ($label === '') {
        jsonResponse(['success' => false, 'message' => 'Please give the message a name.'], 400);
    }
    if (mb_strlen($label) > 100) {
        jsonResponse(['success' => false, 'message' => 'The name is too long (max 100 characters).'], 400);
    }
    if ($body === '') {
        jsonResponse(['success' => false, 'message' => 'The message cannot be empty.'], 400);
    }
    if (mb_strlen($body) > 2000) {
        jsonResponse(['success' => false, 'message' => 'The message is too long (max 2000 characters).'], 400);
    }

    $existing = null;
    if ($id !== null) {
        $existing = getWhatsAppTemplateById($pdo, $id);
        if (!$existing) {
            jsonResponse(['success' => false, 'message' => 'Message not found.'], 404);
        }
    }

    $placement = $existing['placement'] ?? 'booking_menu';
    $error = validateWhatsAppTemplateBody($body, $placement);
    if ($error !== null) {
        jsonResponse(['success' => false, 'message' => $error], 400);
    }

    if ($existing) {
        // Only booking-menu messages can be hidden; the system messages are always in use.
        $newActive = $placement === 'booking_menu' ? $active : 1;
        $stmt = $pdo->prepare("UPDATE whatsapp_templates SET label = ?, body = ?, active = ? WHERE id = ?");
        $stmt->execute([$label, $body, $newActive, $id]);

        logInfo('WHATSAPP', 'WhatsApp message updated', [
            'id' => $id,
            'template_key' => $existing['template_key'],
        ]);
    } else {
        $key = generateWhatsAppTemplateKey($pdo, $label);
        $sortOrder = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM whatsapp_templates WHERE placement = 'booking_menu'")->fetchColumn() + 10;

        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_templates (template_key, label, placement, body, active, sort_order, is_builtin)
            VALUES (?, ?, 'booking_menu', ?, ?, ?, 0)
        ");
        $stmt->execute([$key, $label, $body, $active, $sortOrder]);

        logInfo('WHATSAPP', 'WhatsApp message added', ['template_key' => $key]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Message saved.',
        'redirect_url' => BASE_URL . '/modules/WhatsApp/',
    ]);
}

/**
 * Delete one of your own messages. Built-in messages cannot be deleted.
 */
function handleDeleteTemplate(): void
{
    global $pdo;

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid message ID.'], 400);
    }

    $existing = getWhatsAppTemplateById($pdo, $id);
    if (!$existing) {
        jsonResponse(['success' => false, 'message' => 'Message not found.'], 404);
    }
    if (!empty($existing['is_builtin'])) {
        jsonResponse(['success' => false, 'message' => 'Built-in messages cannot be deleted.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM whatsapp_templates WHERE id = ? AND is_builtin = 0");
    $stmt->execute([$id]);

    logInfo('WHATSAPP', 'WhatsApp message deleted', [
        'id' => $id,
        'template_key' => $existing['template_key'],
    ]);

    jsonResponse(['success' => true, 'message' => '"' . $existing['label'] . '" deleted.']);
}
