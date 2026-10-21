<?php
// services/variables.php

$page_title = 'System Variables';
$page_subtitle = 'System Variables';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'System Variables'],
]);
include ROOT_DIR . '/includes/header.php';
?>

<!-- System Variables -->
<div class="form-container">
    <h2>⚙️ System Variables</h2>
    <form id="variablesForm">
        <?php foreach (SYSTEM_VARIABLES as $name => $def):
            $stmt = $pdo->prepare("SELECT value FROM system_variables WHERE name = ?");
            $stmt->execute([$name]);
            $saved = $stmt->fetchColumn();
            $value = ($saved !== false) ? $saved : $def['default'];
        ?>
            <div class="form-group">
                <label><?= htmlspecialchars($def['label']) ?></label>
                <?php if ($def['type'] === 'number'): ?>
                    <input type="number" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" step="0.01" min="0" required>
                    <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                <?php elseif ($def['type'] === 'time'): ?>
                    <input type="time" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" required>
                    <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                <?php elseif ($def['type'] === 'textarea'): ?>
                    <textarea name="variables[<?= htmlspecialchars($name) ?>]" rows="12"
                        style="width: 100%; font-family: monospace;" required><?= htmlspecialchars($value) ?></textarea>
                    <?php if ($name === 'ai_prompt_template'): ?>
                        <small>Placeholders like <code>{{car_rental}}</code> are filled in automatically — keep the token names intact.</small>
                    <?php else: ?>
                        <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <input type="text" name="variables[<?= htmlspecialchars($name) ?>]"
                        value="<?= htmlspecialchars($value) ?>" <?= empty($def['optional']) ? 'required' : '' ?>>
                    <?php if (!empty($def['hint'])): ?>
                        <small><?= htmlspecialchars($def['hint']) ?></small>
                    <?php else: ?>
                        <small>Default: <?= htmlspecialchars($def['default']) ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="page-action-btn save" id="varSubmitBtn">
            💾 Save Variables
        </button>
    </form>
    <div id="variablesResult"></div>
</div>

<script>
    $(document).ready(function () {
        $('#variablesForm').on('submit', function (e) {
            e.preventDefault();
            var submitBtn = $('#varSubmitBtn');
            var result = $('#variablesResult');

            submitBtn.prop('disabled', true).text('Saving...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/services/api/index.php?action=update_variables',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    submitBtn.prop('disabled', false).text('💾 Save Variables');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }

                    setTimeout(function () {
                        result.fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function (xhr, status, error) {
                    submitBtn.prop('disabled', false).text('💾 Save Variables');
                    result.html('<div class="error-message">❌ Failed to save. Please try again.</div>');
                    console.error('Error:', error, xhr.responseText);
                    logJsError('ERROR', 'Variables: failed to save variables', { response: xhr.responseText });
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
