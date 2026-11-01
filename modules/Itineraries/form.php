<?php
// modules/Itineraries/form.php
// Shared form for add.php and edit.php. Included by those pages, not opened directly.
// Expects: $pdo, $itn (array for edit, null for add), $stopsData (list of stops), $destinations, $costs, $timeOptions
// and, for add only, $contacts. Optional: $distancePayload (stored legs, edit only), $ratePerKm.

if (!isset($pdo, $destinations, $costs, $timeOptions)) {
    exit;
}

$isEdit     = !empty($itn['id']);
$itn        = $itn ?? [];
$stopsData  = $stopsData ?? [];
$contacts   = $contacts ?? [];
$distancePayload = $distancePayload ?? null;
$ratePerKm       = (float) ($ratePerKm ?? 0);
$returnChecked   = !empty($itn['return_to_pickup']);

$savedPickup = $itn['pickup'] ?? '';
$savedDest   = $returnChecked ? '' : ($itn['destination'] ?? '');   // a returning trip ends at the pickup, so no destination is shown
$savedCost   = $itn['cost'] ?? '';
$destNames   = array_column($destinations, 'name');
$costKeys    = array_map(fn($c) => number_format((float)$c['amount'], 2), $costs);

$showOtherPickup = $savedPickup !== '' && !in_array($savedPickup, $destNames, true);
$showOtherDest   = $savedDest !== '' && !in_array($savedDest, $destNames, true);
$showOtherCost   = $savedCost !== '' && !in_array(number_format((float)$savedCost, 2), $costKeys, true);
$savedTime       = !empty($itn['start_time']) ? substr($itn['start_time'], 0, 5) : '';

$jsStops = array_map(fn($s) => [
    'location'       => $s['location'] ?? '',
    'place_id'       => $s['place_id'] ?? '',
    'arrival_time'   => !empty($s['arrival_time']) ? substr($s['arrival_time'], 0, 5) : '',
    'time_fixed'     => $s['time_fixed'] ?? null,
    'wait_minutes'   => $s['wait_minutes'] ?? '',
    'client_notes'   => $s['client_notes'] ?? '',
    'internal_notes' => $s['internal_notes'] ?? '',
], $stopsData);
?>

