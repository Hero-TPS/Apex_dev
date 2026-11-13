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

// Who the message is sent to. Only your own booking-menu messages can choose; built-in
// messages and the system messages (driver, etc.) keep what they have.
$recipient = $isNew ? 'client' : ($template['recipient'] ?? 'client');
$isBuiltin = !$isNew && !empty($template['is_builtin']);
$canChooseRecipient = $isMenuMessage && !$isBuiltin;
$isPassenger = $recipient === 'passenger';
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

    <?php if ($canChooseRecipient): ?>
        <div class="form-group">
            <label for="recipient">Send to</label>
            <select id="recipient" name="recipient">
                <option value="client"<?= $isPassenger ? '' : ' selected' ?>>The client</option>
                <option value="passenger"<?= $isPassenger ? ' selected' : '' ?>>The passenger on the booking</option>
            </select>
            <span class="client-subline">A passenger message goes to the passenger's WhatsApp number, in its own "To passenger" section of the booking menu. It always sends in English, and only shows on a booking that has a different passenger with a phone number.</span>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="body">Message</label>
        <textarea id="body" name="body" class="at-wa-body-input" maxlength="2000" required><?= $isNew ? '' : e($template['body']) ?></textarea>
    </div>

    <?php if ($placement !== 'driver'): ?>
        <div class="form-group at-wa-af-group"<?= $isPassenger ? ' style="display:none"' : '' ?>>
            <label for="body_af">Afrikaans message (optional)</label>
            <textarea id="body_af" name="body_af" class="at-wa-body-input" maxlength="2000"><?= $isNew ? '' : e($template['body_af'] ?? '') ?></textarea>
            <span class="client-subline">Sent to clients and bookings set to Afrikaans. Leave empty to send the English message. Uses the same placeholders.</span>
        </div>
    <?php endif; ?>

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
            <p class="at-wa-client-only"<?= $isPassenger ? ' style="display:none"' : '' ?>>
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
                            <span class="at-wa-clientmark at-wa-client-only"<?= $isPassenger ? ' style="display:none"' : '' ?>>Clients pages</span>
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

        // A passenger message has no Afrikaans version and never shows on the Clients pages
        function applyRecipient() {
            var isPassenger = $('#recipient').val() === 'passenger';
            $('.at-wa-af-group, .at-wa-client-only').toggle(!isPassenger);
        }
        $('#recipient').on('change', applyRecipient);

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
