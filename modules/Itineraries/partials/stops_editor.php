<?php
// modules/Itineraries/partials/stops_editor.php
// Stops editor for a prebooking's itinerary. Included by modules/Prebookings/edit.php,
// OUTSIDE the prebooking <form> so its buttons never submit that form.
// Expects: $pdo, $prebookingId (int), $destinations (rows of the destinations table),
// plus everything edit.php already loads (config, helpers, jQuery from the header).
//
// Stops are edited in the browser and only written when "Save stops" is pressed, so
// nothing is lost by a mis-tap on add / move / remove until then.

require_once ROOT_DIR . '/modules/Itineraries/helpers.php';

$itinerary    = getItineraryForPrebooking($pdo, (int) $prebookingId);
$initialStops = $itinerary ? getItineraryStops($pdo, (int) $itinerary['id']) : [];
$itinStatus   = $itinerary['status'] ?? 'planning';
$jsonFlags    = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>

<div class="at-itin" id="atItin">
    <div class="at-itin-header">
        <h3 class="at-itin-title">🗺️ Itinerary stops</h3>
        <span class="at-itin-badge at-itin-badge--<?= e($itinStatus) ?>">
            <?= $itinStatus === 'confirmed' ? 'Confirmed' : 'Planning' ?>
        </span>
    </div>
    <p class="at-itin-intro client-subline">
        Stops between the pickup and the destination above. Arrival times are approximate and can change.
        Changes are only saved when you press <strong>Save stops</strong>.
    </p>

    <datalist id="at-itin-destinations">
        <?php foreach ($destinations as $dest): ?>
            <option value="<?= e($dest['name']) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <div id="atItinStops" class="at-itin-stops"></div>
    <p id="atItinEmpty" class="at-itin-empty client-subline">No stops yet — this is a single point-to-point trip.</p>

    <div class="at-itin-footer">
        <button type="button" class="page-action-btn edit" id="atItinAdd">➕ Add stop</button>
        <button type="button" class="page-action-btn save" id="atItinSave">💾 Save stops</button>
        <span id="atItinDirty" class="at-itin-dirty hidden">Unsaved changes</span>
    </div>
    <div id="atItinResult"></div>
</div>

