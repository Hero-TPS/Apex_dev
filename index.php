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
        <h3 class="menu-toggle" data-target="confirmations-section">📲 Tomorrow's Confirmations <span
                id="confirmations-badge"></span></h3>
        <div id="confirmations-section" class="menu-content" style="display:none;">
            <div id="confirmations-loading" class="confirmations-loading">Loading...</div>
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

    <!-- Prebookings -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="prebookings-section">📋 Prebookings</h3>
        <div id="prebookings-section" class="menu-content">
            <a href="modules/Prebookings/add.php" class="dashboard-button db-booking">Add Prebooking</a>
            <a href="modules/Prebookings/" class="dashboard-button db-view">View Prebookings</a>
        </div>
    </div>

    <!-- Clients -->
    <div class="menu-section">
        <h3 class="menu-toggle" data-target="clients-section">👥 Clients</h3>
        <div id="clients-section" class="menu-content">
            <a href="modules/Clients/" class="dashboard-button db-clients">View Clients</a>
            <a href="modules/Clients/add.php" class="dashboard-button db-contact">Add Client</a>
            <a href="modules/Clients/duplicates/" class="dashboard-button db-groups">Manage Duplicates</a>
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
            <a href="modules/Financials/" class="dashboard-button db-reports">Financial Summary</a>
            <a href="modules/Financials/balance_sheet.php" class="dashboard-button db-balance-sheet">Balance Sheet</a>
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
            $('.menu-content').not($(this).next()).slideUp(200);
            $(this).next().slideToggle(200);
        });

        // ========== Tomorrow's Confirmations Widget ==========
        function loadTomorrowsConfirmations() {
            var tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            var tomorrowStr = tomorrow.toISOString().split('T')[0];

            $.when(
                $.ajax({
                    type: 'GET',
                    url: '<?= BASE_URL ?>/modules/Bookings/api/index.php?action=tomorrows_bookings',
                    dataType: 'json'
                }),
                $.ajax({
                    type: 'GET',
                    url: '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=list&show=upcoming',
                    dataType: 'json'
                })
            ).done(function (bookingsRes, prebookingsRes) {
                $('#confirmations-loading').hide();

                var res  = bookingsRes[0];
                var pres = prebookingsRes[0];

                if (!res.success) {
                    $('#confirmations-list').html('<p class="error-message">Failed to load confirmations.</p>');
                    return;
                }

                // Unconfirmed bookings + tomorrow's prebookings both count toward the badge
                var pending = res.bookings.filter(function (b) { return !b.already_confirmed; });
                var tomorrowPres = (pres.success && pres.prebookings)
                    ? pres.prebookings.filter(function (p) { return p.trip_date_raw === tomorrowStr; })
                    : [];

                var totalBadge = pending.length + tomorrowPres.length;
                $('#confirmations-badge').text(totalBadge > 0 ? ' (' + totalBadge + ')' : '');

                var html = '';

                // --- Confirmed bookings ---
                $.each(res.bookings, function (i, b) {
                    var time = b.start_time ? b.start_time.substr(0, 5) : '';
                    var cost = 'R' + parseFloat(b.cost).toFixed(2);
                    var viewUrl = '<?= BASE_URL ?>/modules/Bookings/view.php?id=' + b.id;
                    var confirmedClass = b.already_confirmed ? ' is-confirmed' : '';
                    var confirmedBadge = b.already_confirmed
                        ? '<span class="conf-confirmed-badge confirmed"> ✅ Confirmed</span>'
                        : '<span class="conf-confirmed-badge"></span>';
                    html += '<div class="confirmation-row' + confirmedClass + '" id="conf-row-' + b.id + '" ' +
                        'data-booking-id="' + b.id + '" ' +
                        'data-wa-url="' + escapeHtmlAttr(b.whatsapp_url) + '" ' +
                        'data-message="' + escapeHtmlAttr(b.message_content) + '">' +
                        '<strong>' + escapeHtml(b.client_name) + '</strong>' +
                        ' &mdash; ' + escapeHtml(time) +
                        ' &mdash; ' + escapeHtml(b.pickup_location) + ' → ' + escapeHtml(b.destination) +
                        ' &mdash; ' + escapeHtml(cost) +
                        confirmedBadge +
                        '<br>' +
                        '<a href="#" class="page-action-btn whatsapp confirm-send-btn">' +
                        (b.already_confirmed ? '🔁 Re-send' : '💬 Confirm &amp; Send') + '</a>' +
                        ' <a href="' + viewUrl + '" class="page-action-btn view">📋 View Booking</a>' +
                        '</div>';
                });

                // --- Tomorrow's prebookings ---
                if (tomorrowPres.length > 0) {
                    if (html !== '') {
                        html += '<hr class="confirmations-divider">';
                    }
                    html += '<div class="confirmations-section-label">📋 Tentative (Prebookings)</div>';

                    $.each(tomorrowPres, function (i, p) {
                        var time    = p.start_time || 'TBC';
                        var pickup  = p.pickup_location || 'TBC';
                        var dest    = p.destination || 'TBC';
                        var cost    = p.cost || 'TBC';
                        var editUrl = '<?= BASE_URL ?>/modules/Prebookings/edit.php?id=' + p.id;
                        var waPhone = p.client_phone;
                        var waMsg   = encodeURIComponent('Good day ' + p.client_name + ', just a reminder about your tentative booking tomorrow.');
                        html += '<div class="confirmation-row prebooking-reminder-row" id="pre-conf-row-' + p.id + '">' +
                            '<strong>' + escapeHtml(p.client_name) + '</strong> 📋' +
                            ' &mdash; ' + escapeHtml(time) +
                            ' &mdash; ' + escapeHtml(pickup) + ' → ' + escapeHtml(dest) +
                            ' &mdash; ' + escapeHtml(cost) +
                            '<br>' +
                            (waPhone
                                ? '<a href="https://wa.me/' + escapeHtmlAttr(waPhone) + '?text=' + waMsg + '" target="_blank" rel="noopener" class="page-action-btn whatsapp">💬 Send Reminder</a> '
                                : '') +
                            '<a href="' + editUrl + '" class="page-action-btn edit">✏️ Edit</a> ' +
                            '<button class="page-action-btn confirm convert-pre-btn" data-id="' + p.id + '">🚗 Convert</button>' +
                            '</div>';
                    });
                }

                $('#confirmations-list').html(html || '<p>No bookings or prebookings tomorrow.</p>');

            }).fail(function () {
                $('#confirmations-loading').hide();
                $('#confirmations-list').html('<p class="error-message">Could not load tomorrow\'s bookings.</p>');
            });
        }

        loadTomorrowsConfirmations();

        // Confirmed booking — send WA + mark confirmed
        $('#confirmations-list').on('click', '.confirm-send-btn', function (e) {
            e.preventDefault();
            var row = $(this).closest('.confirmation-row');
            var id = row.data('booking-id');
            var waUrl = row.data('wa-url');
            var message = row.data('message');
            window.open(waUrl, '_blank');
            markConfirmed(id, message);
        });

        // Convert prebooking from dashboard
        $('#confirmations-list').on('click', '.convert-pre-btn', function () {
            if (!confirm('Convert this tentative booking? The calendar event will be removed and the booking form will open prefilled.')) return;
            var id  = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Converting…');

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=convert',
                data:     { id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.redirect_url) {
                        window.location.href = res.redirect_url;
                    } else {
                        alert('❌ ' + (res.message || 'Could not convert.'));
                        btn.prop('disabled', false).text('🚗 Convert');
                    }
                },
                error: function () {
                    alert('❌ Request failed.');
                    btn.prop('disabled', false).text('🚗 Convert');
                }
            });
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
                    var row = $('#conf-row-' + bookingId);
                    var wasAlreadyConfirmed = row.hasClass('is-confirmed');

                    if (!wasAlreadyConfirmed) {
                        var current = parseInt($('#confirmations-badge').text().replace(/\D/g, '')) || 0;
                        if (current > 1) {
                            $('#confirmations-badge').text(' (' + (current - 1) + ')');
                        } else {
                            $('#confirmations-badge').text('');
                        }
                    }

                    row.addClass('is-confirmed');
                    row.find('.confirm-send-btn').html('🔁 Re-send');
                    row.find('.conf-confirmed-badge')
                        .html(' ✅ Confirmed')
                        .addClass('confirmed');
                } else {
                    $('#conf-row-' + bookingId).find('.confirm-send-btn')
                        .after('<span class="inline-error">⚠️ Could not save. Run the DB migration.</span>');
                }
            },
            error: function () {
                $('#conf-row-' + bookingId).find('.confirm-send-btn')
                    .after('<span class="inline-error">⚠️ Request failed.</span>');
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

<?php include 'includes/footer.php'; ?>