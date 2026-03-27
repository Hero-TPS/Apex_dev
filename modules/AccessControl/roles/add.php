<?php
// modules/AccessControl/roles/add.php
$page_title    = 'Add Role';
$page_subtitle = 'Access Control';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/auth.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Access Control', 'url' => BASE_URL . '/modules/AccessControl/'],
    ['label' => 'Roles',          'url' => BASE_URL . '/modules/AccessControl/roles/'],
    ['label' => 'Add Role'],
]);
$page_path = '/modules/AccessControl/roles/';
include ROOT_DIR . '/includes/header.php';

$allPages = $pdo->query("SELECT id, name, path, module, operation, description FROM pages ORDER BY module ASC, FIELD(operation,'view','create','edit','delete'), name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group pages by module for the permission grid
$pagesByModule = [];
foreach ($allPages as $page) {
    $pagesByModule[$page['module']][] = $page;
}

$moduleLabels = [
    'bookings'       => '📅 Bookings',
    'clients'        => '👥 Clients',
    'fuel'           => '⛽ Fuel',
    'uber'           => '🚗 Uber',
    'financials'     => '💰 Financials',
    'maintenance'    => '⚙️ Maintenance',
    'access_control' => '🔐 Access Control',
];

$moduleOrder = array_keys($moduleLabels);
?>

<div class="form-container">
    <h2>🏷️ Add New Role</h2>

    <form id="addRoleForm">
        <div class="form-group">
            <label for="name">Role Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required placeholder="e.g. Manager">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <input type="text" id="description" name="description"
                   placeholder="Brief description of this role">
        </div>

        <?php if (!empty($allPages)): ?>
        <div class="form-group">
            <label>Permissions</label>
            <p class="text-muted" style="margin-top:0;font-size:0.9em;">Select the operations this role may perform. Permissions are grouped by module.</p>
            <div style="margin-bottom: 10px;">
                <button type="button" id="selectAllPages" class="page-action-btn toggle" style="font-size:0.85em;padding:4px 10px;">✅ Grant All</button>
                <button type="button" id="deselectAllPages" class="page-action-btn" style="font-size:0.85em;padding:4px 10px;">❌ Revoke All</button>
            </div>

            <table class="permissions-grid" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #ddd;width:55%;">Permission</th>
                        <th style="text-align:center;padding:8px 6px;border-bottom:2px solid #ddd;">Operation</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:2px solid #ddd;">Description</th>
                        <th style="text-align:center;padding:8px 6px;border-bottom:2px solid #ddd;width:70px;">Grant</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $orderedModules = array_merge(
                    array_intersect($moduleOrder, array_keys($pagesByModule)),
                    array_diff(array_keys($pagesByModule), $moduleOrder)
                );
                foreach ($orderedModules as $mod):
                    $pages = $pagesByModule[$mod];
                    $label = $moduleLabels[$mod] ?? ucfirst(str_replace('_', ' ', $mod));
                    $first = true;
                    foreach ($pages as $page):
                        $opBadge = [
                            'view'   => '<span style="background:#17a2b8;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">view</span>',
                            'create' => '<span style="background:#28a745;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">create</span>',
                            'edit'   => '<span style="background:#ffc107;color:#212529;padding:2px 7px;border-radius:4px;font-size:0.78em;">edit</span>',
                            'delete' => '<span style="background:#dc3545;color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78em;">delete</span>',
                        ][$page['operation']] ?? e($page['operation']);
                ?>
                    <tr style="border-bottom:1px solid #eee;<?= $first ? 'border-top:2px solid #ccc;' : '' ?>">
                        <?php if ($first): ?>
                        <td rowspan="<?= count($pages) ?>" style="padding:8px 12px;vertical-align:top;font-weight:bold;background:#fafafa;border-right:1px solid #eee;">
                            <?= e($label) ?>
                        </td>
                        <?php endif; ?>
                        <td style="text-align:center;padding:8px 6px;"><?= $opBadge ?></td>
                        <td style="padding:8px 12px;">
                            <strong><?= e($page['name']) ?></strong>
                            <?php if ($page['description']): ?>
                            <br><small class="text-muted"><?= e($page['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;padding:8px 6px;">
                            <input type="checkbox" name="pages[]" value="<?= (int) $page['id'] ?>">
                        </td>
                    </tr>
                <?php
                        $first = false;
                    endforeach;
                endforeach;
                ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn" id="submitBtn">🏷️ Add Role</button>
    </form>

    <div id="addResult"></div>
</div>

<script>
$(document).ready(function () {
    $('#selectAllPages').on('click', function () {
        $('input[name="pages[]"]').prop('checked', true);
    });
    $('#deselectAllPages').on('click', function () {
        $('input[name="pages[]"]').prop('checked', false);
    });

    $('#addRoleForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $('#submitBtn');
        btn.prop('disabled', true).text('Saving...');
        $('#addResult').html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/AccessControl/api/index.php',
            data: $(this).serialize() + '&action=add_role',
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).text('🏷️ Add Role');
                if (res.success) {
                    $('#addResult').html('<div class="success-message">' + res.message + ' Redirecting...</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/AccessControl/roles/';
                    }, 1500);
                } else {
                    $('#addResult').html('<div class="error-message">❌ ' + res.message + '</div>');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('🏷️ Add Role');
                $('#addResult').html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
