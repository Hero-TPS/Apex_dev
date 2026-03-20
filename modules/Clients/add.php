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
        <input type="text" id="client-name" name="name" required placeholder="Enter full name">
    </div>
    <div class="form-group">
        <label for="phone">Phone Number <span class="required">*</span></label>
        <input type="tel" id="client-phone" name="phone" required placeholder="e.g., 082 123 4567">
        <div id="duplicate-warning" class="error-message" style="display:none;"></div>
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
                        window.location.href = '<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=' + response.contact_id + '&contact_name=' + encodeURIComponent($('#client-name').val());
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

    // Duplicate warning on blur
    function checkDuplicates() {
        var name  = $('#client-name').val().trim();
        var phone = $('#client-phone').val().trim();
        if (!name && !phone) {
            $('#duplicate-warning').hide();
            return;
        }
        $.ajax({
            url: '<?= BASE_URL ?>/modules/Clients/duplicates/api/index.php',
            data: { action: 'check_duplicate', name: name, phone: phone },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.matches && res.matches.length > 0) {
                    var html = '⚠️ Similar client(s) found: ';
                    var parts = [];
                    $.each(res.matches, function (i, m) {
                        parts.push('<strong>' + escHtml(m.name) + '</strong> — ' + escHtml(m.phone || '') + ' — ' + escHtml(m.address || '') +
                            ' <a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=' + parseInt(m.id) + '" target="_blank">[View]</a>');
                    });
                    html += parts.join(' &nbsp;|&nbsp; ');
                    $('#duplicate-warning').html(html).show();
                } else {
                    $('#duplicate-warning').hide();
                }
            }
        });
    }

    $('#client-name, #client-phone').on('blur', checkDuplicates);

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = String(str || '');
        return d.innerHTML;
    }
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>