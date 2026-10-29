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
$readOnly   = $isEdit && ($itn['status'] ?? '') === 'confirmed';   // confirmed itineraries are locked until reopened
$stopsData  = $stopsData ?? [];
$contacts   = $contacts ?? [];
$distancePayload = $distancePayload ?? null;
$ratePerKm       = (float) ($ratePerKm ?? 0);
$returnChecked   = !empty($itn['return_to_pickup']);

$savedPickup = $itn['pickup'] ?? '';
$savedDest   = $itn['destination'] ?? '';
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
        <fieldset class="at-itn-readonly"<?= $readOnly ? ' disabled' : '' ?>>
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
            <label for="destination">Destination <small>(optional)</small></label>
            <select id="destination" name="destination">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= e($dest['name']) ?>" <?= (!$showOtherDest && $savedDest === $dest['name']) ? 'selected' : '' ?>><?= e($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= $showOtherDest ? 'selected' : '' ?>>Other (specify below)</option>
            </select>
        </div>
        <div class="form-group <?= $showOtherDest ? '' : 'hidden' ?>" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination</label>
            <input type="text" id="otherDestination" placeholder="Enter destination" autocomplete="off"
                   data-place-id="<?= $showOtherDest ? e($itn['destination_place_id'] ?? '') : '' ?>"
                   value="<?= $showOtherDest ? e($savedDest) : '' ?>">
        </div>
        <div class="at-itn-leg" id="legFinal"></div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="returnToPickup" <?= $returnChecked ? 'checked' : '' ?>>
                🔁 Return to the pickup after the destination
            </label>
        </div>
        <div class="at-itn-leg" id="legReturn"></div>
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
        </fieldset>
        <?php if ($isEdit): ?>
            <a href="<?= BASE_URL ?>/modules/Itineraries/" class="page-action-btn back">← <?= $readOnly ? 'Back' : 'Cancel' ?></a>
        <?php endif; ?>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
$(document).ready(function () {
    var isEdit      = <?= $isEdit ? 'true' : 'false' ?>;
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

    function renderDistance(d) {
        if (!d) { return; }
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
    }

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
            return_to_pickup:     $('#returnToPickup').is(':checked') ? '1' : ''
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
                    '<div class="form-group"><label>Arrival Time <small>(optional)</small></label><select class="at-itn-arrival"></select></div>' +
                    '<div class="form-group"><label>Wait (minutes) <small>(optional)</small></label><input type="number" class="at-itn-wait" min="0" max="1440" step="5"></div>' +
                '</div>' +
                '<div class="form-group"><label>Note for the client <small>(optional)</small></label><textarea class="at-itn-client-notes"></textarea></div>' +
                '<div class="form-group"><label>Internal note <small>(optional, not for the client)</small></label><textarea class="at-itn-internal-notes"></textarea></div>' +
            '</div>'
        );

        var $arrival = $row.find('.at-itn-arrival');
        $arrival.append($('<option>', { value: '', text: '— TBC —' }));
        timeOptions.forEach(function (t) {
            $arrival.append($('<option>', { value: t, text: t }));
        });

        var locEl = $row.find('.at-itn-location')[0];
        locEl.value = stop.location || '';
        if (stop.place_id) { locEl.dataset.placeId = stop.place_id; }
        attachPlaces(locEl);
        $arrival.val(stop.arrival_time || '');
        $row.find('.at-itn-wait').val(stop.wait_minutes === null ? '' : stop.wait_minutes);
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
        $row.find('.at-itn-location').focus();
    });

    $list.on('click', '.at-itn-remove', function () {
        var $row = $(this).closest('.at-itn-stop');
        var loc  = $row.find('.at-itn-location').val().trim();
        if (loc !== '' && !confirm('Remove this stop?')) { return; }
        $row.remove();
        refreshStopChrome();
        markDistanceStale();
    });

    $list.on('click', '.at-itn-up', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.prev('.at-itn-stop').before($row);
        refreshStopChrome();
        markDistanceStale();
    });

    $list.on('click', '.at-itn-down', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.next('.at-itn-stop').after($row);
        refreshStopChrome();
        markDistanceStale();
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
    renderDistance(shownDistance);

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
                    renderDistance(res.distance);
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
                    if (d) { renderDistance(d); }

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
