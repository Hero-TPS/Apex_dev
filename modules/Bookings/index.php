<?php
// BookingsView.php
$page_title = 'Bookings';
$page_subtitle = 'Bookings';
$show_breadcrumb = true;
$breadcrumb = ' > Bookings';

require_once __DIR__ . '/../../config.php';
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
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php" class="btn" style="width: auto; padding: 10px 20px; text-decoration: none;">+ Create a Booking</a>
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
            tableBody.html('<tr><td colspan="7" style="text-align:center;">Loading bookings...</td></tr>');
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
                                '<td data-label="Cost">' + escapeHtml(booking.cost) + '</td>' +
                                '<td data-label="Actions">' +
                                '<div class="actions-container">' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/view.php?id=' + booking.id + '" class="action-btn view-details-btn">View</a>' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/edit.php?id=' + booking.id + '" class="action-btn edit-btn">Edit</a>' +
                                '<button class="action-btn delete-btn" data-id="' + booking.id + '">Delete</button>' +
                                '<a href="<?= BASE_URL ?>/modules/Bookings/invoice.php?id=' + booking.id + '" class="action-btn invoice-btn" target="_blank">Invoice</a>' +
                                '<button class="action-btn thank-you-btn" data-id="' + booking.id + '">Thank You</button>';

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
                error: function () {
                    tableBody.empty();
                    showNotification('❌ Could not load bookings. Please check the server connection.', 'error');
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

        $('#toggleBookingsBtn').on('click', function () {
            showAll = !showAll;
            $(this).text(showAll ? 'Show Upcoming Only' : 'Show All Bookings');
            $('#bookings-heading').text(showAll ? 'All Bookings' : 'Upcoming Bookings');
            loadBookings();
        });

        // Status toggle
        $(document).on('click', '.status-toggle-btn', function () {
            var bookingId = $(this).data('id');
            var currentStatus = $(this).data('status');
            var newStatus = (currentStatus === 'completed') ? 'confirmed' : 'completed';
            var button = $(this);

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
                        // 🔁 Update button state immediately (no full reload)
                        if (newStatus === 'completed') {
                            button
                                .text('Undo Done')
                                .data('status', 'completed')
                                .removeClass('view-details-btn')
                                .addClass('completed');
                        } else {
                            button
                                .text('Mark Done')
                                .data('status', 'confirmed')
                                .removeClass('completed')
                                .addClass('view-details-btn');
                        }
                    }
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

        function showNotification(message, type) {
            var messageDiv = $('<div class="' + (type === 'success' ? 'success-message' : 'error-message') + '"></div>').text(message);
            notificationArea.html(messageDiv);
            setTimeout(function () {
                messageDiv.fadeOut('slow', function () {
                    $(this).remove();
                });
            }, 5000);
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>