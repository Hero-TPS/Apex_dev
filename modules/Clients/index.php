<?php
$page_title = 'Clients';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Clients']]);
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

        function buildGpsButtons(contact) {
            var hasGps = contact.pickup_lat && contact.pickup_lng;
            return hasGps
                ? '<button class="action-btn toggle gps-btn" data-id="' + contact.id + '">📍 Update GPS</button>'
                : '<button class="action-btn save gps-btn" data-id="' + contact.id + '">📍 Set GPS</button>';
        }

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
                            var waGreeting = 'Hi ' + contact.name;
                            var waHref = contact.whatsapp_phone
                                ? 'https://wa.me/' + contact.whatsapp_phone + '?text=' + encodeURIComponent(waGreeting)
                                : '#';

                            var gpsIndicator = (contact.pickup_lat && contact.pickup_lng)
                                ? ' <span title="GPS set">📍</span>'
                                : '';

                            var rowClass = (highlightId && contact.id == highlightId) ? 'highlight-row' : '';
                            var row = '<tr class="' + rowClass + '" data-client-id="' + contact.id + '">' +
                                '<td data-label="Name">' + escapeHtml(contact.name) + gpsIndicator + '</td>' +
                                '<td data-label="Phone">' + escapeHtml(contact.phone || '') + '</td>' +
                                '<td data-label="Email">' + escapeHtml(contact.email || '') + '</td>' +
                                '<td data-label="Address">' + escapeHtml(contact.address || '') + '</td>' +
                                '<td data-label="Additional Info">' + escapeHtml(contact.additional_info || '') + '</td>' +
                                '<td data-label="Bookings">' + (contact.booking_count || 0) + '</td>' +
                                '<td data-label="Actions">' +
                                '<div class="actions-container">' +
                                (contact.booking_count > 0 ? '<a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=' + contact.id + '" class="action-btn view-details-btn">View Bookings</a>' : '') +
                                '<a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=' + contact.id + '" class="action-btn edit-btn">Edit</a>' +
                                (contact.whatsapp_phone
                                    ? '<a href="' + waHref + '" target="_blank" class="action-btn whatsapp-btn" onclick="logWhatsAppSend(null, ' + contact.id + ', ' + JSON.stringify(waGreeting) + ', \'message\')">WhatsApp</a>'
                                    : '') +
                                buildGpsButtons(contact) +
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

        // ── GPS: Set / Update ──
        tableBody.on('click', '.gps-btn', function () {
            var btn = $(this);
            var clientId = btn.data('id');

            if (!navigator.geolocation) {
                showNotification('✗ Geolocation not supported by this browser.', 'error');
                return;
            }

            btn.prop('disabled', true).text('Getting GPS…');

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Clients/api/index.php',
                        type: 'POST',
                        data: {
                            action: 'save_pickup_gps',
                            id: clientId,
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        },
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                showNotification('✓ GPS saved for client.', 'success');
                                btn.text('📍 Update GPS').removeClass('save').addClass('toggle');
                                var row = btn.closest('tr');
                                var nameCell = row.find('td[data-label="Name"]');
                                if (!nameCell.find('span[title="GPS set"]').length) {
                                    nameCell.append(' <span title="GPS set">📍</span>');
                                }
                                var c = allClients.find(function(x) { return x.id == clientId; });
                                if (c) { c.pickup_lat = position.coords.latitude; c.pickup_lng = position.coords.longitude; }
                            } else {
                                showNotification('✗ ' + res.message, 'error');
                            }
                        },
                        error: function () {
                            showNotification('✗ Failed to save GPS.', 'error');
                        },
                        complete: function () {
                            btn.prop('disabled', false);
                        }
                    });
                },
                function (error) {
                    var msg = 'Could not get location.';
                    if (error.code === error.PERMISSION_DENIED) msg = 'Location permission denied.';
                    else if (error.code === error.POSITION_UNAVAILABLE) msg = 'Location unavailable.';
                    else if (error.code === error.TIMEOUT) msg = 'Location request timed out.';
                    showNotification('✗ ' + msg, 'error');
                    btn.prop('disabled', false).text(btn.hasClass('toggle') ? '📍 Update GPS' : '📍 Set GPS');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });

        // ── Delete ──
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
                error: function (xhr) {
                    var msg = '❌ Failed to delete contact';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = '✗ ' + xhr.responseJSON.message;
                    }
                    showNotification(msg, 'error');
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

            var words = searchText.split(/\s+/).filter(w => w.length > 0);

            tableBody.find('tr').each(function () {
                var row = $(this);
                var clientId = parseInt(row.data('client-id'));
                var client = allClients.find(c => c.id === clientId);
                if (!client) { row.hide(); return; }

                var haystack = [
                    client.name || '',
                    client.phone || '',
                    client.address || '',
                    client.email || '',
                    client.additional_info || ''
                ].join(' ').toLowerCase();

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
    });

    function logWhatsAppSend(bookingId, contactId, messageContent, messageType) {
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
            data: {
                action: 'log_whatsapp',
                booking_id: bookingId,
                contact_id: contactId,
                message_type: messageType || 'message',
                message_content: messageContent,
                sent_by: 'user'
            },
            dataType: 'json'
        });
    }
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>