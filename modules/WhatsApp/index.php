<?php
// modules/WhatsApp/index.php
// List of WhatsApp message templates (built-in and your own) with add, edit and delete.
$page_title = 'WhatsApp Messages';
$page_subtitle = 'WhatsApp Messages';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helpers.php';

$breadcrumb = buildBreadcrumb([['label' => 'WhatsApp Messages']]);

$loadError = false;
$templates = [];
try {
    $templates = getWhatsAppTemplates($pdo);
} catch (PDOException $e) {
    $loadError = true;
    logError('WHATSAPP', 'Failed to load WhatsApp templates', ['error' => $e->getMessage()]);
}

include ROOT_DIR . '/includes/header.php';
?>

<h2>💬 WhatsApp Messages</h2>

<p class="at-wa-list-intro">
    Messages in the WhatsApp menu on a booking. A message that only uses client details (like bank details)
    also shows on the Clients pages. Built-in messages can be edited but not deleted.
</p>

<a href="<?= BASE_URL ?>/modules/WhatsApp/add.php" class="page-action-btn save">➕ Add Message</a>

<div id="notification-area"></div>

<?php if ($loadError): ?>
    <div class="error-message">Could not load the messages. If this is the first time, run the whatsapp entry in LIVE_MIGRATION.md on this database.</div>
<?php elseif (empty($templates)): ?>
    <div class="no-bookings">
        <h3>💬 No messages yet</h3>
        <p>Add your first WhatsApp message.</p>
    </div>
<?php else: ?>
    <table class="bookings-table at-wa-list-table">
        <thead>
            <tr>
                <th>Message</th>
                <th>Used for</th>
                <th>In menu</th>
                <th>Preview</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $tpl): ?>
                <?php
                $inMenu = $tpl['placement'] === 'booking_menu';
                if ($inMenu) {
                    $usedFor = isClientLevelWhatsAppBody($tpl['body']) ? 'Booking and Clients pages' : 'Booking page';
                } else {
                    $usedFor = WHATSAPP_PLACEMENTS[$tpl['placement']] ?? $tpl['placement'];
                }
                $preview = mb_strimwidth(preg_replace('/\s+/', ' ', $tpl['body']), 0, 100, '…');
                ?>
                <tr id="template-row-<?= (int) $tpl['id'] ?>">
                    <td data-label="Message">
                        <strong><?= e($tpl['label']) ?></strong>
                        <?php if (!empty($tpl['is_builtin'])): ?>
                            <span class="at-wa-badge at-wa-badge--builtin">Built-in</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Used for"><?= e($usedFor) ?></td>
                    <td data-label="In menu"><?= $inMenu ? (!empty($tpl['active']) ? '✅' : '—') : '—' ?></td>
                    <td data-label="Preview" class="at-wa-preview"><?= e($preview) ?></td>
                    <td data-label="Actions">
                        <div class="actions-container">
                            <a href="<?= BASE_URL ?>/modules/WhatsApp/edit.php?id=<?= (int) $tpl['id'] ?>"
                                class="action-btn edit-btn">Edit</a>
                            <?php if (empty($tpl['is_builtin'])): ?>
                                <button type="button" class="action-btn delete-btn at-wa-delete"
                                    data-id="<?= (int) $tpl['id'] ?>"
                                    data-label="<?= e($tpl['label']) ?>">Delete</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="deleteTemplateModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Delete message?</h3>
        <p id="deleteTemplateText"></p>
        <div class="modal-buttons">
            <button id="confirmDeleteTemplateBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteTemplateBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var modal = $('#deleteTemplateModal');
        var templateToDelete = null;
        var notificationArea = $('#notification-area');

        function showMessage(cls, text) {
            notificationArea.html($('<div>').addClass(cls).text(text));
        }

        $('.at-wa-delete').on('click', function () {
            templateToDelete = $(this).data('id');
            $('#deleteTemplateText').text('This permanently deletes "' + $(this).data('label') + '" from the WhatsApp menu.');
            modal.css('display', 'flex');
        });

        $('#cancelDeleteTemplateBtn').on('click', function () {
            modal.hide();
            templateToDelete = null;
        });

        $('#confirmDeleteTemplateBtn').on('click', function () {
            if (!templateToDelete) { return; }
            var id = templateToDelete;
            modal.hide();
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/WhatsApp/api/index.php',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#template-row-' + id).remove();
                        showMessage('success-message', response.message);
                    } else {
                        showMessage('error-message', response.message || 'Could not delete the message.');
                    }
                },
                error: function () {
                    showMessage('error-message', 'Could not delete the message. Please try again.');
                },
                complete: function () {
                    templateToDelete = null;
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
