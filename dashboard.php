<?php
// dashboard.php — Protected management dashboard
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$page_title    = 'Dashboard';
$page_subtitle = 'Management Dashboard';
include ROOT_DIR . '/includes/header.php';

// Pre-compute permission flags for conditional section/link display
$_dashUser   = function_exists('getCurrentUser') ? getCurrentUser() : null;
$_dashUserId = $_dashUser ? (int) $_dashUser['id'] : 0;

$_can = function (string $path) use ($pdo, $_dashUserId): bool {
    return $_dashUserId > 0 && function_exists('hasPagePermission')
        ? hasPagePermission($pdo, $_dashUserId, $path)
        : false;
};

$canViewBookings    = $_can('/modules/Bookings/');
$canAddBooking      = $_can('/modules/Bookings/add.php');
$canViewClients     = $_can('/modules/Clients/');
$canAddClient       = $_can('/modules/Clients/add.php');
$canLogFuel         = $_can('/modules/Fuel/add.php');
$canViewFuel        = $_can('/modules/Fuel/');
$canLogUber         = $_can('/modules/Uber/add.php');
$canViewUber        = $_can('/modules/Uber/');
$canFinancials      = $_can('/modules/Financials/');
$canBalanceSheet    = $_can('/modules/Financials/balance_sheet.php');
$canMaintenance     = $_can('/maintenance/');
$canAccessControl   = $_can('/modules/AccessControl/');
$canACUsers         = $_can('/modules/AccessControl/users/');
$canACRoles         = $_can('/modules/AccessControl/roles/');
?>

