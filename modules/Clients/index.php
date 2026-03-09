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
<!-- Summary Stats -->
<div id="client-stats" style="margin-bottom: 20px;"></div>

<!-- Booking Filter Toggle -->
<button id="toggleBookingsFilter" class="toggle-btn">
    👁️ Show Only Clients With Bookings
</button>

<!-- Client Search -->
<div class="client-search-container">
    <input type="text" id="clientSearch" class="client-search-input"
        placeholder="🔍 Search clients by name, phone or address...">
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
        var showOnlyWithBookings = false;

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

                        // ✅ ADD: Display summary stats
                        var totalClients = response.contacts.length;
                        var clientsWithBookings = response.contacts.filter(c => c.booking_count > 0).length;
                        var totalBookings = response.contacts.reduce((sum, c) => sum + (c.booking_count || 0), 0);

                        $('#client-stats').html(`
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">${totalClients}</div>
                    <div class="stat-label">Total Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${clientsWithBookings}</div>
                    <div class="stat-label">Clients with Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${totalBookings}</div>
                    <div class="stat-label">Total Bookings</div>
                </div>
            </div>
        `);

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
                                '<a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=' + contact.id + '" class="action-btn view-details-btn">View Bookings</a>' +
                                '<a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=' + contact.id + '" class="action-btn edit-btn">Edit</a>' +
                                '<button class="action-btn whatsapp-btn" onclick="openCustomWhatsApp(\'' + escapeJs(contact.name) + '\', \'' + escapeJs(contact.phone || \'\') + '\', \'Hi \' + \'' + escapeJs(contact.name) + '\' + \',\\n\')">Send Msg</button>' +
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

        // Search functionality
        $('#clientSearch').on('keyup', function () {
            var searchText = $(this).val().toLowerCase().trim();

            if (searchText.length === 0) {
                tableBody.find('tr').show();
                return;
            }

            // Split into words for fuzzy "all words must match" search
            var words = searchText.split(/\s+/).filter(w => w.length > 0);

            tableBody.find('tr').each(function () {
                var row = $(this);
                var clientId = parseInt(row.data('client-id'));
                var client = allClients.find(c => c.id === clientId);
                if (!client) { row.hide(); return; }

                // Build searchable string from relevant fields only
                var haystack = [
                    client.name || '',
                    client.phone || '',
                    client.address || '',
                    client.email || '',
                    client.additional_info || ''
                ].join(' ').toLowerCase();

                // All words must be found somewhere in the haystack
                var match = words.every(function (word) {
                    return haystack.indexOf(word) > -1;
                });

                row.toggle(match);
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

        function escapeJs(text) {
            return (text || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '');
        }
    });

    function openCustomWhatsApp(name, phone, prefill) {
        $('#waModalClientName').text(name);
        $('#waModalPhone').text(phone);
        $('#waModalMessage').val(prefill);
        var cleanPhone = (phone || '').replace(/\D/g, '');
        if (cleanPhone.charAt(0) === '0') { cleanPhone = '27' + cleanPhone.substring(1); }
        $('#waModalSendBtn').attr('href', 'https://wa.me/' + cleanPhone + '?text=');
        $('#customWhatsAppModal').css('display', 'flex');
        $('#waModalMessage').focus();
    }

    function onWaModalSend() {
        var msg = $('#waModalMessage').val();
        var currentHref = $('#waModalSendBtn').attr('href');
        var base = currentHref.split('?text=')[0];
        $('#waModalSendBtn').attr('href', base + '?text=' + encodeURIComponent(msg));
        return true;
    }

    function logWhatsAppSend() {
        // fire-and-forget log (no booking_id context on this page)
    }
</script>

<!-- Custom WhatsApp Modal -->
<div id="customWhatsAppModal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="max-width:480px; text-align:left;">
        <h3>💬 Send Message to <span id="waModalClientName"></span></h3>
        <p style="color:#666; font-size:0.9em;">📱 <span id="waModalPhone"></span></p>
        <div class="form-group" style="margin-top:15px;">
            <label for="waModalMessage">Message:</label>
            <textarea id="waModalMessage" rows="6" placeholder="Type your message here..." style="width:100%; box-sizing:border-box;"></textarea>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:15px;">
            <button id="waModalCancelBtn" class="page-action-btn delete" style="min-width:80px;">Cancel</button>
            <a id="waModalSendBtn" href="#" target="_blank" class="page-action-btn whatsapp" style="min-width:180px;" onclick="return onWaModalSend()">Send via WhatsApp 💬</a>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#waModalCancelBtn').on('click', function () {
            $('#customWhatsAppModal').hide();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') { $('#customWhatsAppModal').hide(); }
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>