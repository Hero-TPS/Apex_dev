<?php
// index.php
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$page_title = 'Dashboard';
include ROOT_DIR . '/includes/header.php';

?>

<div class="dashboard-container">
    <h2 class="dashboard-title">Main Menu</h2>

    <!-- Tomorrow's Confirmations -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="confirmations-section">📲 Tomorrow's Confirmations <span id="confirmations-badge"></span></h3>
        <div id="confirmations-section" class="menu-content" style="display:none;">
            <div id="confirmations-loading" style="text-align:center; padding:10px; color:#666;">Loading...</div>
            <div id="confirmations-list"></div>
        </div>
    </div>

    <!-- Bookings -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="bookings-section">📅 Bookings</h3>
        <div id="bookings-section" class="menu-content">
            <a href="modules/Bookings/" class="dashboard-button db-booking">View Bookings</a>
            <a href="modules/Bookings/add.php" class="dashboard-button db-view">Add Booking</a>
            <a href="modules/Bookings/reports.php" class="dashboard-button db-reports">Booking Reports</a>
        </div>
    </div>

    <!-- Clients -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="clients-section">👥 Clients</h3>
        <div id="clients-section" class="menu-content">
            <a href="modules/Clients/" class="dashboard-button db-clients">View Clients</a>
            <a href="modules/Clients/add.php" class="dashboard-button db-contact">Add Client</a>
            <a href="GroupManagement.php" class="dashboard-button db-groups">Client Groups</a>
        </div>
    </div>

    <!-- Fuel -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="fuel-section">⛽ Fuel</h3>
        <div id="fuel-section" class="menu-content">
            <a href="modules/Fuel/add.php" class="dashboard-button db-fuel">Log Fuel</a>
            <a href="modules/Fuel/" class="dashboard-button db-fuel-reports">Fuel Reports</a>
        </div>
    </div>

    <!-- Uber -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="uber-section">🚗 Uber</h3>
        <div id="uber-section" class="menu-content">
            <a href="modules/Uber/add.php" class="dashboard-button db-uber">Log Uber Income</a>
            <a href="modules/Uber/" class="dashboard-button db-uber-reports">Uber Reports</a>
        </div>
    </div>

   <!-- Financials Menu -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="financials-section">💰 Financials</h3>
        <div id="financials-section" class="menu-content">
            <a href="financials/index.php" class="dashboard-button db-reports">
                <span class="dashboard-text">Financial Summary</span>
            </a>
            <a href="financials/weekly.php" class="dashboard-button db-reports">
                <span class="dashboard-text">Weekly Report</span>
            </a>
            <a href="financials/monthly.php" class="dashboard-button db-reports">
                <span class="dashboard-text">Monthly Report</span>
            </a>
        </div>
    </div>

    <!-- Maintenance -->
<div class="menu-section">
    <h3 class="menu-toggle" data-target="maintenance-section">⚙️ Maintenance</h3>
    <div id="maintenance-section" class="menu-content">
        <a href="maintenance/" class="dashboard-button db-maintenance">Manage Lists</a>
        <a href="maintenance/logs.php" class="dashboard-button db-maintenance">System Logs</a>
    </div>
</div>

    <!-- Calendar -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="calendar-section">🗓️ Calendar</h3>
        <div id="calendar-section" class="menu-content">
            <a href="https://calendar.google.com/calendar/u/0?cid=<?= urlencode(CUSTOM_CALENDAR_ID) ?>" 
               onclick="event.preventDefault(); openCalendarApp('<?= urlencode(CUSTOM_CALENDAR_ID) ?>');"
               class="dashboard-button db-calendar">
                <span class="dashboard-icon">📅</span>
                <span class="dashboard-text">Open Calendar</span>
            </a>
        </div>
    </div>

</div>

<script>
    $(document).ready(function () {
        $('.menu-toggle').on('click', function () {
            // Close all other sections
            $('.menu-content').not($(this).next()).slideUp(200);

            // Toggle current section
            $(this).next().slideToggle(200);
        });

        // ========== Tomorrow's Confirmations Widget ==========
        function loadTomorrowsConfirmations() {
            $.ajax({
                type: 'GET',
                url: '<?= BASE_URL ?>/modules/Bookings/api/index.php?action=tomorrows_bookings',
                dataType: 'json',
                success: function (res) {
                    $('#confirmations-loading').hide();
                    if (!res.success) {
                        $('#confirmations-list').html('<p style="color:#e74c3c;">Failed to load confirmations.</p>');
                        return;
                    }

                    var pending = res.bookings.filter(function (b) { return !b.already_confirmed; });
                    var badge   = pending.length > 0 ? ' (' + pending.length + ')' : '';
                    $('#confirmations-badge').text(badge);

                    if (res.bookings.length === 0 || pending.length === 0) {
                        $('#confirmations-list').html('<p style="color:#27ae60; padding:8px 0;">✅ All confirmed for tomorrow!</p>');
                        return;
                    }

                    var html = '';
                    $.each(pending, function (i, b) {
                        var time    = b.start_time ? b.start_time.substr(0, 5) : '';
                        var cost    = 'R' + parseFloat(b.cost).toFixed(2);
                        html += '<div class="confirmation-row" id="conf-row-' + b.id + '" style="' +
                                'background:#fff; border:1px solid #e0e0e0; border-radius:6px; ' +
                                'padding:10px 12px; margin-bottom:8px;">' +
                                '<strong>' + escapeHtml(b.client_name) + '</strong>' +
                                ' &mdash; ' + time +
                                ' &mdash; ' + escapeHtml(b.pickup_location) + ' → ' + escapeHtml(b.destination) +
                                ' &mdash; ' + cost +
                                '<br>' +
                                '<a href="' + b.whatsapp_url + '" target="_blank" ' +
                                '   class="page-action-btn whatsapp" style="margin-top:6px; display:inline-block;" ' +
                                '   onclick="markConfirmed(' + b.id + ', ' + JSON.stringify(b.message_content) + ')">💬 Confirm &amp; Send</a>' +
                                '</div>';
                    });
                    $('#confirmations-list').html(html);
                },
                error: function () {
                    $('#confirmations-loading').hide();
                    $('#confirmations-list').html('<p style="color:#e74c3c;">Could not load tomorrow\'s bookings.</p>');
                }
            });
        }

        loadTomorrowsConfirmations();

        // Open the confirmations section by default if there are pending items
        $('#confirmations-section').slideDown(200);
    });

    function markConfirmed(bookingId, messageContent) {
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Bookings/api/index.php',
            data: { action: 'mark_confirmed', id: bookingId, message_content: messageContent },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#conf-row-' + bookingId).fadeOut(400, function () {
                        $(this).remove();
                        var remaining = $('.confirmation-row').length;
                        if (remaining === 0) {
                            $('#confirmations-list').html('<p style="color:#27ae60; padding:8px 0;">✅ All confirmed for tomorrow!</p>');
                            $('#confirmations-badge').text('');
                        } else {
                            var current = parseInt($('#confirmations-badge').text().replace(/\D/g, '')) || 0;
                            if (current > 1) {
                                $('#confirmations-badge').text(' (' + (current - 1) + ')');
                            } else {
                                $('#confirmations-badge').text('');
                            }
                        }
                    });
                }
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
</script>

<?php include 'includes/footer.php'; ?>