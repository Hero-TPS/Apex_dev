<?php
$page_title = 'Clients';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;
$breadcrumb = ' > Clients';

require_once __DIR__ . '/../../config.php';
include ROOT_DIR . '/includes/header.php';

// Check for highlight parameter
$highlightClientId = $_GET['highlight'] ?? null;
?>

<!-- Booking Filter Toggle -->
<button id="toggleBookingsFilter" class="toggle-btn">
    👁️ Show Only Clients With Bookings
</button>

<!-- Client Search with Suggestions -->
<div class="client-search-container">
    <input 
        type="text" 
        id="clientSearch" 
        class="client-search-input"
        placeholder="🔍 Search clients by name, phone or address...">
    <div id="clientSuggestions" class="suggestions-box"></div>
</div>

<!-- Notification Area -->
<div id="notification-area"></div>

<!-- Clients Table -->
<table class="bookings-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Address</th>
            <th>Additional Info</th>
            <th>Bookings</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="clients-table-body">
        <!-- Filled by AJAX -->
    </tbody>
</table>

<!-- No Clients Message -->
<div id="no-clients-message" class="no-bookings" style="display: none;">
    <h3>📋 No clients found</h3>
    <p>
        <a href="<?= BASE_URL ?>/modules/Clients/add.php" class="btn" style="width: auto; padding: 10px 20px;">
            + Add Your First Client
        </a>
    </p>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Are you sure?</h3>
        <p>This will permanently delete the contact. This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var tableBody = $('#clients-table-body');
        var noClientsMessage = $('#no-clients-message');
        var notificationArea = $('#notification-area');
        var modal = $('#deleteConfirmationModal');
        var contactIdToDelete = null;
        var allClients = [];
        
        // Booking filter state
        var showOnlyWithBookings = false;

        // Toggle button logic
        $('#toggleBookingsFilter').on('click', function () {
            showOnlyWithBookings = !showOnlyWithBookings;
            $(this).text(showOnlyWithBookings 
                ? '👁️ Show All Clients' 
                : '👁️ Show Only Clients With Bookings'
            );
            loadContacts();
        });

        function loadContacts(highlightId = null) {
            tableBody.html('<tr><td colspan="7" style="text-align:center;">Loading clients...</td></tr>');
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php?action=get',
                data: { only_with_bookings: showOnlyWithBookings ? 1 : 0 },
                dataType: 'json',
                success: function (response) {
                    tableBody.empty();
                    if (response.success && response.contacts.length > 0) {
                        allClients = response.contacts;
                        response.contacts.forEach(function (contact) {
                            var rowClass = (highlightId && contact.id == highlightId) ? 'highlight-row' : '';
                            var row = '<tr class="' + rowClass + '" data-client-id="' + contact.id + '">' +
                                '<td data-label="Name">' + escapeHtml(contact.name) + '</td>' +
                                '<td data-label="Phone">' + escapeHtml(contact.phone || '') + '</td>' +
                                '<td data-label="Email">' + escapeHtml(contact.email || '') + '</td>' +
                                '<td data-label="Address">' + escapeHtml(contact.address || '') + '</td>' +
                                '<td data-label="Additional Info">' + escapeHtml(contact.additional_info || '') + '</td>' +
                                '<td data-label="Bookings">' + (contact.booking_count || 0) + '</td>' +
                                '<td data-label="Actions">' +
                                '<div class="actions-container">' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=' + contact.id + '&contact_name=' + encodeURIComponent(contact.name) + '" class="action-btn view-details-btn">Book</a>' +
                                '<button class="action-btn edit-btn" data-id="' + contact.id + '">Edit</button>' +
                                '<button class="action-btn delete-btn" data-id="' + contact.id + '">Delete</button>' +
                                '</div>' +
                                '</td>' +
                                '</tr>';
                            tableBody.append(row);
                        });
                        $('.bookings-table').show();
                        noClientsMessage.hide();
                    } else {
                        noClientsMessage.show();
                        $('.bookings-table').hide();
                    }
                },
                error: function () {
                    tableBody.empty();
                    showNotification('❌ Could not load clients', 'error');
                }
            });
        }

        // Initial load
        loadContacts(<?= json_encode($highlightClientId) ?>);

        // Delete button
        tableBody.on('click', '.delete-btn', function () {
            contactIdToDelete = $(this).data('id');
            modal.css('display', 'flex');
        });

        $('#confirmDeleteBtn').on('click', function () {
            if (!contactIdToDelete) return;

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php',
                data: {
                    action: 'delete',
                    id: contactIdToDelete
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('tr[data-client-id="' + contactIdToDelete + '"]').fadeOut(function () {
                            $(this).remove();
                            if (tableBody.find('tr').length === 0) {
                                loadContacts();
                            }
                        });
                        showNotification('✓ ' + response.message, 'success');
                    } else {
                        showNotification('✗ ' + response.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to delete contact', 'error');
                },
                complete: function () {
                    modal.hide();
                    contactIdToDelete = null;
                }
            });
        });

        $('#cancelDeleteBtn').on('click', function () {
            modal.hide();
            contactIdToDelete = null;
        });

        // Edit button - redirect to edit page
        tableBody.on('click', '.edit-btn', function () {
            var clientId = $(this).data('id');
            window.location.href = '<?= BASE_URL ?>/modules/Clients/edit.php?id=' + clientId;
        });

        // Search functionality
        $('#clientSearch').on('keyup', function () {
            var searchText = $(this).val().toLowerCase();
            
            if (searchText.length === 0) {
                tableBody.find('tr').show();
                return;
            }

            tableBody.find('tr').each(function () {
                var row = $(this);
                var text = row.text().toLowerCase();
                row.toggle(text.indexOf(searchText) > -1);
            });
        });

        function showNotification(message, type) {
            var className = type === 'success' ? 'success-message' : 'error-message';
            var notification = $('<div class="' + className + '">' + message + '</div>');
            notificationArea.html(notification);
            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>