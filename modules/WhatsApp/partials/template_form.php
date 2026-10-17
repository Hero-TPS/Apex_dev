<?php
// modules/WhatsApp/partials/template_form.php
// Shared add / edit form for a WhatsApp message. Included by add.php and edit.php.
// Expects: $template (array of the stored row when editing, null when adding), plus
// everything add.php / edit.php already load (config, helpers, header).

$isNew = $template === null;
$placement = $isNew ? 'booking_menu' : $template['placement'];
$isMenuMessage = $placement === 'booking_menu';
$variables = getWhatsAppVariables($placement);
$clientVariables = getWhatsAppClientVariables();
?>

<div id="notification-area"></div>

<form id="templateForm">
    <input type="hidden" name="id" value="<?= $isNew ? '' : (int) $template['id'] ?>">

    <?php if (!$isNew): ?>
        <p class="at-wa-form-meta">
            <strong>Used for:</strong> <?= e(WHATSAPP_PLACEMENTS[$placement] ?? $placement) ?>
            <?php if (!empty($template['is_builtin'])): ?>
                <span class="at-wa-badge at-wa-badge--builtin">Built-in</span>
                <span class="client-subline">The system looks this message up by name, so it can be edited but not deleted.</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="form-group">
        <label for="label">Name (shown in the menu)</label>
        <input type="text" id="label" name="label" maxlength="100" required
            value="<?= $isNew ? '' : e($template['label']) ?>">
    </div>

    <div class="form-group">
        <label for="body">Message</label>
        <textarea id="body" name="body" class="at-wa-body-input" maxlength="2000" required><?= $isNew ? '' : e($template['body']) ?></textarea>
    </div>

    <?php if ($isMenuMessage): ?>
        <div class="form-group">
            <label class="at-wa-check-label">
                <input type="checkbox" name="active" value="1"
                    <?= ($isNew || !empty($template['active'])) ? 'checked' : '' ?>>
                Show in the WhatsApp menu
            </label>
        </div>
    <?php endif; ?>

    <details class="at-wa-help">
        <summary>How to write a message</summary>
        <p>
            Put <code>{name}</code> where a value should go, for example <code>Good day {client_name},</code>.
            A line is left out of the sent message when a placeholder on it is empty
            (a <code>Flight Number: {flight_number}</code> line disappears when there is no flight).
        </p>
        <p>
            For a section that only shows sometimes, put <code>{if name}</code>, <code>{else}</code> and
            <code>{/if}</code> on lines of their own. Use <code>{if !name}</code> for "when empty":
        </p>
        <pre>{if driver_name}
🚗 Your driver will be: {driver_name}
{else}
Driver still to be confirmed
{/if}</pre>
        <p>Blank lines at the start and end are removed, and several blank lines in a row become one.</p>
    </details>

    <details class="at-wa-help">
        <summary>Available placeholders</summary>
        <?php if ($isMenuMessage): ?>
            <p>
                Messages that only use the ones marked <span class="at-wa-clientmark">Clients pages</span>
                also appear in the WhatsApp menu on the Clients pages. Anything else needs a booking, so those
                messages only appear on the booking page.
            </p>
        <?php endif; ?>
        <table class="at-wa-vars">
            <?php foreach ($variables as $name => $description): ?>
                <tr>
                    <td><code>{<?= e($name) ?>}</code></td>
                    <td>
                        <?= e($description) ?>
                        <?php if ($isMenuMessage && isset($clientVariables[$name])): ?>
                            <span class="at-wa-clientmark">Clients pages</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </details>

    <button type="submit" id="saveTemplateBtn" class="page-action-btn save">💾 Save</button>
    <a href="<?= BASE_URL ?>/modules/WhatsApp/" class="page-action-btn back">Cancel</a>
</form>

<script>
    $(document).ready(function () {
        var form = $('#templateForm');
        var saveBtn = $('#saveTemplateBtn');
        var notificationArea = $('#notification-area');

        function showError(text) {
            notificationArea.html($('<div>').addClass('error-message').text(text));
            saveBtn.prop('disabled', false).text('💾 Save');
        }

        form.on('submit', function (e) {
            e.preventDefault();
            saveBtn.prop('disabled', true).text('Saving…');
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/WhatsApp/api/index.php',
                data: form.serialize() + '&action=save',
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.redirect_url) {
                        window.location.href = response.redirect_url;
                        return;
                    }
                    showError(response.message || 'Could not save the message.');
                },
                error: function (xhr) {
                    var msg = 'Could not save the message. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; }
                    showError(msg);
                }
            });
        });
    });
</script>
