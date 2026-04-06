<?php
// modules/Prebookings/add.php
$page_title    = 'Add Prebooking';
$page_subtitle = 'Add Prebooking';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Prebookings', 'url' => BASE_URL . '/modules/Prebookings/'],
    ['label' => 'Add Prebooking'],
]);
include ROOT_DIR . '/includes/header.php';

$contacts     = fetchData($pdo, 'contacts', 'name ASC');
$destinations = fetchData($pdo, 'destinations', 'name ASC');
$costs        = fetchData($pdo, 'costs', 'amount ASC');
$timeOptions  = generateTimeOptions();
?>

<div class="form-container">
    <p class="form-intro">Use this form to record a tentative booking when the client knows their date but not all details yet. Time, destination, and cost are optional.</p>

    <form id="prebookingForm">
        <div class="form-group">
            <label for="contactSearch">Client Name <span class="required">*</span></label>
            <input type="text" id="contactSearch" placeholder="Search client..." required>
            <div id="contactSuggestions" class="suggestions-box"></div>
            <input type="hidden" id="contact_id" name="contact_id" required>
        </div>

        <div class="form-group">
            <label for="trip_date">Date <span class="required">*</span></label>
            <input type="date" id="trip_date" name="trip_date" required>
        </div>

        <div class="form-group">
            <label for="start_time">Time <small>(optional)</small></label>
            <select id="start_time" name="start_time">
                <option value="">— Not known yet —</option>
                <?php foreach ($timeOptions as $time): ?>
                    <option value="<?= htmlspecialchars($time) ?>"><?= htmlspecialchars($time) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="original_pickup">Pickup Location <small>(optional)</small></label>
            <select id="original_pickup" name="original_pickup">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= htmlspecialchars($dest['name']) ?>"><?= htmlspecialchars($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherPickupGroup">
            <label for="otherPickup">Specify Other Pickup</label>
            <input type="text" id="otherPickup" name="other_original_pickup" placeholder="Enter pickup address">
        </div>

        <div class="form-group">
            <label for="original_destination">Destination <small>(optional)</small></label>
            <select id="original_destination" name="original_destination">
                <option value="">— Not known yet —</option>
                <?php foreach ($destinations as $dest): ?>
                    <option value="<?= htmlspecialchars($dest['name']) ?>"><?= htmlspecialchars($dest['name']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherDestinationGroup">
            <label for="otherDestination">Specify Other Destination</label>
            <input type="text" id="otherDestination" name="other_original_destination" placeholder="Enter destination">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="swapLocations" name="swap_locations">
                🔄 Swap pickup and destination locations
            </label>
        </div>

        <div class="form-group">
            <label for="cost">Cost <small>(optional)</small></label>
            <select id="cost" name="cost">
                <option value="">— Not known yet —</option>
                <?php foreach ($costs as $c): ?>
                    <option value="<?= htmlspecialchars($c['amount']) ?>">R<?= htmlspecialchars(number_format($c['amount'], 2)) ?></option>
                <?php endforeach; ?>
                <option value="other">Other (specify below)</option>
            </select>
        </div>

        <div class="form-group hidden" id="otherCostGroup">
            <label for="otherCost">Specify Other Cost</label>
            <input type="number" id="otherCost" name="other_cost" step="0.01" min="0" placeholder="0.00">
        </div>

        <div class="form-group">
            <label for="description">Notes / Description <small>(optional)</small></label>
            <textarea id="description" name="description" placeholder="Any relevant info about this booking..."></textarea>
        </div>

        <button type="submit" class="btn" id="submitBtn">📋 Save Prebooking</button>
    </form>

    <div id="loading" class="loading"></div>
    <div id="result"></div>
</div>

<script>
    $(document).ready(function () {
        document.getElementById('trip_date').min = new Date().toISOString().split('T')[0];

        const contactSearch   = $('#contactSearch');
        const suggestionsBox  = $('#contactSuggestions');
        const contactIdInput  = $('#contact_id');
        let clients = <?= json_encode($contacts ?: []) ?>;
        let selected = -1;

        contactSearch.on('input focus', function () {
            const query = $(this).val().trim().toLowerCase();
            if (query.length === 0) { suggestionsBox.hide(); return; }
            const filtered = clients.filter(c =>
                (c.name  && c.name.toLowerCase().includes(query)) ||
                (c.phone && c.phone.toLowerCase().includes(query))
            );
            suggestionsBox.empty();
            if (filtered.length > 0) {
                selected = -1;
                filtered.forEach(function (client) {
                    const item = $('<div class="suggestion-item">' +
                        escapeHtml(client.name) +
                        '<br><small>' + escapeHtml(client.phone || '') + '</small></div>');
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
            const items = suggestionsBox.find('.suggestion-item');
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
        }

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#contactSearch, #contactSuggestions').length) {
                suggestionsBox.hide();
            }
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') { suggestionsBox.hide(); }
        });

        // Other pickup toggle
        $('#original_pickup').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherPickupGroup').removeClass('hidden');
            } else {
                $('#otherPickupGroup').addClass('hidden');
                $('#otherPickup').val('');
            }
        });

        // Other destination toggle
        $('#original_destination').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherDestinationGroup').removeClass('hidden');
            } else {
                $('#otherDestinationGroup').addClass('hidden');
                $('#otherDestination').val('');
            }
        });

        // Other cost toggle
        $('#cost').on('change', function () {
            if ($(this).val() === 'other') {
                $('#otherCostGroup').removeClass('hidden');
            } else {
                $('#otherCostGroup').addClass('hidden');
                $('#otherCost').val('');
            }
        });

        // Form submit
        $('#prebookingForm').on('submit', function (e) {
            e.preventDefault();

            if (!contactIdInput.val()) {
                $('#result').html('<div class="error-message">⚠️ Please select a client from the list.</div>');
                return;
            }

            var submitBtn = $('#submitBtn');
            var loading   = $('#loading');
            submitBtn.hide();
            loading.show();
            $('#result').html('');

            // Resolve destination: if "other" use the typed value
            var destSelect = $('#original_destination');
            var destVal    = destSelect.val() === 'other' ? $('#otherDestination').val().trim() : destSelect.val();

            // Resolve pickup: if "other" use the typed value
            var pickupSelect = $('#original_pickup');
            var pickupVal    = pickupSelect.val() === 'other' ? $('#otherPickup').val().trim() : pickupSelect.val();

            // Resolve cost: if "other" use the typed value
            var costSelect = $('#cost');
            var costVal    = costSelect.val() === 'other' ? $('#otherCost').val().trim() : costSelect.val();

            var data = {
                contact_id:           contactIdInput.val(),
                trip_date:            $('#trip_date').val(),
                start_time:           $('#start_time').val(),
                original_pickup:      pickupVal,
                original_destination: destVal,
                swap_locations:       $('#swapLocations').is(':checked') ? '1' : '',
                cost:                 costVal,
                description:          $('#description').val().trim(),
            };

            $.ajax({
                type:     'POST',
                url:      '<?= BASE_URL ?>/modules/Prebookings/api/index.php?action=add',
                data:     data,
                dataType: 'json',
                success: function (res) {
                    loading.hide();
                    submitBtn.show();
                    if (res.success) {
                        $('#result').html('<div class="success-message">' + res.message + '</div>');
                        setTimeout(function () {
                            window.location.href = '<?= BASE_URL ?>/modules/Prebookings/';
                        }, 600);
                    } else {
                        $('#result').html('<div class="error-message">' + (res.message || 'Failed to save.') + '</div>');
                    }
                },
                error: function (xhr) {
                    loading.hide();
                    submitBtn.show();
                    $('#result').html('<div class="error-message">❌ Error: ' + (xhr.responseText || 'Unknown error') + '</div>');
                }
            });
        });

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
