<?php
$page_title = 'Edit Client';
$page_subtitle = 'Edit Client';
$show_breadcrumb = true;
$breadcrumb = '> <a href="ClientsView.php">Clients View</a> > Edit Client';


require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';
include ROOT_DIR . '/includes/client_helpers.php'; 

$contact = null;
$error_message = '';

if (isset($_GET['id'])) {
    $contactId = intval($_GET['id']);
    if ($contactId > 0) {
        $contact = getContactById($pdo, $contactId);
        if (!$contact) {
            $error_message = "Contact not found.";
        }
    } else {
        $error_message = "Invalid contact ID.";
    }
} else {
    $error_message = "No contact ID provided.";
}
?>

<div class="form-container">
<?php if ($contact): ?>
    <form id="editContactForm">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($contact['id']); ?>">
        <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($contact['name']); ?>">
        </div>
        <div class="form-group">
            <label for="phone">Phone Number <span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" required value="<?php echo htmlspecialchars($contact['phone']); ?>">
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($contact['email']); ?>">
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($contact['address']); ?></textarea>
        </div>
        <div class="form-group">
            <label for="additionalInfo">Additional Information</label>
            <textarea id="additionalInfo" name="additionalInfo"><?php echo htmlspecialchars($contact['additional_info']); ?></textarea>
        </div>
        <button type="submit" class="page-action-btn save" id="submitBtn">💾 Update Contact</button>
    </form>
<?php else: ?>
    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>
    
<div id="loading" class="loading"></div>
<div id="result"></div>
</div>

<script>
$(document).ready(function () {
    $('#editContactForm').on('submit', function (e) {
        e.preventDefault();
        var submitBtn = $('#submitBtn');
        var loading = $('#loading');
        var result = $('#result');
        submitBtn.hide();
        loading.show();
        result.html('');
        $.ajax({
            type: 'POST',
            url: 'api/clients.php?action=update', // ✅ No leading slash
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                loading.hide();
                if (response.success) {
                    result.html('<div class="success-message">' + response.message + '</div>');
                    setTimeout(function () {
                        window.location.href = 'ClientsView.php';
                    }, 500);
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                    submitBtn.show();
                }
            },
            error: function () {
                loading.hide();
                submitBtn.show();
                result.html('<div class="error-message">❌ An unexpected error occurred.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>