<script>
    $(document).ready(function () {
        var prebookingId = <?= (int) $prebookingId ?>;
        var saveUrl = '<?= BASE_URL ?>/modules/Itineraries/api/index.php?action=save_prebooking_stops';
        var initialStops = <?= json_encode($initialStops, $jsonFlags) ?>;
        var dirty = false;

        var $list = $('#atItinStops');
        var $empty = $('#atItinEmpty');
        var $dirtyFlag = $('#atItinDirty');

        // Static markup only; stop values are set with .val() so nothing is injected as HTML.
        var rowHtml =
            '<div class="at-itin-stop">' +
            '  <div class="at-itin-stop-head">' +
            '    <span class="at-itin-stop-num"></span>' +
            '    <div class="at-itin-stop-actions">' +
            '      <button type="button" class="at-itin-icon-btn at-itin-up" title="Move up" aria-label="Move stop up">↑</button>' +
            '      <button type="button" class="at-itin-icon-btn at-itin-down" title="Move down" aria-label="Move stop down">↓</button>' +
            '      <button type="button" class="at-itin-icon-btn at-itin-remove" title="Remove stop" aria-label="Remove stop">✕</button>' +
            '    </div>' +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label>Location</label>' +
            '    <input type="text" class="at-itin-location" list="at-itin-destinations" maxlength="255" placeholder="Pick from the list or type a place">' +
            '  </div>' +
            '  <div class="form-row">' +
            '    <div class="form-group">' +
            '      <label>Planned arrival <small>(approx.)</small></label>' +
            '      <input type="time" class="at-itin-time" step="900">' +
            '    </div>' +
            '    <div class="form-group">' +
            '      <label>Wait here <small>(minutes)</small></label>' +
            '      <input type="number" class="at-itin-wait" min="0" max="1440" step="5" placeholder="0">' +
            '    </div>' +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label>Notes about this stop <small>(for the client)</small></label>' +
            '    <textarea class="at-itin-client-notes" maxlength="<?= ITINERARY_NOTES_MAX_LENGTH ?>"></textarea>' +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label>Internal notes <small>(driver / you)</small></label>' +
            '    <textarea class="at-itin-internal-notes" maxlength="<?= ITINERARY_NOTES_MAX_LENGTH ?>"></textarea>' +
            '  </div>' +
            '</div>';

        function setDirty(value) {
            dirty = value;
            $dirtyFlag.toggleClass('hidden', !value);
        }

        // Re-number rows, enable / disable the arrows at the ends, toggle the empty note.
        function refresh() {
            var $rows = $list.children('.at-itin-stop');
            $rows.each(function (i) {
                $(this).find('.at-itin-stop-num').text('Stop ' + (i + 1));
                $(this).find('.at-itin-up').prop('disabled', i === 0);
                $(this).find('.at-itin-down').prop('disabled', i === $rows.length - 1);
            });
            $empty.toggleClass('hidden', $rows.length > 0);
        }

        function addRow(stop) {
            var $row = $(rowHtml);
            if (stop) {
                $row.find('.at-itin-location').val(stop.location || '');
                $row.find('.at-itin-time').val(stop.arrival_time || '');
                $row.find('.at-itin-wait').val(stop.wait_minutes === '' || stop.wait_minutes === null ? '' : stop.wait_minutes);
                $row.find('.at-itin-client-notes').val(stop.client_notes || '');
                $row.find('.at-itin-internal-notes').val(stop.internal_notes || '');
            }
            $list.append($row);
            return $row;
        }

        function renderStops(stops) {
            $list.empty();
            $.each(stops, function (_, stop) { addRow(stop); });
            refresh();
        }

        function collectStops() {
            var stops = [];
            $list.children('.at-itin-stop').each(function () {
                var $row = $(this);
                stops.push({
                    location: $.trim($row.find('.at-itin-location').val()),
                    arrival_time: $row.find('.at-itin-time').val(),
                    wait_minutes: $.trim($row.find('.at-itin-wait').val()),
                    client_notes: $.trim($row.find('.at-itin-client-notes').val()),
                    internal_notes: $.trim($row.find('.at-itin-internal-notes').val())
                });
            });
            return stops;
        }

        $('#atItinAdd').on('click', function () {
            var $row = addRow(null);
            refresh();
            setDirty(true);
            $row.find('.at-itin-location').trigger('focus');
        });

        $list.on('click', '.at-itin-up', function () {
            var $row = $(this).closest('.at-itin-stop');
            $row.prev('.at-itin-stop').before($row);
            refresh();
            setDirty(true);
        });

        $list.on('click', '.at-itin-down', function () {
            var $row = $(this).closest('.at-itin-stop');
            $row.next('.at-itin-stop').after($row);
            refresh();
            setDirty(true);
        });

        $list.on('click', '.at-itin-remove', function () {
            $(this).closest('.at-itin-stop').remove();
            refresh();
            setDirty(true);
        });

        $list.on('input change', 'input, textarea', function () {
            setDirty(true);
        });

        $('#atItinSave').on('click', function () {
            var $btn = $(this);
            var $result = $('#atItinResult');
            $btn.prop('disabled', true);
            $result.html('');

            $.ajax({
                type: 'POST',
                url: saveUrl,
                data: {
                    prebooking_id: prebookingId,
                    stops: JSON.stringify(collectStops())
                },
                dataType: 'json',
                success: function (res) {
                    $btn.prop('disabled', false);
                    if (res.success) {
                        renderStops(res.stops || []);
                        setDirty(false);
                        $result.html('<div class="success-message">✅ ' + $('<div>').text(res.message).html() + '</div>');
                    } else {
                        $result.html('<div class="error-message">❌ ' + $('<div>').text(res.message || 'Failed to save stops.').html() + '</div>');
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false);
                    $result.html('<div class="error-message">❌ Could not save stops. Please try again.</div>');
                }
            });
        });

        // Warn before leaving with unsaved stop changes.
        $(window).on('beforeunload', function () {
            if (dirty) {
                return 'You have unsaved itinerary changes.';
            }
        });

        renderStops(initialStops);
    });
</script>
