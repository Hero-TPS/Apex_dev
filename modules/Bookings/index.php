<?php
// BookingsView.php
$page_title = 'Bookings';
$page_subtitle = 'Bookings';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([['label' => 'Bookings']]);
include ROOT_DIR . '/includes/header.php';
?>

<!-- Dynamic Heading -->
<h2 id="bookings-heading">Upcoming Bookings</h2>

<!-- Toggle Button -->
<button id="toggleBookingsBtn" class="page-action-btn primary">Show All</button>

<!-- Notification Area -->
<div id="notification-area"></div>

<!-- Bookings Table -->
<table class="bookings-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Client</th>
            <th>Pickup</th>
            <th>Destination</th>
            <th>Cost</th>
            <th>Distance</th>
            <th>Calc. Cost</th>
            <th>Driver</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="bookings-table-body">
        <!-- Filled by AJAX -->
    </tbody>
</table>

<!-- No Bookings Message -->
<div id="no-bookings-message" class="no-bookings" style="display: none;">
    <h3>📋 No bookings found</h3>
    <p>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php" class="page-action-btn primary">+ Create a Booking</a>
    </p>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Are you sure?</h3>
        <p>This will permanently delete the booking. This action cannot be undone.</p>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var tableBody = $('#bookings-table-body');
        var noBookingsMessage = $('#no-bookings-message');
        var notificationArea = $('#notification-area');
        var modal = $('#deleteConfirmationModal');
        var bookingIdToDelete = null;
        var showAll = false;

        function loadBookings() {
            tableBody.html('<tr><td colspan="10" style="text-align:center;">Loading bookings...</td></tr>');
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: { show: showAll ? 'all' : 'upcoming' },
                dataType: 'json',
                success: function (response) {
                    tableBody.empty();
                    if (response.success && response.bookings.length > 0) {
                        $.each(response.bookings, function (index, booking) {
                            var rowClass = booking.is_overdue ? 'booking-overdue' : '';
                            var waGreeting = 'Hi ' + booking.client_name;
                            var row =
                                '<tr id="booking-row-' + booking.id + '" ' +
                                'class="' + rowClass + '" ' +
                                'data-name="' + escapeHtml(booking.client_name.toLowerCase()) + '" ' +
                                'data-date="' + escapeHtml(booking.trip_date_raw) + '">' +
                                '<td data-label="Date">' + escapeHtml(booking.trip_date) + '</td>' +
                                '<td data-label="Time">' + escapeHtml(booking.start_time) + '</td>' +
                                '<td data-label="Client">' + escapeHtml(booking.client_name) + '</td>' +
                                '<td data-label="Pickup">' + escapeHtml(booking.pickup_location) + '</td>' +
                                '<td data-label="Destination">' + escapeHtml(booking.destination) + '</td>' +
                                '<td data-label="Cost">' + escapeHtml(booking.cost) + (booking.payment_received ? ' <span title="Payment received">✅</span>' : '') + '</td>' +
                                '<td data-label="Distance">' + (booking.distance ? escapeHtml(booking.distance) : '<span style="color:#aaa;">—</span>') + '</td>' +
                                '<td data-label="Calc. Cost">' + (booking.calculated_cost ? escapeHtml(booking.calculated_cost) : '<span style="color:#aaa;">—</span>') + '</td>' +
                                '<td data-label="Driver">' + (booking.driver_name ? escapeHtml(booking.driver_name) : '<span style="color:#aaa;">—</span>') + '</td>' +
                                '<td data-label="Actions">' +
                                '<div class="actions-container">' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=' + booking.id + '" class="action-btn view-details-btn">View</a>' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=' + booking.id + '" class="action-btn edit-btn">Edit</a>' +
                                (booking.client_phone
                                    ? '<a href="https://wa.me/' + booking.client_phone + '?text=' + encodeURIComponent(waGreeting + '\n') + '" target="_blank" class="action-btn whatsapp-btn" onclick="logWhatsAppSend(' + booking.id + ', ' + booking.contact_id + ', ' + JSON.stringify(waGreeting) + ', \'message\')">Send Msg</a>'
                                    : '') +
                                '<button class="action-btn delete-btn" data-id="' + booking.id + '">Delete</button>' 

                            // Status button logic
                            var showStatusButton = false;
                            var statusButtonText = '';
                            if (booking.status === 'completed') {
                                if (booking.is_today) {
                                    showStatusButton = true;
                                    statusButtonText = 'Undo Done';
                                }
                            } else {
                                if (booking.is_today || booking.is_past) {
                                    showStatusButton = true;
                                    statusButtonText = 'Mark Done';
                                }
                            }

                            if (showStatusButton) {
                                row += '<button class="action-btn status-toggle-btn ' +
                                    (booking.status === 'completed' ? 'completed' : '') + '" ' +
                                    'data-id="' + booking.id + '" ' +
                                    'data-status="' + booking.status + '">' +
                                    escapeHtml(statusButtonText) +
                                    '</button>';
                            }

                            row += '</div>' +
                                (booking.is_overdue ? '<div class="overdue-label">⚠️ Overdue</div>' : '') +
                                '</td>' +
                                '</tr>';
                            tableBody.append(row);
                        });
                        $('.bookings-table').show();
                        noBookingsMessage.hide();

                        if (showAll) {
                            scrollToToday();
                        }
                    } else {
                        noBookingsMessage.show();
                        $('.bookings-table').hide();
                    }
                },
                error: function (xhr, status, error) {
                    tableBody.empty();
                    let errorMsg = 'Failed to load bookings';

                    // Try to parse JSON error response
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch (e) {
                        // If not JSON, use status text
                        errorMsg = xhr.statusText || error || 'Unknown error occurred';
                    }

                    // Log to console for debugging
                    console.error('Booking load error:', {
                        status: xhr.status,
                        error: errorMsg,
                        response: xhr.responseText
                    });

                    showNotification('❌ ' + errorMsg, 'error');
                }
            });
        }

        function scrollToToday() {
            var today = new Date().toISOString().split('T')[0];
            var $targetRow = tableBody.find('tr[data-date="' + today + '"]').first();
            if (!$targetRow.length) {
                tableBody.find('tr').each(function () {
                    if ($(this).data('date') > today) {
                        $targetRow = $(this);
                        return false;
                    }
                });
            }
            if ($targetRow.length) {
                $('html, body').animate({
                    scrollTop: $targetRow.offset().top - 150
                }, 500);
            }
        }

        loadBookings();
        loadPrebookings();

        $('#toggleBookingsBtn').on('click', function () {
            showAll = !showAll;
            $(this).text(showAll ? 'Show Upcoming Only' : 'Show All Bookings');
            $('#bookings-heading').text(showAll ? 'All Bookings' : 'Upcoming Bookings');
            loadBookings();
            loadPrebookings();
        });

        // Status toggle
        $(document).on('click', '.status-toggle-btn', function () {
            var bookingId = $(this).data('id');
            var currentStatus = $(this).data('status');
            var newStatus = (currentStatus === 'completed') ? 'confirmed' : 'completed';
            var button = $(this);
            var row = button.closest('tr');

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                type: 'POST',
                data: {
                    action: 'update_status',
                    id: bookingId,
                    status: newStatus
                },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        // If marking as completed and viewing "Upcoming Only"
                        if (newStatus === 'completed' && !showAll) {
                            // Fade out and remove the row
                            row.fadeOut(400, function () {
                                $(this).remove();

                                // Check if table is empty
                                if (tableBody.find('tr').length === 0) {
                                    $('.bookings-table').hide();
                                    noBookingsMessage.show();
                                }
                            });

                            showNotification('✓ Booking marked as completed', 'success');
                        }
                        // If marking as completed and viewing "All Bookings"
                        else if (newStatus === 'completed' && showAll) {
                            // Just update the button state
                            button
                                .text('Undo Done')
                                .data('status', 'completed')
                                .removeClass('view-details-btn')
                                .addClass('completed');

                            // Add visual indicator to the row
                            row.addClass('booking-completed');

                            showNotification('✓ Booking marked as completed', 'success');
                        }
                        // If undoing completion
                        else {
                            button
                                .text('Mark Done')
                                .data('status', 'confirmed')
                                .removeClass('completed')
                                .addClass('view-details-btn');

                            // Remove completed styling
                            row.removeClass('booking-completed');

                            showNotification('✓ Booking status changed to confirmed', 'success');
                        }
                    } else {
                        showNotification('✗ ' + (res.message || 'Failed to update status'), 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to update booking status', 'error');
                }
            });
        });

        // Delete booking
        tableBody.on('click', '.delete-btn', function () {
            bookingIdToDelete = $(this).data('id');
            modal.css('display', 'flex');
        });

        // Thank You button
        tableBody.on('click', '.thank-you-btn', function (e) {
            e.preventDefault();
            var button = $(this);
            var bookingId = button.data('id');
            button.text('...');

            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/api/get_thank_you_link.php',
                data: { id: bookingId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        window.open(response.whatsapp_link, '_blank');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Could not generate Thank You link.', 'error');
                },
                complete: function () {
                    button.text('Thank You');
                }
            });
        });

        $('#cancelDeleteBtn').on('click', function () {
            modal.hide();
            bookingIdToDelete = null;
        });

        $('#confirmDeleteBtn').on('click', function () {
            if (!bookingIdToDelete)
                return;
            var confirmBtn = $(this);
            confirmBtn.text('Deleting...').prop('disabled', true);

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
                data: {
                    action: 'delete',
                    id: bookingIdToDelete
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        $('#booking-row-' + bookingIdToDelete).fadeOut('slow', function () {
                            $(this).remove();
                            if (tableBody.find('tr').length === 0) {
                                loadBookings();
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
                    bookingIdToDelete = null;
                }
            });
        });

        function loadPrebookings() {
            // Remove any existing prebooking rows/separator
            tableBody.find('.prebooking-separator, .prebooking-row-info').remove();

            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Prebookings/api/index.php',
                data: { action: 'list', show: showAll ? 'all' : 'upcoming' },
                dataType: 'json',
                success: function (res) {
                    if (!res.success || res.prebookings.length === 0) return;

                    // Add separator row
                    tableBody.append(
                        '<tr class="prebooking-separator">' +
                        '<td colspan="10">📋 Tentative / Prebookings</td>' +
                        '</tr>'
                    );

                    $.each(res.prebookings, function (i, p) {
                        var rowClass = 'prebooking-row-info' + (p.is_past ? ' booking-overdue' : '');
                        var waLink = p.whatsapp_url && p.whatsapp_url !== '#'
                            ? '<a href="' + escapeHtml(p.whatsapp_url) + '" target="_blank" class="action-btn whatsapp-btn">💬 WhatsApp</a>'
                            : '';
                        var row =
                            '<tr id="pre-row-' + p.id + '" class="' + rowClass + '" data-date="' + escapeHtml(p.trip_date_raw) + '">' +
                            '<td data-label="Date">' + escapeHtml(p.trip_date) + '</td>' +
                            '<td data-label="Time">' + (p.start_time ? escapeHtml(p.start_time) : '<em>TBC</em>') + '</td>' +
                            '<td data-label="Client">' + escapeHtml(p.client_name) + '</td>' +
                            '<td data-label="Pickup">' + (p.pickup_location ? escapeHtml(p.pickup_location) : '<em>TBC</em>') + '</td>' +
                            '<td data-label="Destination">' + (p.destination ? escapeHtml(p.destination) : '<em>TBC</em>') + '</td>' +
                            '<td data-label="Cost">' + (p.cost ? escapeHtml(p.cost) : '<em>TBC</em>') + '</td>' +
                            '<td data-label="Distance"><span style="color:#aaa;">—</span></td>' +
                            '<td data-label="Calc. Cost"><span style="color:#aaa;">—</span></td>' +
                            '<td data-label="Driver">' +
                            '<span class="prebooking-label">Tentative</span>' +
                            (p.description ? '<div class="prebooking-description">' + escapeHtml(p.description) + '</div>' : '') +
                            '</td>' +
                            '<td data-label="Actions">' +
                            '<div class="actions-container">' +
                            waLink +
                            '<a href="<?= BASE_URL ?>/modules/Prebookings/edit.php?id=' + p.id + '" class="action-btn edit-btn">✏️ Edit</a>' +
                            '<button class="action-btn convert-btn convert-prebooking-btn" data-id="' + p.id + '">🚗 Convert</button>' +
                            '<button class="action-btn delete-btn delete-prebooking-btn" data-id="' + p.id + '">🗑️ Delete</button>' +
                            '</div>' +
                            '</td>' +
                            '</tr>';
                        tableBody.append(row);
                    });

                    $('.bookings-table').show();
                    noBookingsMessage.hide();
                }
            });
        }

        // Convert prebooking to full booking
        $(document).on('click', '.convert-prebooking-btn', function () {
            if (!confirm('Convert this tentative booking? The calendar event will be removed and the booking form will open prefilled.')) return;
            var id = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Converting…');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=convert',
                data: { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.redirect_url) {
                        window.location.href = res.redirect_url;
                    } else {
                        alert('❌ ' + (res.message || 'Could not convert prebooking.'));
                        btn.prop('disabled', false).text('🚗 Convert');
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    btn.prop('disabled', false).text('🚗 Convert');
                }
            });
        });

        // Delete prebooking from bookings list
        $(document).on('click', '.delete-prebooking-btn', function () {
            if (!confirm('Delete this tentative booking and remove it from Google Calendar?')) return;
            var id = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Deleting…');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=delete',
                data: { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        $('#pre-row-' + id).fadeOut(400, function () { $(this).remove(); });
                        showNotification('✓ Tentative booking deleted', 'success');
                    } else {
                        alert('❌ ' + (res.message || 'Could not delete.'));
                        btn.prop('disabled', false).text('🗑️ Delete');
                    }
                },
                error: function () {
                    alert('❌ Request failed. Please try again.');
                    btn.prop('disabled', false).text('🗑️ Delete');
                }
            });
        });

        function showNotification(message, type) {
            const className = type === 'success' ? 'success-message' : 'error-message';
            const notification = $('<div class="' + className + '">' + message + '</div>');
            notificationArea.html(notification);

            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
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