<div class="dashboard-container">
    <h2 class="dashboard-title">Main Menu</h2>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
    <div class="error-message" style="margin-bottom:16px;">
        🚫 You do not have permission to access that page.
    </div>
    <?php endif; ?>

    <!-- Tomorrow's Confirmations -->
    <?php if ($canViewBookings): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="confirmations-section">📲 Tomorrow's Confirmations <span
                id="confirmations-badge"></span></h3>
        <div id="confirmations-section" class="menu-content" style="display:none;">
            <div id="confirmations-loading" style="text-align:center; padding:10px; color:#666;">Loading...</div>
            <div id="confirmations-list"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bookings -->
    <?php if ($canViewBookings || $canAddBooking): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="bookings-section">📅 Bookings</h3>
        <div id="bookings-section" class="menu-content">
            <?php if ($canViewBookings): ?>
            <a href="modules/Bookings/" class="dashboard-button db-booking">View Bookings</a>
            <?php endif; ?>
            <?php if ($canAddBooking): ?>
            <a href="modules/Bookings/add.php" class="dashboard-button db-view">Add Booking</a>
            <?php endif; ?>
            <?php if ($canViewBookings): ?>
            <a href="modules/Bookings/reports.php" class="dashboard-button db-reports">Booking Reports</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clients -->
    <?php if ($canViewClients || $canAddClient): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="clients-section">👥 Clients</h3>
        <div id="clients-section" class="menu-content">
            <?php if ($canViewClients): ?>
            <a href="modules/Clients/" class="dashboard-button db-clients">View Clients</a>
            <?php endif; ?>
            <?php if ($canAddClient): ?>
            <a href="modules/Clients/add.php" class="dashboard-button db-contact">Add Client</a>
            <?php endif; ?>
            <?php if ($canViewClients): ?>
            <a href="modules/Clients/duplicates/" class="dashboard-button db-groups">Manage Duplicates</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fuel -->
    <?php if ($canLogFuel || $canViewFuel): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="fuel-section">⛽ Fuel</h3>
        <div id="fuel-section" class="menu-content">
            <?php if ($canLogFuel): ?>
            <a href="modules/Fuel/add.php" class="dashboard-button db-fuel">Log Fuel</a>
            <?php endif; ?>
            <?php if ($canViewFuel): ?>
            <a href="modules/Fuel/" class="dashboard-button db-fuel-reports">Fuel Reports</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Uber -->
    <?php if ($canLogUber || $canViewUber): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="uber-section">🚗 Uber</h3>
        <div id="uber-section" class="menu-content">
            <?php if ($canLogUber): ?>
            <a href="modules/Uber/add.php" class="dashboard-button db-uber">Log Uber Income</a>
            <?php endif; ?>
            <?php if ($canViewUber): ?>
            <a href="modules/Uber/" class="dashboard-button db-uber-reports">Uber Reports</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Financials -->
    <?php if ($canFinancials || $canBalanceSheet): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="financials-section">💰 Financials</h3>
        <div id="financials-section" class="menu-content">
            <?php if ($canFinancials): ?>
            <a href="modules/Financials/" class="dashboard-button db-reports">Financial Summary</a>
            <?php endif; ?>
            <?php if ($canBalanceSheet): ?>
            <a href="modules/Financials/balance_sheet.php" class="dashboard-button db-balance-sheet">Balance Sheet</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maintenance -->
    <?php if ($canMaintenance): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="maintenance-section">⚙️ Maintenance</h3>
        <div id="maintenance-section" class="menu-content">
            <a href="maintenance/" class="dashboard-button db-maintenance">Manage Lists</a>
            <a href="maintenance/logs.php" class="dashboard-button db-maintenance">System Logs</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Access Control -->
    <?php if ($canAccessControl || $canACUsers || $canACRoles): ?>
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="access-control-section">🔐 Access Control</h3>
        <div id="access-control-section" class="menu-content">
            <?php if ($canAccessControl): ?>
            <a href="modules/AccessControl/" class="dashboard-button db-access-control">Access Control</a>
            <?php endif; ?>
            <?php if ($canACUsers): ?>
            <a href="modules/AccessControl/users/" class="dashboard-button db-access-control">Users</a>
            <?php endif; ?>
            <?php if ($canACRoles): ?>
            <a href="modules/AccessControl/roles/" class="dashboard-button db-access-control">Roles</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

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
                    var badge = pending.length > 0 ? ' (' + pending.length + ')' : '';
                    $('#confirmations-badge').text(badge);

                  //  if (res.bookings.length === 0 || pending.length === 0) {
                        $('#confirmations-section').slideUp(200);
                    //    return;
                  //  }

                    var html = '';
                    $.each(pending, function (i, b) {
                        var time = b.start_time ? b.start_time.substr(0, 5) : '';
                        var cost = 'R' + parseFloat(b.cost).toFixed(2);
                        html += '<div class="confirmation-row" id="conf-row-' + b.id + '" style="' +
                            'background:#fff; border:1px solid #e0e0e0; border-radius:6px; ' +
                            'padding:10px 12px; margin-bottom:8px;" ' +
                            'data-booking-id="' + b.id + '" ' +
                            'data-wa-url="' + escapeHtmlAttr(b.whatsapp_url) + '" ' +
                            'data-message="' + escapeHtmlAttr(b.message_content) + '">' +
                            '<strong>' + escapeHtml(b.client_name) + '</strong>' +
                            ' &mdash; ' + escapeHtml(time) +
                            ' &mdash; ' + escapeHtml(b.pickup_location) + ' → ' + escapeHtml(b.destination) +
                            ' &mdash; ' + escapeHtml(cost) +
                            '<br>' +
                            '<a href="#" target="_blank" ' +
                            '   class="page-action-btn whatsapp confirm-send-btn" style="margin-top:6px; display:inline-block;">' +
                            '💬 Confirm &amp; Send</a>' +
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

        // Open the confirmations section by default
        $('#confirmations-section').slideDown(200);

        // Delegated click for Confirm & Send buttons
        $('#confirmations-list').on('click', '.confirm-send-btn', function (e) {
            e.preventDefault();
            var row = $(this).closest('.confirmation-row');
            var id = row.data('booking-id');
            var waUrl = row.data('wa-url');
            var message = row.data('message');
            window.open(waUrl, '_blank');
            markConfirmed(id, message);
        });
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
                            $('#confirmations-section').slideUp(200);
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
                } else {
                    $('#conf-row-' + bookingId).find('.confirm-send-btn')
                        .after('<span style="color:#e74c3c; font-size:0.85em; margin-left:8px;">⚠️ Could not save. Run the DB migration.</span>');
                }
            },
            error: function () {
                $('#conf-row-' + bookingId).find('.confirm-send-btn')
                    .after('<span style="color:#e74c3c; font-size:0.85em; margin-left:8px;">⚠️ Request failed.</span>');
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function escapeHtmlAttr(text) {
        return (text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
