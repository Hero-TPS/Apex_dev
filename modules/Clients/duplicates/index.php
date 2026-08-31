<?php
$page_title = 'Duplicate Clients';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;

require_once __DIR__ . '/../../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'Clients', 'url' => BASE_URL . '/modules/Clients/'],
    ['label' => 'Duplicates'],
]);

// Counts for stats bar
try {
    $linkedCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_links")->fetchColumn();
    $dismissedCount = (int)$pdo->query("SELECT COUNT(*) FROM duplicate_dismissals")->fetchColumn();
} catch (PDOException $e) {
    $linkedCount = 0;
    $dismissedCount = 0;
}

include ROOT_DIR . '/includes/header.php';
?>

<div class="dup-stats-bar">
    <span class="dup-stat">🔍 Suspect pairs loaded per tab</span>
    <span class="dup-stat">🔗 Linked pairs: <strong id="stat-linked"><?= $linkedCount ?></strong></span>
    <span class="dup-stat">🚫 Dismissed pairs: <strong id="stat-dismissed"><?= $dismissedCount ?></strong></span>
</div>

<div class="dup-tabs">
    <button class="dup-tab-btn active" data-type="phone">📞 Same Phone</button>
    <button class="dup-tab-btn" data-type="name">📛 Similar Name</button>
    <button class="dup-tab-btn" data-type="address">🏠 Same Address</button>
</div>

<div id="dup-tab-content">
    <div id="dup-loading" class="loading" style="display:none;"></div>
    <div id="dup-result"></div>
</div>

<!-- Reassign confirmation modal -->
<div id="reassign-modal" class="dup-modal" style="display:none;">
    <div class="dup-modal-inner">
        <p id="reassign-confirm-text"></p>
        <button id="reassign-confirm-btn" class="page-action-btn delete">✅ Confirm &amp; Delete</button>
        <button id="reassign-cancel-btn" class="page-action-btn back">Cancel</button>
    </div>
</div>

