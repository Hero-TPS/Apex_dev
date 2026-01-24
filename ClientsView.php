<?php
// ClientsView.php

$page_title = 'View Clients';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;
$breadcrumb = ' > View Clients';

include 'includes/header.php';

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
    <p>Add your first client using the "Add New Contact" button on the dashboard.</p>
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
        
        // 🔽 Booking filter state
        var showOnlyWithBookings = false;

        // 🔽 Toggle button logic
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
                url: 'api/clients.php?action=get',
                // ✅ Send filter state
                data: { only_with_bookings: showOnlyWithBookings ? 1 : 0 },
                dataType: 'json',
                success: function (response) {
                    tableBody.empty();
                    if (response.success && response.contacts.length > 0) {
                        allClients = response.contacts;
                        allClients.sort((a, b) => a.name.localeCompare(b.name));
                        renderContacts(allClients);
                        noClientsMessage.hide();
                        $('.bookings-table').show();

                        // 🔽 Scroll to highlighted client AFTER data loads
                        if (highlightId) {
                            var row = $('#contact-row-' + highlightId);
                            if (row.length) {
                                $('html, body').animate({
                                    scrollTop: row.offset().top - 100
                                }, 500);
                                row.addClass('highlight-row');
                                setTimeout(() => row.removeClass('highlight-row'), 2500);
                            }
                        }
                    } else {
                        noClientsMessage.show();
                        $('.bookings-table').hide();
                    }
                },
                error: function () {
                    tableBody.empty();
                    showNotification('❌ Could not load contacts.', 'error');
                }
            });
        }

        function renderContacts(contacts) {
            tableBody.empty();
            $.each(contacts, function (index, contact) {
                // 🔹 Booking cell
                var bookingCell = '';
                if (contact.booking_count > 0) {
                    bookingCell = '<a href="ClientBookings.php?contact_id=' + contact.id + '" class="action-btn view-details-btn">View (' + contact.booking_count + ')</a>';
                } else {
                    bookingCell = '<span style="color:#888;">0</span>';
                }

                var row = '<tr id="contact-row-' + contact.id + '" data-name="' + contact.name.toLowerCase() + '">' +
                    '<td data-label="Name">' + escapeHtml(contact.name) + '</td>' +
                    '<td data-label="Phone">' + escapeHtml(contact.phone) + '</td>' +
                    '<td data-label="Email">' + escapeHtml(contact.email || '') + '</td>' +
                    '<td data-label="Address">' + escapeHtml(contact.address || '') + '</td>' +
                    '<td data-label="Additional Info">' + escapeHtml(contact.additional_info || '') + '</td>' +
                    '<td data-label="Bookings">' + bookingCell + '</td>' +
                    '<td data-label="Actions">' +
                    '<div class="actions-container">' +
                    '<a href="AddBooking.php?contact_id=' + contact.id + '&contact_name=' + encodeURIComponent(contact.name) + '" class="action-btn add-booking-small">Add Booking</a>' +
                    '<a href="EditContactForm.php?id=' + contact.id + '" class="action-btn edit-btn">Edit</a>' +
                    '<button class="action-btn delete-btn" data-contact-id="' + contact.id + '">Delete</button>' +
                    '</div>' +
                    '</td>' +
                    '</tr>';
                tableBody.append(row);
            });
        }

        // 🔹 Search with suggestions
        var selectedSuggestion = -1;
        $('#clientSearch').on('input focus', function () {
            var query = $(this).val().trim().toLowerCase();
            if (query === '') {
                $('#clientSuggestions').hide();
                return;
            }

            var filtered = allClients.filter(c =>
                (c.name && c.name.toLowerCase().includes(query)) ||
                (c.phone && c.phone.toLowerCase().includes(query)) ||
                (c.address && c.address.toLowerCase().includes(query))
            );

            $('#clientSuggestions').empty();
            if (filtered.length > 0) {
                selectedSuggestion = -1;
                filtered.forEach(client => {
                    var item = $(`<div class="suggestion-item">
                        ${escapeHtml(client.name)}<br>
                        <small>${escapeHtml(client.phone || '')}</small>
                        <small>${escapeHtml(client.address || '')}</small>
                    </div>`);
                    item.data('client', client);
                    item.on('click', function () {
                        selectClient(client);
                    });
                    $('#clientSuggestions').append(item);
                });
                $('#clientSuggestions').show();
            } else {
                $('#clientSuggestions').html('<div class="suggestion-item">No clients found</div>').show();
            }
        });

        // Keyboard navigation
        $('#clientSearch').on('keydown', function (e) {
            var items = $('#clientSuggestions .suggestion-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedSuggestion = Math.min(selectedSuggestion + 1, items.length - 1);
                highlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedSuggestion = Math.max(selectedSuggestion - 1, -1);
                highlight(items);
            } else if (e.key === 'Enter' && selectedSuggestion >= 0) {
                e.preventDefault();
                var client = items.eq(selectedSuggestion).data('client');
                if (client) selectClient(client);
            } else if (e.key === 'Escape') {
                $('#clientSuggestions').hide();
            }
        });

        function highlight(items) {
            items.removeClass('active');
            if (selectedSuggestion >= 0) {
                items.eq(selectedSuggestion).addClass('active');
            }
        }

        function selectClient(client) {
            $('#clientSearch').val(client.name);
            $('#clientSuggestions').hide();
            var row = $('#contact-row-' + client.id);
            if (row.length) {
                $('html, body').animate({ scrollTop: row.offset().top - 150 }, 300);
                row.addClass('highlight-row');
                setTimeout(() => row.removeClass('highlight-row'), 2000);
            }
        }

        // Hide suggestions on outside click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#clientSearch, #clientSuggestions').length) {
                $('#clientSuggestions').hide();
            }
        });

        // --- Delete Logic ---
        tableBody.on('click', '.delete-btn', function () {
            contactIdToDelete = $(this).data('contact-id');
            modal.show();
        });

        $('#cancelDeleteBtn').on('click', function () {
            modal.hide();
            contactIdToDelete = null;
        });

        $('#confirmDeleteBtn').on('click', function () {
            var confirmBtn = $(this);
            confirmBtn.text('Deleting...').prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: 'api/clients.php?action=delete',
                // ✅ 'data' is explicitly present
                data: { id: contactIdToDelete },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#contact-row-' + contactIdToDelete).fadeOut('slow', function () {
                            $(this).remove();
                            if (tableBody.find('tr').length === 0) {
                                loadContacts();
                            }
                        });
                        showNotification(response.message, 'success');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ An unexpected error occurred while deleting.', 'error');
                },
                complete: function () {
                    modal.hide();
                    confirmBtn.text('Yes, Delete').prop('disabled', false);
                    contactIdToDelete = null;
                }
            });
        });

        // Helpers
        function showNotification(message, type) {
            var messageDiv = $('<div class=" ' + (type === 'success' ? 'success-message' : 'error-message') + '">' + message + '</div>');
            notificationArea.html(messageDiv);
            setTimeout(function () {
                messageDiv.fadeOut('slow', function () { $(this).remove(); });
            }, 5000);
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        // Initial load — pass highlight ID if exists
        var initialHighlightId = <?= $highlightClientId ? (int)$highlightClientId : 'null' ?>;
        loadContacts(initialHighlightId);
    });
</script>

<?php include 'includes/footer.php'; ?>