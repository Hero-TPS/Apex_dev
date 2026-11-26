<?php
// services/gps_sessions.php

$page_title = 'GPS Log';
$page_subtitle = 'GPS Log';
$show_breadcrumb = true;
$hide_gps_banner = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once ROOT_DIR . '/includes/gps_import.php';
require_once ROOT_DIR . '/includes/gps_session.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'GPS Log'],
]);

if (empty($_SESSION['gps_session_csrf'])) {
    $_SESSION['gps_session_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['gps_session_csrf'];

// Import whatever GPSLogger has uploaded, then read the state the page starts with
gpsImportOnDemand($pdo);
$state = null;
try {
    $state = gpsSessionState($pdo);
} catch (PDOException $e) {
    logError('GPS', 'GPS Log page could not read its tables', ['error' => $e->getMessage()]);
}

include ROOT_DIR . '/includes/header.php';
?>

<div class="form-container">
    <h2>📍 GPS Log</h2>

    <?php if ($state === null): ?>
        <div class="error-message">The GPS Log could not load. Run the pending updates in Services → Database Updates.
            If that does not help, the reason is in Services → System Logs (category GPS).</div>
    <?php else: ?>

        <div id="gpsState" class="at-gps-state"></div>
        <div id="gpsFresh" class="at-gps-fresh"></div>
        <div id="gpsMessage"></div>

        <div class="at-gps-buttons">
            <button type="button" class="page-action-btn save at-gps-btn" data-press="start">▶️ Start logging</button>
            <button type="button" class="page-action-btn delete at-gps-btn" data-press="end">⏹️ End logging</button>
        </div>

        <div class="at-gps-buttons">
            <button type="button" class="page-action-btn at-gps-btn at-gps-uber" data-press="go_uber">Go to Uber pickup</button>
            <button type="button" class="page-action-btn at-gps-btn at-gps-uber" data-press="pickup_uber">Uber pickup</button>
            <button type="button" class="page-action-btn at-gps-btn at-gps-uber" data-press="drop_uber">Uber drop off</button>
        </div>

        <div class="at-gps-buttons">
            <button type="button" class="page-action-btn edit at-gps-btn" data-press="go_apex">Go to Apex pickup</button>
            <button type="button" class="page-action-btn edit at-gps-btn" data-press="pickup_apex">Apex pickup</button>
            <button type="button" class="page-action-btn edit at-gps-btn" data-press="drop_apex">Apex drop off</button>
        </div>

        <div class="at-gps-buttons">
            <button type="button" class="page-action-btn back at-gps-btn" id="gpsUndo">↩️ Undo last</button>
        </div>

        <h3 id="gpsEventsTitle">Events</h3>
        <div id="gpsEvents"></div>

        <h3>Recent sessions</h3>
        <div id="gpsRecent"></div>

        <details class="at-update-section">
            <summary>How the buttons work</summary>
            <ul>
                <li>Start logging when you begin a day of driving and End logging when you finish. Make sure GPSLogger is running.</li>
                <li>A job has three buttons: go to pickup, pickup, drop off. Every press is saved with the time it was pressed.</li>
                <li>If you skip a button, the missing steps are filled in at the same moment. Pressing "Uber drop off" straight
                    after "Go to Uber pickup" also fills in the pickup.</li>
                <li>If you start another job, or press an earlier step, the job in progress is closed first with a drop off.
                    A job that only reached "go to pickup" is counted as cancelled.</li>
                <li>Everything outside a job counts as between jobs. Time stopped and driving after a drop off are worked out later
                    from the GPS points.</li>
                <li>The phone position is saved with each press when the browser allows it. If it does not, the press is still saved.</li>
                <li>Undo last removes the last button together with the steps filled in for it. After End logging it opens the session again.</li>
                <li>Use the pencil to correct a time. It has to stay between the buttons before and after it.</li>
            </ul>
        </details>

        <script>
            $(document).ready(function () {
                var csrf = <?= json_encode($csrf) ?>;
                var apiUrl = '<?= BASE_URL ?>/services/api/gps_sessions.php';
                var state = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var busy = false;
                var editingId = null;

                function pad(n) { return (n < 10 ? '0' : '') + n; }

                /** "2026-11-26 14:05:09" -> "14:05:09" */
                function timeOf(value) { return value.substring(11, 19); }

                function minutesText(minutes) {
                    if (minutes < 60) return minutes + ' min';
                    return Math.floor(minutes / 60) + ' h ' + pad(minutes % 60) + ' min';
                }

                function ageText(seconds) {
                    if (seconds < 60) return 'just now';
                    return minutesText(Math.floor(seconds / 60)) + ' ago';
                }

                function showMessage(text, ok) {
                    var box = $('#gpsMessage').empty();
                    if (text) box.append($('<div>').addClass(ok ? 'success-message' : 'error-message').text(text));
                }

                function render() {
                    var session = state.session;
                    var logging = !!(session && session.open);

                    // State line
                    var stateBox = $('#gpsState').empty().attr('class', 'at-gps-state at-gps-state-' + state.stage.code);
                    if (logging) {
                        stateBox.append($('<strong>').text(state.stage.text));
                        stateBox.append($('<span>').text(' since ' + timeOf(state.stage.since)));
                        stateBox.append($('<div>').addClass('at-gps-sub').text(
                            'Logging since ' + session.started_at.substring(0, 16) + ' (' + minutesText(session.minutes) + ')'));
                    } else {
                        stateBox.append($('<strong>').text('Not logging'));
                    }

                    // Age of the newest GPS point
                    var fresh = $('#gpsFresh').empty().removeClass('at-gps-fresh-old');
                    if (state.last_point_at === null) {
                        fresh.text('No GPS points imported yet.');
                    } else {
                        var age = Math.max(0, state.now - state.last_point_at);
                        fresh.text('Latest GPS point: ' + ageText(age));
                        if (logging && age > 1800) {
                            fresh.addClass('at-gps-fresh-old').append(' ⚠️ Check that GPSLogger is running and uploading.');
                        }
                    }

                    // Buttons
                    $('[data-press]').each(function () {
                        var type = $(this).data('press');
                        var disabled = busy || (type === 'start' ? logging : !logging);
                        $(this).prop('disabled', disabled);
                    });
                    $('#gpsUndo').prop('disabled', busy || !state.can_undo);

                    renderEvents();
                    renderRecent();
                }

                function renderEvents() {
                    var box = $('#gpsEvents').empty();
                    if (!state.session || !state.events.length) {
                        box.append($('<p>').addClass('at-update-empty').text('No events yet.'));
                        $('#gpsEventsTitle').text('Events');
                        return;
                    }
                    $('#gpsEventsTitle').text(state.session.open ? 'Events of this session' : 'Events of the last session');

                    var table = $('<table>').addClass('at-update-table');
                    table.append($('<thead>').append($('<tr>')
                        .append($('<th>').text('Time'))
                        .append($('<th>').text('Event'))
                        .append($('<th>').text(''))));
                    var body = $('<tbody>').appendTo(table);
                    var firstDate = state.events[0].time.substring(0, 10);

                    // Newest first, so the latest press is at the top while driving
                    var events = state.events.slice().reverse();
                    $.each(events, function (index, ev) {
                        var row = $('<tr>').toggleClass('at-gps-auto', ev.auto);
                        var date = ev.time.substring(0, 10);
                        var timeCell = $('<td>');

                        if (editingId === ev.id) {
                            var input = $('<input type="datetime-local" step="1" class="at-gps-time-input">')
                                .val(ev.time.replace(' ', 'T'));
                            timeCell.append(input);
                            var actions = $('<td>')
                                .append($('<button type="button" class="page-action-btn save at-gps-small">').text('Save').on('click', function () {
                                    send('edit_time', { event_id: ev.id, time: input.val() }, function () { editingId = null; });
                                }))
                                .append(' ')
                                .append($('<button type="button" class="page-action-btn back at-gps-small">').text('Cancel').on('click', function () {
                                    editingId = null;
                                    render();
                                }));
                            row.append(timeCell).append($('<td>').text(ev.label)).append(actions);
                        } else {
                            timeCell.text((date !== firstDate ? date.substring(5) + ' ' : '') + timeOf(ev.time)).attr('title', date);
                            var label = $('<td>').text(ev.label);
                            if (ev.auto) label.append($('<span>').addClass('at-gps-tag').text('filled in'));
                            if (ev.has_location) label.append($('<span>').addClass('at-gps-tag at-gps-tag-pos').text('📍'));
                            var tools = $('<td>');
                            if (ev.editable) {
                                tools.append($('<button type="button" class="at-gps-icon" title="Change time">✏️</button>').on('click', function () {
                                    editingId = ev.id;
                                    render();
                                }));
                            }
                            if (ev.deletable) {
                                tools.append($('<button type="button" class="at-gps-icon" title="Delete">🗑️</button>').on('click', function () {
                                    if (confirm('Delete "' + ev.label + '" and the steps filled in for it?')) {
                                        send('delete', { event_id: ev.id });
                                    }
                                }));
                            }
                            row.append(timeCell).append(label).append(tools);
                        }
                        body.append(row);
                    });
                    box.append(table);
                }

                function renderRecent() {
                    var box = $('#gpsRecent').empty();
                    if (!state.recent.length) {
                        box.append($('<p>').addClass('at-update-empty').text('No sessions yet.'));
                        return;
                    }
                    var table = $('<table>').addClass('at-update-table');
                    table.append($('<thead>').append($('<tr>')
                        .append($('<th>').text('Started'))
                        .append($('<th>').text('Ended'))
                        .append($('<th>').text('Length'))
                        .append($('<th>').text('Uber jobs'))
                        .append($('<th>').text('Apex jobs'))));
                    var body = $('<tbody>').appendTo(table);
                    $.each(state.recent, function (index, s) {
                        body.append($('<tr>')
                            .append($('<td>').text(s.started_at.substring(0, 16)))
                            .append($('<td>').text(s.ended_at ? s.ended_at.substring(0, 16) : 'running'))
                            .append($('<td>').text(minutesText(s.minutes)))
                            .append($('<td>').text(s.uber_jobs))
                            .append($('<td>').text(s.apex_jobs)));
                    });
                    box.append(table);
                }

                /** Ask for the phone position and attach it to the press. A refusal or a timeout is fine. */
                function capturePosition(eventId) {
                    if (!eventId || !navigator.geolocation) return;
                    navigator.geolocation.getCurrentPosition(function (position) {
                        $.ajax({
                            type: 'POST',
                            url: apiUrl + '?action=location',
                            data: {
                                csrf: csrf,
                                event_id: eventId,
                                lat: position.coords.latitude,
                                lon: position.coords.longitude,
                                acc: position.coords.accuracy
                            },
                            dataType: 'json',
                            success: function (response) {
                                if (response.state && !busy) {
                                    state = response.state;
                                    render();
                                }
                            }
                        });
                    }, function () { /* position denied or not found in time: the press is already saved */ },
                        { enableHighAccuracy: true, timeout: 8000, maximumAge: 15000 });
                }

                function send(action, data, beforeRender) {
                    if (busy) return;
                    busy = true;
                    render();
                    $.ajax({
                        type: 'POST',
                        url: apiUrl + '?action=' + action,
                        data: $.extend({ csrf: csrf }, data || {}),
                        dataType: 'json',
                        success: function (response) {
                            if (response.state) state = response.state;
                            if (beforeRender && response.success) beforeRender();
                            if (action !== 'state') showMessage(response.message, response.success);
                            busy = false;
                            render();
                            if (action === 'press' && response.success && response.event_id) {
                                capturePosition(response.event_id);
                            }
                        },
                        error: function (xhr) {
                            var message = '❌ Request failed. Please reload and try again.';
                            try {
                                var body = JSON.parse(xhr.responseText);
                                if (body && body.message) message = body.message;
                            } catch (ignore) { }
                            showMessage(message, false);
                            busy = false;
                            render();
                        }
                    });
                }

                $('[data-press]').on('click', function () {
                    var type = $(this).data('press');
                    if (type === 'start' && !confirm('Is GPSLogger running?')) return;
                    send('press', { type: type });
                });

                $('#gpsUndo').on('click', function () {
                    if (state.session && !state.session.open && !confirm('Reopen the session that was ended?')) return;
                    send('undo');
                });

                render();
            });
        </script>
    <?php endif; ?>
</div>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
