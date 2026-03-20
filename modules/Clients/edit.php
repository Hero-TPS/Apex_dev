<?php
$page_title = 'Edit Client';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Clients', 'url' => BASE_URL . '/modules/Clients/'],
    ['label' => 'Edit'],
]);
include ROOT_DIR . '/includes/header.php';

$client_id = intval($_GET['id'] ?? 0);

if ($client_id <= 0) {
    echo '<div class="error-message">Invalid client ID</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}
?>

<div id="loading" class="loading" style="display: block;">Loading client data...</div>

<form id="editClientForm" style="display: none;">
    <input type="hidden" id="client_id" name="id" value="<?= $client_id ?>">
    
    <div class="form-group">
        <label for="name">Full Name <span class="required">*</span></label>
        <input type="text" id="name" name="name" required placeholder="Enter full name">
    </div>
    <div class="form-group">
        <label for="phone">Phone Number <span class="required">*</span></label>
        <input type="tel" id="phone" name="phone" required placeholder="e.g., 082 123 4567">
    </div>
    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="email@example.com">
    </div>
    <div class="form-group">
        <label for="address">Address</label>
        <textarea id="address" name="address" placeholder="Full address for pickup location"></textarea>
    </div>
    <div class="form-group">
        <label for="additionalInfo">Additional Information</label>
        <textarea id="additionalInfo" name="additionalInfo" placeholder="Any special notes, preferences, or instructions"></textarea>
    </div>
    
    <div class="action-buttons">
        <button type="submit" class="btn" id="submitBtn">
            💾 Update Client
        </button>
        <a href="<?= BASE_URL ?>/modules/Clients/" class="page-action-btn back">
            ← Back to Clients
        </a>
        <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=<?= $client_id ?>" class="page-action-btn edit">
            📅 View Bookings
        </a>
    </div>
</form>

<div id="result"></div>

<script>
$(document).ready(function() {
    var clientId = <?= $client_id ?>;
    var loading = $('#loading');
    var form = $('#editClientForm');
    var result = $('#result');

    // Load client data
    $.ajax({
        url: '<?= BASE_URL ?>/modules/Clients/api/index.php?action=get_single&id=' + clientId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            loading.hide();
            if (response.success && response.client) {
                var client = response.client;
                $('#name').val(client.name || '');
                $('#phone').val(client.phone || '');
                $('#email').val(client.email || '');
                $('#address').val(client.address || '');
                $('#additionalInfo').val(client.additional_info || '');
                form.show();
            } else {
                result.html('<div class="error-message">Client not found</div>');
            }
        },
        error: function() {
            loading.hide();
            result.html('<div class="error-message">Failed to load client data</div>');
        }
    });

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        var submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).text('Updating...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/api/index.php?action=update',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                submitBtn.prop('disabled', false).text('💾 Update Client');
                if (response.success) {
                    result.html('<div class="success-message">' + response.message + '. Redirecting...</div>');
                    setTimeout(function() {
                        window.location.href = '<?= BASE_URL ?>/modules/Clients/?highlight=' + clientId;
                    }, 2000);
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text('💾 Update Client');
                result.html('<div class="error-message">❌ An unexpected error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>