<div class="form-container">
    <?php if (!$isEdit): ?>
        <p class="form-intro">Plan a day trip with stops between the pickup and the destination. Only the client and date are required; everything else can be filled in as the plan firms up.</p>
    <?php endif; ?>

    <form id="itineraryForm">
        <?php if ($isEdit): ?>
            <input type="hidden" id="itinerary_id" name="id" value="<?= (int)$itn['id'] ?>">
            <div class="form-group">
                <label>Client</label>
                <input type="text" value="<?= e($itn['client_name'] ?? '') ?>" disabled>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label for="contactSearch">Client Name <span class="required">*</span></label>
                <input type="text" id="contactSearch" placeholder="Search client..." required>
                <div id="contactSuggestions" class="suggestions-box"></div>
                <input type="hidden" id="contact_id" name="contact_id" required>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="passengerName">Picking Up (if not the client)</label>
                <input type="text" id="passengerName" name="passenger_name" placeholder="Name of person being collected"
                    value="<?= e($itn['passenger_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="passengerPhone">Passenger Phone</label>
                <input type="tel" id="passengerPhone" name="passenger_phone"
                    value="<?= e($itn['passenger_phone'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="trip_date">Date <span class="required">*</span></label>
                <input type="date" id="trip_date" name="trip_date" value="<?= e($itn['trip_date'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="start_time">Start Time <small>(optional)</small></label>
                <select id="start_time" name="start_time">
                    <option value="">— Not known yet —</option>
                    <?php foreach ($timeOptions as $time): ?>
                        <option value="<?= e($time) ?>" <?= $savedTime === $time ? 'selected' : '' ?>><?= e($time) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="pickup">Pickup Location <small>(optional)</small></label>
            <select id="pickup" name="pickup">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= e($dest['name']) ?>" <?= (!$showOtherPickup && $savedPickup === $dest['name']) ? 'selected' : '' ?>><?= e($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherPickup ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>
        <div class="form-group <?= $showOtherPickup ? '' : 'hidden' ?>" id="otherPickupGroup">
            <label for="otherPickup">Specify Other Pickup</label>
            <input type="text" id="otherPickup" placeholder="Enter pickup address" autocomplete="off"
                   data-place-id="<?= $showOtherPickup ? e($itn['pickup_place_id'] ?? '') : '' ?>"
                   value="<?= $showOtherPickup ? e($savedPickup) : '' ?>">
        </div>

        <div class="at-itn-stops-section">
            <h3 class="at-itn-stops-title">Stops <small>(between pickup and destination)</small></h3>
            <p class="at-itn-stops-empty" id="stopsEmpty">No stops yet. Add the first one below.</p>
            <div id="stopsList"></div>
            <button type="button" class="page-action-btn add" id="addStopBtn">➕ Add Stop</button>
            <button type="button" class="page-action-btn gps" id="recalcBtn">📏 Recalculate Distance</button>
            <p class="at-itn-leg at-itn-leg-info hidden" id="placesNote"></p>
            <datalist id="itnPlaces">
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= e($dest['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="returnToPickup" <?= $returnChecked ? 'checked' : '' ?>>
                🔁 Return to pickup
            </label>
            <small>Tick this if the trip ends back at the pickup. Otherwise choose where it ends below.</small>
        </div>
        <div class="at-itn-leg" id="legReturn"></div>

        <div class="form-group <?= $returnChecked ? 'hidden' : '' ?>" id="destinationGroup">
            <label for="destination">Destination <span class="required">*</span></label>
            <select id="destination" name="destination">
                <option value="">— Choose destination —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= e($dest['name']) ?>" <?= (!$showOtherDest && $savedDest === $dest['name']) ? 'selected' : '' ?>><?= e($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherDest ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>
        <div class="form-group <?= ($showOtherDest && !$returnChecked) ? '' : 'hidden' ?>" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination</label>
            <input type="text" id="otherDestination" placeholder="Enter destination" autocomplete="off"
                   data-place-id="<?= $showOtherDest ? e($itn['destination_place_id'] ?? '') : '' ?>"
                   value="<?= $showOtherDest ? e($savedDest) : '' ?>">
        </div>
        <div class="at-itn-leg" id="legFinal"></div>
        <div class="at-itn-time-note" id="etaNote"></div>
        <div class="at-itn-distance-summary" id="distanceSummary"></div>

        <div class="form-group">
            <label for="cost">Cost <small>(optional)</small></label>
            <select id="cost" name="cost">
                <option value="">— Not known yet —</option>
                <?php foreach ($costs as $c):
                    $key = number_format((float)$c['amount'], 2); ?>
                    <option value="<?= e($c['amount']) ?>" <?= (!$showOtherCost && $savedCost !== '' && number_format((float)$savedCost, 2) === $key) ? 'selected' : '' ?>>R<?= e($key) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherCost ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>
        <div class="form-group <?= $showOtherCost ? '' : 'hidden' ?>" id="otherCostGroup">
            <label for="otherCost">Specify Other Cost</label>
            <input type="number" id="otherCost" step="0.01" min="0" placeholder="0.00"
                   value="<?= $showOtherCost ? e((string)$savedCost) : '' ?>">
        </div>
        <div class="at-itn-cost-ref" id="costReference"></div>

        <div class="form-group">
            <label for="description">Notes / Description <small>(optional)</small></label>
            <textarea id="description" name="description"
                      placeholder="Any relevant info about this trip..."><?= e($itn['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn" id="submitBtn"><?= $isEdit ? '💾 Save Changes' : '🗺️ Save Itinerary' ?></button>
        <?php if ($isEdit): ?>
            <a href="<?= BASE_URL ?>/modules/Itineraries/" class="page-action-btn back">← Cancel</a>
        <?php endif; ?>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
$(document).ready(function () {
    var isEdit      = <?= $isEdit ? 'true' : 'false' ?>;
    var itnContactId = <?= (int) ($itn['contact_id'] ?? 0) ?>;
    var timeOptions = <?= json_encode(array_values($timeOptions)) ?>;
    var initial     = <?= json_encode($jsStops, JSON_UNESCAPED_UNICODE) ?>;
    var storedDistance = <?= json_encode($distancePayload, JSON_UNESCAPED_UNICODE) ?>;
    var ratePerKm   = <?= json_encode($ratePerKm) ?>;
    var maxStops    = <?= (int)(defined('ITINERARY_MAX_STOPS') ? ITINERARY_MAX_STOPS : 30) ?>;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    // ---------- "Other" toggles ----------
    function bindOther(selectId, groupId, inputId) {
        $(selectId).on('change', function () {
            if ($(this).val() === 'other') {
                $(groupId).removeClass('hidden');
            } else {
                $(groupId).addClass('hidden');
                $(inputId).val('');
            }
        });
    }
    bindOther('#pickup', '#otherPickupGroup', '#otherPickup');
    bindOther('#destination', '#otherDestinationGroup', '#otherDestination');
    bindOther('#cost', '#otherCostGroup', '#otherCost');

    // ---------- Address suggestions (Google Places) ----------
    // Picking a suggestion stores the exact Google place, so distances do not depend on Google guessing
    // what a typed name means. If the Maps script cannot load, every field still works as plain text.
    var placesReady = false;
    var pendingPlaces = [];

    function placesUnavailable(reason) {
        $('#placesNote').text('Address suggestions are unavailable, so type full addresses. (' + reason + ')').removeClass('hidden');
        $('#stopsList .at-itn-location').attr('list', 'itnPlaces');
        if (window.logJsError) {
            logJsError('ERROR', 'Itinerary address suggestions unavailable: ' + reason, { page_url: window.location.href });
        }
    }

    function attachPlaces(el) {
        if (!el) { return; }
        $(el).on('input', function () { delete el.dataset.placeId; });
        $(el).on('keydown', function (e) {
            if (e.key === 'Enter' && $('.pac-container:visible').length) { e.preventDefault(); }
        });
        if (!placesReady) { pendingPlaces.push(el); return; }
        initAutocomplete(el);
    }

    function initAutocomplete(el) {
        try {
            var ac = new google.maps.places.Autocomplete(el, {
                componentRestrictions: { country: 'za' },
                fields: ['place_id', 'formatted_address', 'name']
            });
            ac.addListener('place_changed', function () {
                var p = ac.getPlace();
                if (!p || !p.place_id) { return; }
                var addr = p.formatted_address || '';
                var nm   = p.name || '';
                el.value = (nm && addr.indexOf(nm) !== 0) ? (addr ? nm + ', ' + addr : nm) : (addr || el.value);
                el.dataset.placeId = p.place_id;
                markDistanceStale();
                scheduleLive();
            });
        } catch (err) {
            placesUnavailable('Autocomplete error');
        }
    }

    window.itnOnMapsLoaded = function () {
        placesReady = true;
        pendingPlaces.forEach(initAutocomplete);
        pendingPlaces = [];
    };
    window.gm_authFailure = function () {
        placesUnavailable('Google rejected the map key for this site. Check its allowed websites.');
    };
    window.itnOnMapsError = function () { placesUnavailable('the Google Maps script did not load'); };

    attachPlaces(document.getElementById('otherPickup'));
    attachPlaces(document.getElementById('otherDestination'));

    // ---------- Distances ----------
    function formatMoney(n) {
        return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function setLeg($el, leg) {
        $el.attr('class', 'at-itn-leg' + (leg ? ' at-itn-leg-' + leg.level : '')).text(leg ? leg.message : '');
    }

    function renderDistance(d, passive) {
        if (!d) { return; }
        lastLegs = d;
        legsFresh = true;
        var $rows = $list.children('.at-itn-stop');
        $rows.find('.at-itn-leg').attr('class', 'at-itn-leg').text('');
        setLeg($('#legFinal'), null);
        setLeg($('#legReturn'), null);

        d.legs.forEach(function (leg) {
            if (leg.kind === 'stop') {
                var $row = $rows.eq(leg.stop_index);
                if ($row.length) { setLeg($row.find('.at-itn-leg'), leg); }
            } else if (leg.kind === 'destination') {
                setLeg($('#legFinal'), leg);
            } else if (leg.kind === 'return') {
                setLeg($('#legReturn'), leg);
            }
        });

        var $sum = $('#distanceSummary');
        var $ref = $('#costReference');
        if (d.total_km !== null && d.total_km !== undefined) {
            var partial = d.unknown_legs > 0 ? ' (partial: ' + d.unknown_legs + ' leg' + (d.unknown_legs > 1 ? 's' : '') + ' without a distance)' : '';
            $sum.attr('class', 'at-itn-distance-summary').text('📍 Total distance: ' + Number(d.total_km).toFixed(1) + ' km' + partial);
            if (ratePerKm > 0) {
                $ref.attr('class', 'at-itn-cost-ref').text('Reference cost (display only, the cost above is yours to set): R' +
                    formatMoney(d.total_km * ratePerKm) + ' = ' + Number(d.total_km).toFixed(1) + ' km × R' + ratePerKm.toFixed(2) + '/km' + partial);
            }
        } else {
            $sum.attr('class', 'at-itn-distance-summary').text('');
            $ref.attr('class', 'at-itn-cost-ref').text('');
        }
        updateChain(!!passive);
    }

    // Return to pickup: the trip ends at the pickup, so the destination is hidden and not needed
    function syncReturnTick() {
        var on = $('#returnToPickup').is(':checked');
        if (on) {
            $('#destinationGroup, #otherDestinationGroup').addClass('hidden');
            $('#destination').val('');
            $('#otherDestination').val('');
            delete $('#otherDestination')[0].dataset.placeId;
            setLeg($('#legFinal'), null);
        } else {
            $('#destinationGroup').removeClass('hidden');
            $('#destination').trigger('change');
        }
    }
    $('#returnToPickup').on('change', syncReturnTick);

    // ---------- Arrival times that follow the route ----------
    // Each stop is either "fixed" (a time chosen by hand, never changed by the page) or automatic (it follows the
    // previous stop: previous arrival + its wait + the drive). Automatic times are rounded up to the next quarter hour.
    var lastLegs  = null;     // the legs (with drive minutes) behind what is on screen
    var legsFresh = false;    // false while the route has changed and its legs are being looked up again

    function toMin(hhmm) {
        var m = /^(\d{2}):(\d{2})/.exec(hhmm || '');
        return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : null;
    }
    function fmtMin(n) {
        return ('0' + Math.floor(n / 60)).slice(-2) + ':' + ('0' + (n % 60)).slice(-2);
    }
    function legMinutes(kind, idx) {
        if (!lastLegs) { return null; }
        for (var i = 0; i < lastLegs.legs.length; i++) {
            var l = lastLegs.legs[i];
            if (l.kind === kind && (kind !== 'stop' || l.stop_index === idx)) {
                return (l.minutes === undefined || l.minutes === null) ? null : parseInt(l.minutes, 10);
            }
        }
        return null;
    }
    function setTimeNote($el, level, text) {
        $el.attr('class', 'at-itn-time-note' + (level ? ' at-itn-time-' + level : '')).text(text || '');
    }

    // passive = only show notes and warnings, do not touch any automatic time (used when the page opens and after a save)
    function updateChain(passive) {
        if (!lastLegs || !legsFresh) { return; }
        var depart = toMin($('#start_time').val());     // when the trip leaves the pickup
        var $rows  = $list.children('.at-itn-stop');

        $rows.each(function (i) {
            var $r     = $(this);
            var $note  = $r.find('.at-itn-time-note');
            var $sel   = $r.find('.at-itn-arrival');
            var fixed  = $r.find('.at-itn-fixed').is(':checked');
            var drive  = legMinutes('stop', i);
            var prevLabel = i === 0 ? 'the pickup time' : 'stop ' + i;
            var prevDepart = depart;
            var suggested = (prevDepart !== null && drive !== null) ? Math.ceil((prevDepart + drive) / 15) * 15 : null;
            if (suggested !== null && suggested >= 1440) { suggested = null; }
            var arrival;

            if (fixed || passive) {
                arrival = toMin($sel.val());
            } else if (suggested !== null) {
                $sel.val(fmtMin(suggested));
                arrival = suggested;
            } else {
                $sel.val('');
                arrival = null;
            }

            if (fixed && arrival !== null && prevDepart !== null) {
                if (arrival < prevDepart) {
                    setTimeNote($note, 'warn', '⚠️ This is before ' + prevLabel + ' is done (leaves ' + fmtMin(prevDepart % 1440) + ').');
                } else if (drive !== null && arrival < prevDepart + drive) {
                    setTimeNote($note, 'warn', '⚠️ Tight: the drive takes about ' + drive + ' min, so the earliest arrival is ' + (suggested !== null ? fmtMin(suggested) : fmtMin((prevDepart + drive) % 1440)) + '.');
                } else if (suggested !== null) {
                    setTimeNote($note, 'info', 'Earliest arrival: ' + fmtMin(suggested) + ' (drive about ' + drive + ' min).');
                } else {
                    setTimeNote($note, '', '');
                }
            } else if (!fixed) {
                if (arrival !== null && drive !== null) {
                    setTimeNote($note, 'info', 'Follows ' + prevLabel + ' (drive about ' + drive + ' min). Pick a time to fix it.');
                } else if (prevDepart === null) {
                    setTimeNote($note, 'info', i === 0 ? 'Choose the start time at the top to get a suggested arrival.' : 'Set the previous stop\'s time to get a suggested arrival.');
                } else {
                    setTimeNote($note, 'info', 'No drive time yet for this leg.');
                }
            } else {
                setTimeNote($note, '', '');
            }

            var wait = parseInt($r.find('.at-itn-wait').val(), 10) || 0;
            depart = arrival === null ? null : arrival + wait;
        });

        // Estimated arrival at the end of the trip
        var endKind = $('#returnToPickup').is(':checked') ? 'return' : 'destination';
        var endDrive = legMinutes(endKind, null);
        if (depart !== null && endDrive !== null) {
            var eta = depart + endDrive;
            setTimeNote($('#etaNote'), 'info', '🕒 Estimated arrival ' + (endKind === 'return' ? 'back at the pickup' : 'at the destination') + ': ' + fmtMin(eta % 1440) + (eta >= 1440 ? ' (after midnight)' : ''));
        } else {
            setTimeNote($('#etaNote'), '', '');
        }
    }

    $list.on('change', '.at-itn-arrival', function () {
        // Choosing a time by hand fixes it; choosing "TBC" hands the stop back to the automatic times
        $(this).closest('.at-itn-stop').find('.at-itn-fixed').prop('checked', $(this).val() !== '');
        updateChain(false);
    });
    $list.on('change', '.at-itn-fixed, .at-itn-wait', function () { updateChain(false); });
    $('#start_time').on('change', function () { updateChain(false); });

    // ---------- Live distances while editing ----------
    // Each leg is looked up once (by its two ends) and remembered, so moving a stop only asks Google about legs that are new.
    var legCache = {};
    var liveTimer = null;
    var liveSeq = 0;

    function scheduleLive() {
        legsFresh = false;
        clearTimeout(liveTimer);
        liveTimer = setTimeout(liveCalc, 700);
    }

    function routePoints() {
        var d = currentRouteData();
        var pts = [{ text: d.pickup, pid: d.pickup_place_id, kind: 'pickup', idx: null }];
        readStops().forEach(function (s, i) { pts.push({ text: s.location, pid: s.place_id, kind: 'stop', idx: i }); });
        if (d.return_to_pickup === '1' && d.pickup !== '') {
            pts.push({ text: d.pickup, pid: d.pickup_place_id, kind: 'return', idx: null });
        } else {
            pts.push({ text: d.destination, pid: d.destination_place_id, kind: 'destination', idx: null });
        }
        return pts;
    }

    function liveCalc() {
        var seq = ++liveSeq;
        var contact = currentRouteData().contact_id;
        var pts = routePoints();
        var legs = [];
        var todo = [];

        for (var i = 1; i < pts.length; i++) {
            var a = pts[i - 1], b = pts[i];
            if (!a.text || !b.text) {
                legs[i - 1] = { kind: b.kind, stop_index: b.idx, from: a.text, to: b.text, km: null, minutes: null, status: 'missing', level: 'info',
                                message: 'Add both ends of this leg to calculate its distance.' };
                continue;
            }
            var key = [a.text, a.pid, b.text, b.pid, b.kind, contact].join('|');
            if (legCache[key]) {
                legs[i - 1] = $.extend({}, legCache[key], { kind: b.kind, stop_index: b.idx });
            } else {
                todo.push({ at: i - 1, key: key, a: a, b: b });
            }
        }

        function finish() {
            if (seq !== liveSeq) { return; }
            var total = 0, known = 0, unknown = 0, problems = false;
            legs.forEach(function (l) {
                if (l.km !== null && l.km !== undefined) { total += parseFloat(l.km); known++; } else { unknown++; }
                if (l.level === 'warn' || l.level === 'fail') { problems = true; }
            });
            renderDistance({
                legs: legs, total_km: known > 0 ? Math.round(total * 10) / 10 : null,
                unknown_legs: unknown, problems: problems, return_to_pickup: $('#returnToPickup').is(':checked')
            }, false);
        }

        function next() {
            if (seq !== liveSeq) { return; }
            if (!todo.length) { finish(); return; }
            var t = todo.shift();
            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/modules/Itineraries/api/index.php?action=calc_leg',
                data: { from: t.a.text, from_place_id: t.a.pid, to: t.b.text, to_place_id: t.b.pid, kind: t.b.kind, contact_id: contact },
                dataType: 'json',
                success: function (res) {
                    if (res.success && res.leg) {
                        legCache[t.key] = res.leg;
                        legs[t.at] = $.extend({}, res.leg, { stop_index: t.b.idx });
                    } else {
                        legs[t.at] = failedLeg(t);
                    }
                    next();
                },
                error: function () { legs[t.at] = failedLeg(t); next(); }
            });
        }

        function failedLeg(t) {
            return { kind: t.b.kind, stop_index: t.b.idx, from: t.a.text, to: t.b.text, km: null, minutes: null, status: 'error', level: 'fail',
                     message: '❌ The distance lookup failed (network or Google error). Try Recalculate.' };
        }

        if (todo.length) {
            $('#distanceSummary').attr('class', 'at-itn-distance-summary').text('📏 Calculating distances…');
        }
        next();
    }

    $('#itineraryForm').on('change', '#otherPickup, #otherDestination, #pickup, #destination, #returnToPickup, .at-itn-location', scheduleLive);

    // Anything that can change a distance dims the old figures until they are recalculated
    function markDistanceStale() {
        $('#stopsList .at-itn-leg, #legFinal, #legReturn').addClass('at-itn-stale');
        $('#distanceSummary, #costReference').addClass('at-itn-stale');
    }
    $('#itineraryForm').on('input change', '#otherPickup, #otherDestination, #pickup, #destination, #returnToPickup, .at-itn-location', markDistanceStale);

    function currentRouteData() {
        var pickupOther = $('#pickup').val() === 'other';
        var destOther   = $('#destination').val() === 'other';
        return {
            pickup:               pickupOther ? $('#otherPickup').val().trim() : $('#pickup').val(),
            pickup_place_id:      pickupOther ? ($('#otherPickup')[0].dataset.placeId || '') : '',
            destination:          destOther ? $('#otherDestination').val().trim() : $('#destination').val(),
            destination_place_id: destOther ? ($('#otherDestination')[0].dataset.placeId || '') : '',
            return_to_pickup:     $('#returnToPickup').is(':checked') ? '1' : '',
            contact_id:           $('#contact_id').val() || itnContactId || ''
        };
    }

    // ---------- Stops editor ----------
    var $list = $('#stopsList');

    function buildStopRow(stop) {
        var $row = $(
            '<div class="at-itn-stop">' +
                '<div class="at-itn-stop-head">' +
                    '<strong class="at-itn-stop-num"></strong>' +
                    '<div class="at-itn-stop-btns">' +
                        '<button type="button" class="at-itn-icon-btn at-itn-up" title="Move up">▲</button>' +
                        '<button type="button" class="at-itn-icon-btn at-itn-down" title="Move down">▼</button>' +
                        '<button type="button" class="at-itn-icon-btn at-itn-remove" title="Remove stop">✕</button>' +
                    '</div>' +
                '</div>' +
                '<div class="form-group">' +
                    '<label>Location <span class="required">*</span></label>' +
                    '<input type="text" class="at-itn-location" maxlength="255" autocomplete="off" placeholder="Where is this stop?">' +
                    '<div class="at-itn-leg"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>Arrival Time <small>(optional)</small></label><select class="at-itn-arrival"></select>' +
                        '<label class="at-itn-fixed-label"><input type="checkbox" class="at-itn-fixed"> 🔒 This time is fixed</label></div>' +
                    '<div class="form-group"><label>Wait <small>(optional, hh:mm)</small></label><select class="at-itn-wait"></select></div>' +
                '</div>' +
                '<div class="at-itn-time-note"></div>' +
                '<div class="form-group"><label>Note for the client <small>(optional)</small></label><textarea class="at-itn-client-notes"></textarea></div>' +
                '<div class="form-group"><label>Internal note <small>(optional, not for the client)</small></label><textarea class="at-itn-internal-notes"></textarea></div>' +
            '</div>'
        );

        var $arrival = $row.find('.at-itn-arrival');
        $arrival.append($('<option>', { value: '', text: '— TBC —' }));
        timeOptions.forEach(function (t) {
            $arrival.append($('<option>', { value: t, text: t }));
        });

        // Wait: none, then 15-minute steps up to 5 hours, shown as hh:mm
        var $wait = $row.find('.at-itn-wait');
        $wait.append($('<option>', { value: '', text: '— None —' }));
        for (var m = 15; m <= 300; m += 15) {
            $wait.append($('<option>', { value: String(m), text: ('0' + Math.floor(m / 60)).slice(-2) + ':' + ('0' + (m % 60)).slice(-2) }));
        }

        var locEl = $row.find('.at-itn-location')[0];
        locEl.value = stop.location || '';
        if (stop.place_id) { locEl.dataset.placeId = stop.place_id; }
        attachPlaces(locEl);
        $arrival.val(stop.arrival_time || '');
        // Stops saved before the fixed tick existed count as fixed when they have a time, so nothing planned changes
        var fixedNow = (stop.time_fixed === null || stop.time_fixed === undefined || stop.time_fixed === '')
            ? !!stop.arrival_time
            : String(stop.time_fixed) === '1';
        $row.find('.at-itn-fixed').prop('checked', fixedNow);
        // Older saves could hold any number of minutes: round to the nearest 15, capped at 5 hours
        var waitMin = parseInt(stop.wait_minutes, 10);
        waitMin = isNaN(waitMin) || waitMin <= 0 ? '' : Math.min(300, Math.round(waitMin / 15) * 15);
        $row.find('.at-itn-wait').val(waitMin === '' ? '' : String(waitMin));
        $row.find('.at-itn-client-notes').val(stop.client_notes || '');
        $row.find('.at-itn-internal-notes').val(stop.internal_notes || '');
        return $row;
    }

    function readStops() {
        var stops = [];
        $list.children('.at-itn-stop').each(function () {
            var $r = $(this);
            stops.push({
                location:       $r.find('.at-itn-location').val().trim(),
                place_id:       $r.find('.at-itn-location')[0].dataset.placeId || '',
                arrival_time:   $r.find('.at-itn-arrival').val(),
                time_fixed:     $r.find('.at-itn-fixed').is(':checked') ? '1' : '0',
                wait_minutes:   $r.find('.at-itn-wait').val(),
                client_notes:   $r.find('.at-itn-client-notes').val().trim(),
                internal_notes: $r.find('.at-itn-internal-notes').val().trim()
            });
        });
        return stops;
    }

    function renderStops(stops) {
        $list.empty();
        stops.forEach(function (s) { $list.append(buildStopRow(s)); });
        refreshStopChrome();
    }

    function refreshStopChrome() {
        var $rows = $list.children('.at-itn-stop');
        var count = $rows.length;
        $rows.each(function (i) {
            $(this).find('.at-itn-stop-num').text('Stop ' + (i + 1));
            $(this).find('.at-itn-up').prop('disabled', i === 0);
            $(this).find('.at-itn-down').prop('disabled', i === count - 1);
        });
        $('#stopsEmpty').toggle(count === 0);
        $('#addStopBtn').prop('disabled', count >= maxStops);
    }

    $('#addStopBtn').on('click', function () {
        var $row = buildStopRow({});
        $list.append($row);
        refreshStopChrome();
        markDistanceStale();
        scheduleLive();
        $row.find('.at-itn-location').focus();
    });

    $list.on('click', '.at-itn-remove', function () {
        var $row = $(this).closest('.at-itn-stop');
        var loc  = $row.find('.at-itn-location').val().trim();
        if (loc !== '' && !confirm('Remove this stop?')) { return; }
        $row.remove();
        refreshStopChrome();
        markDistanceStale();
        scheduleLive();
    });

    $list.on('click', '.at-itn-up', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.prev('.at-itn-stop').before($row);
        refreshStopChrome();
        markDistanceStale();
        scheduleLive();
    });

    $list.on('click', '.at-itn-down', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.next('.at-itn-stop').after($row);
        refreshStopChrome();
        markDistanceStale();
        scheduleLive();
    });

    renderStops(initial);

    // Distances: what was just calculated before a redirect, otherwise what is stored
    var shownDistance = storedDistance;
    if (isEdit) {
        try {
            var key = 'itnLegs' + $('#itinerary_id').val();
            var fresh = window.sessionStorage.getItem(key);
            if (fresh) {
                shownDistance = JSON.parse(fresh);
                window.sessionStorage.removeItem(key);
            }
        } catch (err) { /* storage can be blocked; the stored figures are shown instead */ }
    }
    renderDistance(shownDistance, true);

    // ---------- Recalculate (preview of what is in the form, nothing is saved) ----------
    $('#recalcBtn').on('click', function () {
        var stops = readStops();
        var bad = -1;
        stops.forEach(function (s, i) { if (bad < 0 && s.location === '') { bad = i; } });
        if (bad >= 0) {
            $('#result').html('<div class="error-message">⚠️ Stop ' + (bad + 1) + ' needs a location, or remove it.</div>');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text('Calculating…');
        $('#result').html('');
        var data = currentRouteData();
        data.stops = JSON.stringify(stops);
        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Itineraries/api/index.php?action=calc_legs',
            data: data,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    renderDistance(res.distance, false);
                } else {
                    $('#result').html('<div class="error-message">❌ ' + escapeHtml(res.message || 'Could not calculate.') + '</div>');
                }
            },
            error: function () {
                $('#result').html('<div class="error-message">❌ The distance request failed. Please try again.</div>');
            },
            complete: function () { btn.prop('disabled', false).text('📏 Recalculate Distance'); }
        });
    });

    // ---------- Client search (add only) ----------
    if (!isEdit) {
        document.getElementById('trip_date').min = new Date().toISOString().split('T')[0];

        var contactSearch  = $('#contactSearch');
        var suggestionsBox = $('#contactSuggestions');
        var contactIdInput = $('#contact_id');
        var clients        = <?= json_encode($contacts ?: [], JSON_UNESCAPED_UNICODE) ?>;
        var selected       = -1;

        contactSearch.on('input focus', function () {
            var query = $(this).val().trim().toLowerCase();
            contactIdInput.val('');
            if (query.length === 0) { suggestionsBox.hide(); return; }
            var filtered = clients.filter(function (c) {
                return (c.name && c.name.toLowerCase().includes(query)) ||
                       (c.phone && c.phone.toLowerCase().includes(query));
            });
            suggestionsBox.empty();
            if (filtered.length > 0) {
                selected = -1;
                filtered.forEach(function (client) {
                    var item = $('<div class="suggestion-item">' +
                        escapeHtml(client.name) +
                        '<br><small>' + escapeHtml(client.phone || '') + '</small>' +
                        '<br><small>' + escapeHtml(client.address || '') + '</small></div>');
                    item.data('client', client);
                    item.on('click', function () { selectClient($(this).data('client')); });
                    suggestionsBox.append(item);
                });
                suggestionsBox.show();
            } else {
                suggestionsBox.html('<div class="suggestion-item">No clients found</div>').show();
            }
        });

        contactSearch.on('keydown', function (e) {
            var items = suggestionsBox.find('.suggestion-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selected = Math.min(selected + 1, items.length - 1);
                items.removeClass('active');
                if (selected >= 0) { items.eq(selected).addClass('active'); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selected = Math.max(selected - 1, -1);
                items.removeClass('active');
                if (selected >= 0) { items.eq(selected).addClass('active'); }
            } else if (e.key === 'Enter' && selected >= 0) {
                e.preventDefault();
                selectClient(items.eq(selected).data('client'));
            }
        });

        function selectClient(client) {
            contactSearch.val(client.name);
            contactIdInput.val(client.id);
            suggestionsBox.hide();

            var pickupSelect = $('#pickup');
            pickupSelect.empty();
            if (client.address) {
                pickupSelect.append($('<option>', { value: client.address, text: client.address, selected: true }));
            } else {
                pickupSelect.append($('<option>', { value: '', text: '— Not known yet —', selected: true }));
            }
            <?php foreach ($destinations as $dest): ?>
            pickupSelect.append($('<option>', { value: <?= json_encode($dest['name']) ?>, text: <?= json_encode($dest['name']) ?> }));
            <?php endforeach; ?>
            pickupSelect.append($('<option>', { value: 'other', text: 'Other (specify below)' }));
            pickupSelect.trigger('change');
        }

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#contactSearch, #contactSuggestions').length) {
                suggestionsBox.hide();
            }
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') { suggestionsBox.hide(); }
        });
    }

    // ---------- Save ----------
    $('#itineraryForm').on('submit', function (e) {
        e.preventDefault();

        if (!isEdit && !$('#contact_id').val()) {
            $('#result').html('<div class="error-message">⚠️ Please select a client from the list.</div>');
            return;
        }

        var stops = readStops();
        var missing = -1;
        stops.forEach(function (s, i) { if (missing < 0 && s.location === '') { missing = i; } });
        if (missing >= 0) {
            $('#result').html('<div class="error-message">⚠️ Stop ' + (missing + 1) + ' needs a location, or remove it.</div>');
            $list.children('.at-itn-stop').eq(missing).find('.at-itn-location').focus();
            return;
        }

        if (!$('#returnToPickup').is(':checked')) {
            var destVal = $('#destination').val() === 'other' ? $('#otherDestination').val().trim() : $('#destination').val();
            if (!destVal) {
                $('#result').html('<div class="error-message">⚠️ Choose a destination, or tick Return to pickup if the trip ends back at the pickup.</div>');
                ($('#destination').val() === 'other' ? $('#otherDestination') : $('#destination')).focus();
                return;
            }
        }

        var submitBtn = $('#submitBtn');
        var loading   = $('#loading');
        submitBtn.hide();
        loading.show();
        $('#result').html('');

        var costVal   = $('#cost').val() === 'other' ? $('#otherCost').val().trim() : $('#cost').val();

        var data = currentRouteData();
        data.trip_date       = $('#trip_date').val();
        data.start_time      = $('#start_time').val();
        data.cost            = costVal;
        data.description     = $('#description').val().trim();
        data.passenger_name  = $('#passengerName').val().trim();
        data.passenger_phone = $('#passengerPhone').val().trim();
        data.stops           = JSON.stringify(stops);
        if (isEdit) { data.id = $('#itinerary_id').val(); }
        else        { data.contact_id = $('#contact_id').val(); }

        $.ajax({
            type:     'POST',
            url:      '<?= BASE_URL ?>/modules/Itineraries/api/index.php?action=<?= $isEdit ? 'update' : 'add' ?>',
            data:     data,
            dataType: 'json',
            success: function (res) {
                loading.hide();
                submitBtn.show();
                if (res.success) {
                    var d = res.distance || null;

                    // A new itinerary opens on its own edit page, so the distances can be checked and fixed there
                    if (!isEdit) {
                        try { window.sessionStorage.setItem('itnLegs' + res.itinerary_id, JSON.stringify(d)); } catch (err) { /* optional */ }
                        window.location.href = '<?= BASE_URL ?>/modules/Itineraries/edit.php?id=' + res.itinerary_id + '&saved=1';
                        return;
                    }

                    $(document).trigger('itn:saved');
                    if (d) { renderDistance(d, true); }

                    if (d && !d.problems) {
                        $('#result').html('<div class="success-message">✅ ' + escapeHtml(res.message) + '</div>');
                        setTimeout(function () {
                            window.location.href = '<?= BASE_URL ?>/modules/Itineraries/';
                        }, 600);
                    } else if (d) {
                        $('#result').html('<div class="success-message">✅ ' + escapeHtml(res.message) + ' Check the highlighted distances below.</div>');
                    } else {
                        $('#result').html('<div class="success-message">✅ ' + escapeHtml(res.message) + ' The distances could not be calculated, use Recalculate Distance.</div>');
                    }
                } else {
                    $('#result').html('<div class="error-message">❌ ' + escapeHtml(res.message || 'Failed to save.') + '</div>');
                }
            },
            error: function (xhr) {
                loading.hide();
                submitBtn.show();
                $('#result').html('<div class="error-message">❌ Error: ' + escapeHtml(xhr.responseText || 'Unknown error') + '</div>');
            }
        });
    });
});
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(GOOGLE_MAPS_BROWSER_KEY) ?>&libraries=places&callback=itnOnMapsLoaded" async defer onerror="itnOnMapsError()"></script>
