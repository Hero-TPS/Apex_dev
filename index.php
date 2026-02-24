<?php
// index.php
require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$page_title = 'Dashboard';
include ROOT_DIR . '/includes/header.php';

?>

<div class="dashboard-container">
    <h2 class="dashboard-title">Main Menu</h2>

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
    });
</script>

<?php include 'includes/footer.php'; ?>