<?php
// modules/clients/index.php
$page_title = 'Clients';
$page_subtitle = 'Clients';
$show_breadcrumb = true;
$breadcrumb = ' > Clients';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';
?>

<div class="content">
    <h2>Clients</h2>

    <div style="margin-bottom: 12px;">
        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/clients/add.php" class="btn">+ Add Client</a>
    </div>

    <table class="clients-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Address</th>
                <th>Bookings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="clients-table-body">
            <tr><td colspan="6" style="text-align:center;">Loading clients...</td></tr>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function () {
    const body = $('#clients-table-body');

    function loadClients() {
        body.html('<tr><td colspan="6" style="text-align:center;">Loading clients...</td></tr>');
        $.ajax({
            url: '<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/clients/api/index.php?action=get',
            dataType: 'json',
            success: function (resp) {
                body.empty();
                if (resp.success && resp.contacts && resp.contacts.length > 0) {
                    resp.contacts.forEach(function (c) {
                        const row = `
                            <tr id="client-${c.id}">
                                <td>${escapeHtml(c.name)}</td>
                                <td>${escapeHtml(c.phone)}</td>
                                <td>${escapeHtml(c.email || '')}</td>
                                <td>${escapeHtml(c.address || '')}</td>
                                <td>${c.booking_count ?? 0}</td>
                                <td>
                                    <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/clients/detail.php?id=${c.id}" class="action-btn">View</a>
                                    <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/clients/edit.php?id=${c.id}" class="action-btn">Edit</a>
                                    <button class="action-btn delete-btn" data-id="${c.id}">Delete</button>
                                </td>
                            </tr>
                        `;
                        body.append(row);
                    });
                } else {
                    body.html('<tr><td colspan="6" style="text-align:center;">No clients found.</td></tr>');
                }
            },
            error: function (xhr) {
                body.html('<tr><td colspan="6" class="error-message">Failed to load clients.</td></tr>');
            }
        });
    }

    // Delete handler (posts to API)
    $(document).on('click', '.delete-btn', function () {
        if (!confirm('Delete this client? This action cannot be undone.')) return;
        const id = $(this).data('id');
        $.ajax({
            type: 'POST',
            url: '<?= defined('BASE_URL') ? BASE_URL : '' ?>/modules/clients/api/index.php?action=delete',
            data: { id: id },
            dataType: 'json',
            success: function (resp) {
                if (resp.success) {
                    $('#client-' + id).remove();
                } else {
                    alert('Delete failed: ' + (resp.message || 'Unknown error'));
                }
            },
            error: function () {
                alert('Network error while deleting client.');
            }
        });
    });

    function escapeHtml(str) {
        return (str || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    loadClients();
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>