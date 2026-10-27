<?php
// modules/Itineraries/form.php
// Shared form for add.php and edit.php. Included by those pages, not opened directly.
// Expects: $pdo, $itn (array for edit, null for add), $stopsData (list of stops), $destinations, $costs, $timeOptions
// and, for add only, $contacts.

if (!isset($pdo, $destinations, $costs, $timeOptions)) {
    exit;
}

$isEdit     = !empty($itn['id']);
$itn        = $itn ?? [];
$stopsData  = $stopsData ?? [];
$contacts   = $contacts ?? [];

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
            <input type="text" id="otherPickup" placeholder="Enter pickup address"
                   value="<?= $showOtherPickup ? e($savedPickup) : '' ?>">
        </div>

        <div class="at-itn-stops-section">
            <h3 class="at-itn-stops-title">Stops <small>(between pickup and destination)</small></h3>
            <p class="at-itn-stops-empty" id="stopsEmpty">No stops yet. Add the first one below.</p>
            <div id="stopsList"></div>
            <button type="button" class="page-action-btn add" id="addStopBtn">➕ Add Stop</button>
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
            <input type="text" id="otherDestination" placeholder="Enter destination"
                   value="<?= $showOtherDest ? e($savedDest) : '' ?>">
        </div>

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
    var timeOptions = <?= json_encode(array_values($timeOptions)) ?>;
    var initial     = <?= json_encode($jsStops, JSON_UNESCAPED_UNICODE) ?>;
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
                    '<input type="text" class="at-itn-location" list="itnPlaces" maxlength="255" placeholder="Where is this stop?">' +
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

        $row.find('.at-itn-location').val(stop.location || '');
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
        $row.find('.at-itn-location').focus();
    });

    $list.on('click', '.at-itn-remove', function () {
        var $row = $(this).closest('.at-itn-stop');
        var loc  = $row.find('.at-itn-location').val().trim();
        if (loc !== '' && !confirm('Remove this stop?')) { return; }
        $row.remove();
        refreshStopChrome();
    });

    $list.on('click', '.at-itn-up', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.prev('.at-itn-stop').before($row);
        refreshStopChrome();
    });

    $list.on('click', '.at-itn-down', function () {
        var $row = $(this).closest('.at-itn-stop');
        $row.next('.at-itn-stop').after($row);
        refreshStopChrome();
    });

    renderStops(initial);

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

        var pickupVal = $('#pickup').val() === 'other' ? $('#otherPickup').val().trim() : $('#pickup').val();
        var destVal   = $('#destination').val() === 'other' ? $('#otherDestination').val().trim() : $('#destination').val();
        var costVal   = $('#cost').val() === 'other' ? $('#otherCost').val().trim() : $('#cost').val();

        var data = {
            trip_date:       $('#trip_date').val(),
            start_time:      $('#start_time').val(),
            pickup:          pickupVal,
            destination:     destVal,
            cost:            costVal,
            description:     $('#description').val().trim(),
            passenger_name:  $('#passengerName').val().trim(),
            passenger_phone: $('#passengerPhone').val().trim(),
            stops:           JSON.stringify(stops)
        };
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
                    $('#result').html('<div class="success-message">✅ ' + escapeHtml(res.message) + '</div>');
                    setTimeout(function () {
                        window.location.href = '<?= BASE_URL ?>/modules/Itineraries/';
                    }, 600);
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
