<!-- includes/footer.php -->
<?php if (!isset($hide_hamburger) || !$hide_hamburger): ?>
    <div class="floating-menu-toggle" id="menuToggle">☰</div>
    <div class="floating-menu" id="floatingMenu">
        <a href="<?= BASE_URL ?>/index.php" class="hamburger-item">🏠 Home</a>
        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="hamburger-item">📊 Dashboard</a>
        <?php
        // Pre-compute permission flags for logged-in users
        $_footerUser   = function_exists('getCurrentUser') ? getCurrentUser() : null;
        $_footerUserId = $_footerUser ? (int) $_footerUser['id'] : 0;
        $_footerHasPerm = function (string $path) use ($pdo, $_footerUserId): bool {
            return isset($pdo) && $_footerUserId > 0 && function_exists('hasPagePermission')
                ? hasPagePermission($pdo, $_footerUserId, $path)
                : false;
        };
        ?>
        <?php if ($_footerHasPerm('/modules/Bookings/add.php')): ?>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php" class="hamburger-item">🚗 Add Booking</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Bookings/')): ?>
        <a href="<?= BASE_URL ?>/modules/Bookings/" class="hamburger-item">📅 View Bookings</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Clients/add.php')): ?>
        <a href="<?= BASE_URL ?>/modules/Clients/add.php" class="hamburger-item">👥 Add Client</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Clients/')): ?>
        <a href="<?= BASE_URL ?>/modules/Clients/" class="hamburger-item">📋 View Clients</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Fuel/add.php')): ?>
        <a href="<?= BASE_URL ?>/modules/Fuel/add.php" class="hamburger-item">⛽ Add Fuel Log</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Uber/add.php')): ?>
        <a href="<?= BASE_URL ?>/modules/Uber/add.php" class="hamburger-item">🚕 Log Uber Income</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Financials/')): ?>
        <a href="<?= BASE_URL ?>/modules/Financials/" class="hamburger-item">💰 Financials</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/modules/Financials/balance_sheet.php')): ?>
        <a href="<?= BASE_URL ?>/modules/Financials/balance_sheet.php" class="hamburger-item">📊 Balance Sheet</a>
        <?php endif; ?>
        <?php if ($_footerHasPerm('/maintenance/')): ?>
        <a href="<?= BASE_URL ?>/maintenance/" class="hamburger-item">⚙️ Maintenance</a>
        <?php endif; ?>
        <?php if (function_exists('isAdmin') && isAdmin()): ?>
        <a href="https://calendar.google.com/calendar/u/0?cid=<?= urlencode(CUSTOM_CALENDAR_ID) ?>"
           onclick="event.preventDefault(); openCalendarApp('<?= urlencode(CUSTOM_CALENDAR_ID) ?>');"
           class="hamburger-item">🗓️ Open Calendar</a>
        <?php endif; ?>
        <div class="hamburger-divider"></div>
        <?php if ($_footerHasPerm('/modules/AccessControl/')): ?>
        <a href="<?= BASE_URL ?>/modules/AccessControl/" class="hamburger-item">🔐 Access Control</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/logout.php" class="hamburger-item">🚪 Sign Out</a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/modules/Bookings/add.php" class="hamburger-item">🚗 Add Booking</a>
        <a href="<?= BASE_URL ?>/modules/Bookings/" class="hamburger-item">📅 View Bookings</a>
        <a href="<?= BASE_URL ?>/modules/Clients/add.php" class="hamburger-item">👥 Add Client</a>
        <a href="<?= BASE_URL ?>/modules/Clients/" class="hamburger-item">📋 View Clients</a>
        <a href="<?= BASE_URL ?>/modules/Fuel/add.php" class="hamburger-item">⛽ Add Fuel Log</a>
        <a href="<?= BASE_URL ?>/modules/Uber/add.php" class="hamburger-item">🚕 Log Uber Income</a>
        <a href="<?= BASE_URL ?>/modules/Financials/" class="hamburger-item">💰 Financials</a>
        <a href="<?= BASE_URL ?>/modules/Financials/balance_sheet.php" class="hamburger-item">📊 Balance Sheet</a>
        <a href="https://calendar.google.com/calendar/u/0?cid=<?= urlencode(CUSTOM_CALENDAR_ID) ?>"
           onclick="event.preventDefault(); openCalendarApp('<?= urlencode(CUSTOM_CALENDAR_ID) ?>');"
           class="hamburger-item">🗓️ Open Calendar</a>
        <div class="hamburger-divider"></div>
        <a href="<?= BASE_URL ?>/login.php" class="hamburger-item">🔑 Sign In</a>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function () {
            // Toggle hamburger menu
            $('#menuToggle').on('click', function () {
                $('#floatingMenu').slideToggle(200);
            });

            // Accordion for hamburger parents
            $(document).on('click', '.hamburger-parent', function () {
                var target = $(this).data('target');
                $('.hamburger-submenu').not('#' + target).slideUp(200);
                $('#' + target).slideToggle(200);
            });

            // Close menu on outside click
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.floating-menu, .floating-menu-toggle').length) {
                    $('#floatingMenu').slideUp(200);
                    $('.hamburger-submenu').slideUp(200);
                }
            });
        });
    </script>
<?php endif; ?>

<!-- Breadcrumb at Bottom -->
<?php if (isset($show_breadcrumb) && $show_breadcrumb): ?>
    <div class="breadcrumb breadcrumb-footer">
        <a href="<?= BASE_URL ?>/index.php">Home</a>
        <?php echo isset($breadcrumb) ? $breadcrumb : ''; ?>
    </div>
<?php endif; ?>

</main>
</div>   
</body>
</html>