<script>
$(document).ready(function () {
    var currentType = 'phone';

    // Load first tab on page load
    loadTab('phone');

    // Tab clicks
    $('.dup-tab-btn').on('click', function () {
        $('.dup-tab-btn').removeClass('active');
        $(this).addClass('active');
        currentType = $(this).data('type');
        loadTab(currentType);
    });

    function loadTab(type) {
        $('#dup-result').empty();
        $('#dup-loading').show();

        $.ajax({
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php?action=get_clusters&type=' + encodeURIComponent(type),
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                $('#dup-loading').hide();
                if (!res.success) {
                    $('#dup-result').html('<div class="error-message">' + escHtml(res.message || 'Error loading data') + '</div>');
                    return;
                }
                if (!res.clusters || res.clusters.length === 0) {
                    $('#dup-result').html('<div class="success-message">✅ No duplicates found for this category.</div>');
                    return;
                }
                renderClusters(res.clusters);
            },
            error: function () {
                $('#dup-loading').hide();
                $('#dup-result').html('<div class="error-message">❌ Failed to load clusters. Please try again.</div>');
            }
        });
    }

    function renderClusters(clusters) {
        var html = '';
        $.each(clusters, function (ci, cluster) {
            html += '<div class="dup-cluster" data-cluster="' + ci + '">';
            html += '<div class="dup-cards-row">';
            $.each(cluster, function (i, c) {
                var bookingBadgeClass = parseInt(c.booking_count) > 0 ? 'dup-badge-red' : 'dup-badge';
                var linkedBadge = parseInt(c.is_linked) ? ' <span class="dup-linked-badge">🔗 Linked</span>' : '';
                html += '<div class="dup-card" data-id="' + parseInt(c.id) + '">';
                html += '<div class="dup-card-name">' + escHtml(c.name) + linkedBadge + '</div>';
                html += '<div class="dup-card-detail">📱 ' + escHtml(c.phone || '—') + '</div>';
                html += '<div class="dup-card-detail">📍 ' + escHtml(c.address || '—') + '</div>';
                html += '<div class="dup-card-detail">✉️ ' + escHtml(c.email || '—') + '</div>';
                html += '<div class="dup-card-detail">📅 Bookings: <span class="' + bookingBadgeClass + '">' + parseInt(c.booking_count) + '</span></div>';
                html += '<div class="dup-card-detail dup-card-date">Added: ' + escHtml(c.date_added || '—') + '</div>';
                html += '</div>';
            });
            html += '</div>'; // .dup-cards-row

            // Action buttons (operate on the first two contacts in cluster)
            if (cluster.length >= 2) {
                var idA = parseInt(cluster[0].id);
                var idB = parseInt(cluster[1].id);
                var nameA = cluster[0].name;
                var nameB = cluster[1].name;
                var bookingsA = parseInt(cluster[0].booking_count);
                var bookingsB = parseInt(cluster[1].booking_count);

                html += '<div class="dup-actions">';
                html += '<button class="page-action-btn primary dup-link-btn" data-a="' + idA + '" data-b="' + idB + '">🔗 Link as Household</button>';

                // Delete A (only if 0 bookings)
                if (bookingsA === 0) {
                    html += '<button class="page-action-btn delete dup-delete-btn" data-id="' + idA + '" data-cluster="' + ci + '">🗑 Delete ' + escHtml(nameA) + '</button>';
                } else {
                    html += '<button class="page-action-btn delete dup-reassign-btn" data-from="' + idA + '" data-to="' + idB + '" data-from-name="' + escAttr(nameA) + '" data-to-name="' + escAttr(nameB) + '" data-bookings="' + bookingsA + '">🔄 Re-assign &amp; Delete ' + escHtml(nameA) + '</button>';
                }

                // Delete B (only if 0 bookings)
                if (bookingsB === 0) {
                    html += '<button class="page-action-btn delete dup-delete-btn" data-id="' + idB + '" data-cluster="' + ci + '">🗑 Delete ' + escHtml(nameB) + '</button>';
                } else {
                    html += '<button class="page-action-btn delete dup-reassign-btn" data-from="' + idB + '" data-to="' + idA + '" data-from-name="' + escAttr(nameB) + '" data-to-name="' + escAttr(nameA) + '" data-bookings="' + bookingsB + '">🔄 Re-assign &amp; Delete ' + escHtml(nameB) + '</button>';
                }

                html += '<button class="page-action-btn back dup-dismiss-btn" data-a="' + idA + '" data-b="' + idB + '" data-cluster="' + ci + '">🚫 Dismiss</button>';
                html += '</div>';
            }

            html += '</div>'; // .dup-cluster
        });
        $('#dup-result').html(html);
    }

    // Link as Household
    $(document).on('click', '.dup-link-btn', function () {
        var btn = $(this);
        var idA = btn.data('a');
        var idB = btn.data('b');
        btn.prop('disabled', true).text('Linking...');
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php?action=link',
            data: { id_a: idA, id_b: idB, link_type: 'household' },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    btn.text('✅ Linked').removeClass('primary').addClass('save');
                    $('#stat-linked').text(parseInt($('#stat-linked').text()) + 1);
                } else {
                    btn.prop('disabled', false).text('🔗 Link as Household');
                    alert(res.message || 'Failed to link.');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('🔗 Link as Household');
                alert('Error linking contacts.');
            }
        });
    });

    // Delete contact
    $(document).on('click', '.dup-delete-btn', function () {
        var btn = $(this);
        var id = btn.data('id');
        var cluster = btn.data('cluster');
        if (!confirm('Delete this contact? This cannot be undone.')) return;
        btn.prop('disabled', true).text('Deleting...');
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php?action=delete_contact',
            data: { id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('.dup-cluster[data-cluster="' + cluster + '"]').fadeOut(400, function () { $(this).remove(); });
                } else {
                    btn.prop('disabled', false).text('🗑 Delete');
                    alert(res.message || 'Failed to delete.');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('🗑 Delete');
                alert('Error deleting contact.');
            }
        });
    });

    // Re-assign & Delete (open modal)
    $(document).on('click', '.dup-reassign-btn', function () {
        var btn = $(this);
        var fromId   = btn.data('from');
        var toId     = btn.data('to');
        var fromName = btn.data('from-name');
        var toName   = btn.data('to-name');
        var bookings = btn.data('bookings');

        $('#reassign-confirm-text').text('Move ' + bookings + ' booking(s) from "' + fromName + '" to "' + toName + '" then delete "' + fromName + '"?');
        $('#reassign-modal').data('from', fromId).data('to', toId).data('cluster-btn', btn).show();
    });

    $('#reassign-cancel-btn').on('click', function () {
        $('#reassign-modal').hide();
    });

    $('#reassign-confirm-btn').on('click', function () {
        var modal  = $('#reassign-modal');
        var fromId = modal.data('from');
        var toId   = modal.data('to');
        var origBtn = modal.data('cluster-btn');
        var clusterEl = origBtn.closest('.dup-cluster');

        $(this).prop('disabled', true).text('Processing...');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php?action=reassign_and_delete',
            data: { from_id: fromId, to_id: toId },
            dataType: 'json',
            success: function (res) {
                modal.hide();
                $('#reassign-confirm-btn').prop('disabled', false).text('✅ Confirm & Delete');
                if (res.success) {
                    clusterEl.fadeOut(400, function () { $(this).remove(); });
                } else {
                    alert(res.message || 'Failed to reassign.');
                }
            },
            error: function () {
                modal.hide();
                $('#reassign-confirm-btn').prop('disabled', false).text('✅ Confirm & Delete');
                alert('Error during reassign.');
            }
        });
    });

    // Dismiss pair
    $(document).on('click', '.dup-dismiss-btn', function () {
        var btn     = $(this);
        var idA     = btn.data('a');
        var idB     = btn.data('b');
        var cluster = btn.data('cluster');
        btn.prop('disabled', true).text('Dismissing...');
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php?action=dismiss',
            data: { id_a: idA, id_b: idB },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('.dup-cluster[data-cluster="' + cluster + '"]').fadeOut(400, function () { $(this).remove(); });
                    $('#stat-dismissed').text(parseInt($('#stat-dismissed').text()) + 1);
                } else {
                    btn.prop('disabled', false).text('🚫 Dismiss');
                    alert(res.message || 'Failed to dismiss.');
                }
            },
            error: function () {
                btn.prop('disabled', false).text('🚫 Dismiss');
                alert('Error dismissing pair.');
            }
        });
    });

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = String(str || '');
        return d.innerHTML;
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
