<?php
$page_title = 'Add New Client';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Clients', 'url' => BASE_URL . '/modules/Clients/'],
    ['label' => 'Add'],
]);
include ROOT_DIR . '/includes/header.php';
?>

<form id="contactForm">
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
    <button type="submit" class="btn" id="submitBtn">
        👤 Add Client
    </button>
    <button type="submit" name="save_and_book" value="1" class="page-action-btn save">
        💾 Save & Create Booking
    </button>
</form>

<div id="loading" class="loading"></div>
<div id="result"></div>

<script>
$(document).ready(function() {
    $('#contactForm').on('submit', function(e) {
        e.preventDefault();
        var submitBtn = $('#submitBtn');
        var loading = $('#loading');
        var result = $('#result');
        submitBtn.hide();
        loading.show();
        result.html('');

        var isSaveAndBook = e.originalEvent.submitter && e.originalEvent.submitter.name === 'save_and_book';

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Clients/api/index.php?action=add',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                loading.hide();
                submitBtn.show();
                if (response.success) {
                    if (isSaveAndBook && response.contact_id) {
                        // Redirect to AddBooking with client pre-filled
                        window.location.href = '<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=' + response.contact_id + '&contact_name=' + encodeURIComponent($('#name').val());
                    } else {
                        result.html('<div class="success-message">' + response.message + '. Redirecting...</div>');
                        
                        // Redirect to clients list after 2 seconds
                        setTimeout(function() {
                            window.location.href = '<?= BASE_URL ?>/modules/Clients/?highlight=' + response.contact_id;
                        }, 2000);
                    }
                } else {
                    result.html('<div class="error-message">' + response.message + '</div>');
                }
            },
            error: function() {
                loading.hide();
                submitBtn.show();
                result.html('<div class="error-message">❌ An unexpected error occurred. Please try again.</div>');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>