<!-- includes/footer.php -->
<?php
// Show floating menu on all pages EXCEPT index.php
//$is_home = basename($_SERVER['SCRIPT_NAME']) === 'index.php';
?>

    <div class="floating-menu-toggle" id="menuToggle">☰</div>
    <div class="floating-menu" id="floatingMenu">
        <a href="<?= BASE_URL ?>/index.php" class="hamburger-item">🏠 Home</a>
        <a href="<?= BASE_URL ?>/AddBooking.php" class="hamburger-item">🚗 Add Booking</a>
        <a href="<?= BASE_URL ?>/BookingsView.php" class="hamburger-item">📅 View Bookings</a>
        <a href="<?= BASE_URL ?>/ContactForm.php" class="hamburger-item">👥 Add Client</a>
        <a href="<?= BASE_URL ?>/FuelLog.php" class="hamburger-item">⛽ Add Fuel Log</a>
        <a href="https://calendar.google.com/calendar/u/0?cid=<?= urlencode(CUSTOM_CALENDAR_ID) ?>"
           onclick="event.preventDefault(); openCalendarApp('<?= urlencode(CUSTOM_CALENDAR_ID) ?>');"
           class="hamburger-item">🗓️ Open Calendar</a>
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