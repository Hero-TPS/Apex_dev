<?php
// modules/Clients/index.php
$page_title = 'Clients';
$page_subtitle = 'Client Management';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Clients']]);
include ROOT_DIR . '/includes/header.php';

// Check for highlight parameter
$highlightClientId = $_GET['highlight'] ?? null;
?>
<!-- Summary Stats -->
<div id="client-stats" style="margin-bottom: 20px;"></div>

<!-- Booking Filter Buttons -->
<div class="view-filter-group">
    <button class="view-filter-btn active" data-filter="all">👥 All Clients</button>
    <button class="view-filter-btn" data-filter="with_bookings">📅 With Bookings</button>
    <button class="view-filter-btn" data-filter="without_bookings">🚫 Without Bookings</button>
    <button class="view-filter-btn" data-filter="archived">📦 Archived</button>
    <a id="downloadCsvBtn" href="#" class="csv-download-btn">⬇️ Download CSV</a>
</div>

<!-- Client Search -->
<div class="client-search-container">
    <input type="text" id="clientSearch" class="client-search-input"
        placeholder="🔍 Search clients by name, phone or address...">
    <div id="search-results-count"></div>
</div>

<!-- Notification Area -->
<div id="notification-area"></div>

<!-- Clients Table -->
<table class="bookings-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Address</th>
            <th>Additional Info</th>
            <th>Bookings</th>
            <th>Last Booking</th>
            <th class="wa-status-col" style="display:none;">WA Status</th>
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
        var currentFilter = 'all';

        // Build CSV download URL for the current filter
        function updateCsvLink() {
            $('#downloadCsvBtn').attr(
                'href',
                '<?= BASE_URL ?>/modules/Clients/api/index.php?action=get_csv&filter=' + currentFilter
            );
        }

        // Show/hide WA Status column (only visible in without_bookings view)
        function updateWaStatusColumn() {
            if (currentFilter === 'without_bookings') {
                $('.wa-status-col').show();
            } else {
                $('.wa-status-col').hide();
            }
        }

        // Filter button clicks
        $('.view-filter-btn').on('click', function () {
            $('.view-filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            updateCsvLink();
            updateWaStatusColumn();
            loadContacts();
        });

        // Set initial CSV link and column visibility
        updateCsvLink();
        updateWaStatusColumn();

        function buildGpsButtons(contact) {
            var hasGps = contact.pickup_lat && contact.pickup_lng;
            return hasGps
                ? '<button class="action-btn toggle gps-btn" data-id="' + contact.id + '">Update GPS</button>'
                : '<button class="action-btn save gps-btn" data-id="' + contact.id + '">Set GPS</button>';
        }

        // ── WA Status helpers ──

        var WA_STATUS_LABELS = {
            '': '📋 Not Sent',
            'sent': '📨 Sent',
            'positive': '✅ Positive'
        };
        var WA_STATUS_CSS = {
            '': 'not-sent',
            'sent': 'sent',
            'positive': 'positive'
        };

        function buildWaStatusBadge(status, sentDate) {
            var key = status || '';
            var label = WA_STATUS_LABELS[key] || key;
            var css = WA_STATUS_CSS[key] || 'not-sent';
            var html = '<span class="wa-status-badge ' + css + '">' + label + '</span>';
            if (sentDate) {
                var d = new Date(sentDate);
                var formatted = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                html += '<div class="wa-sent-date">📅 ' + formatted + '</div>';
            }
            return html;
        }

        // ======================================================
        // CLEANUP WA MESSAGE — edit the text below as needed

               function buildCleanupMessage(name) {
    return "Hi " + name + " 👋 André Matthews suggested I reach out to you.\n\n" +
        "I'm <?= e(BUSINESS_OWNER) ?> from <?= e(BUSINESS_NAME) ?> — I've taken over his personal transport services in the Helderberg area since he retired.\n\n" +
        "Just checking whether transport is something you still need from time to time. I'd be happy to assist if so.\n\n" +
        "This is a once-off message — I won't follow up unless I hear from you.\n\n" +
        "Kind regards,\n<?= e(BUSINESS_OWNER) ?>\n<?= e(BUSINESS_NAME) ?>";
}

        function loadContacts(highlightId = null) {
            var colspan = currentFilter === 'without_bookings' ? 7 : 6;
            tableBody.html('<tr><td colspan="' + colspan + '" style="text-align:center;">Loading clients...</td></tr>');
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php?action=get',
                data: { filter: currentFilter },
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

                            // WA Status cell and cleanup buttons (without_bookings view only)
                            var waStatusCell = '';
                            var waCleanupBtn = '';
                            var waStatusBtns = '';
                            if (currentFilter === 'without_bookings') {
                                waStatusCell = '<td data-label="WA Status" class="wa-status-col">' +
                                    buildWaStatusBadge(contact.wa_status, contact.wa_sent_date) + '</td>';

                                if (contact.whatsapp_phone && contact.wa_status !== 'sent' && contact.wa_status !== 'positive') {
                                    var cleanupMsg = buildCleanupMessage(contact.name);
                                    var cleanupHref = 'https://wa.me/' + contact.whatsapp_phone +
                                        '?text=' + encodeURIComponent(cleanupMsg);
                                    waCleanupBtn = '<a href="' + cleanupHref + '" target="_blank" ' +
                                        'class="action-btn wa-cleanup-btn" ' +
                                        'data-id="' + contact.id + '">WA Cleanup</a>';
                                }

                                waStatusBtns =
                                    (contact.wa_status !== 'positive' ? '<button class="action-btn wa-positive-btn" data-id="' + contact.id + '" data-status="positive">Positive</button>' : '');
                            }

                            var archiveBtn = contact.is_archived
                                ? '<button class="action-btn toggle archive-btn" data-id="' + contact.id + '" data-archived="1">♻️ Unarchive</button>'
                                : '<button class="action-btn archive-btn" data-id="' + contact.id + '" data-archived="0">📦 Archive</button>';

                            var row = '<tr class="' + rowClass + '" data-client-id="' + contact.id + '">' +
                                '<td data-label="Name">' + escapeHtml(contact.name) + gpsIndicator +
                                (contact.phone ? '<br><span class="client-subline">📞 ' + escapeHtml(contact.phone) + '</span>' : '') +
                                (contact.email ? '<br><span class="client-subline">✉️ ' + escapeHtml(contact.email) + '</span>' : '') +
                                '</td>' +
                                '<td data-label="Address">' + escapeHtml(contact.address || '') + '</td>' +
                                '<td data-label="Additional Info">' + escapeHtml(contact.additional_info || '') + '</td>' +
                                '<td data-label="Bookings">' + (contact.booking_count || 0) + '</td>' +
                                '<td data-label="Last Booking">' + (contact.last_booking_date ? (contact.last_booking_is_future ? '📅 Upcoming: ' + escapeHtml(contact.last_booking_date) : escapeHtml(contact.last_booking_date)) : '—') + '</td>' +
                                waStatusCell +
                                '<td data-label="Actions">' +
                                '<div class="actions-container">' +
                                (contact.booking_count > 0 ? '<a href="<?= BASE_URL ?>/modules/Clients/bookings.php?id=' + contact.id + '" class="action-btn view-details-btn">View Bookings</a>' : '') +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/add.php?contact_id=' + contact.id + '&contact_name=' + encodeURIComponent(contact.name) + '" class="action-btn add-booking-small">Add Booking</a>' +
                                '<a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=' + contact.id + '" class="action-btn edit-btn">Edit</a>' +
                                (contact.whatsapp_phone
                                    ? '<a href="' + waHref + '" target="_blank" class="action-btn whatsapp-btn" onclick="logWhatsAppSend(null, ' + contact.id + ', ' + JSON.stringify(waGreeting) + ', \'message\')">WhatsApp</a>'
                                    : '') +
                                waCleanupBtn +
                                buildGpsButtons(contact) +
                                waStatusBtns +
                                archiveBtn +
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
                                btn.text('Update GPS').removeClass('save').addClass('toggle');
                                var row = btn.closest('tr');
                                var nameCell = row.find('td[data-label="Name"]');
                                if (!nameCell.find('span[title="GPS set"]').length) {
                                    nameCell.append(' <span title="GPS set">📍</span>');
                                }
                                var c = allClients.find(function (x) { return x.id == clientId; });
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
                    btn.prop('disabled', false).text(btn.hasClass('toggle') ? 'Update GPS' : 'Set GPS');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });

        // ── WA Cleanup button: open WA and mark as 'sent' ──
        tableBody.on('click', '.wa-cleanup-btn', function () {
            var clientId = $(this).data('id');
            var row = $(this).closest('tr');
            updateWaStatus(clientId, 'sent', row);
        });

        // ── WA Status quick-update buttons ──
        tableBody.on('click', '.wa-positive-btn', function () {
            var btn = $(this);
            var clientId = btn.data('id');
            var newStatus = btn.data('status');
            var row = btn.closest('tr');
            updateWaStatus(clientId, newStatus, row);
        });

        function updateWaStatus(clientId, status, row) {
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php',
                data: {
                    action: 'update_wa_status',
                    id: clientId,
                    status: status
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        // Update badge in row
                        var statusCell = row.find('.wa-status-col');
                        statusCell.html(buildWaStatusBadge(status, res.wa_sent_date || null));

                        // Hide buttons based on new status
                        if (status === 'sent' || status === 'positive') {
                            row.find('.wa-cleanup-btn').hide();
                        }
                        if (status === 'positive') {
                            row.find('.wa-positive-btn').hide();
                        }

                        // Update cached client object
                        var c = allClients.find(function (x) { return x.id == clientId; });
                        if (c) {
                            c.wa_status = status;
                            if (res.wa_sent_date) { c.wa_sent_date = res.wa_sent_date; }
                        }
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                    }
                },
                error: function () {
                    showNotification('✗ Failed to update WA status.', 'error');
                }
            });
        }

        // ── Archive / Unarchive ──
        tableBody.on('click', '.archive-btn', function () {
            var btn = $(this);
            var clientId = btn.data('id');
            var isArchived = btn.data('archived') == 1;

            if (!isArchived) {
                if (!confirm('Archive this client? Any future bookings for them will be deleted (past bookings are kept for records).')) {
                    return;
                }
            }

            btn.prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Clients/api/index.php',
                data: {
                    action: 'toggle_archive',
                    id: clientId
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showNotification('✓ ' + res.message, 'success');
                        // The client no longer belongs in the current filter's view either way
                        // (moved between active <-> archived), so just reload the list.
                        loadContacts();
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                        btn.prop('disabled', false);
                    }
                },
                error: function () {
                    showNotification('❌ Failed to update client.', 'error');
                    btn.prop('disabled', false);
                }
            });
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
                $('#search-results-count').text('');
                return;
            }

            var words = searchText.split(/\s+/).filter(w => w.length > 0);

            var visibleCount = 0;

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
                if (match) visibleCount++;
            });

            $('#search-results-count').text(visibleCount + ' result' + (visibleCount !== 1 ? 's' : ''));

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
