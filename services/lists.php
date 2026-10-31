<?php
// services/lists.php

$page_title = 'Dropdown Lists';
$page_subtitle = 'Dropdown Lists';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'Dropdown Lists'],
]);
include ROOT_DIR . '/includes/header.php';

// Fetch dropdown data
$current_destinations = fetchColumn($pdo, 'destinations', 'name', 'name ASC');
$current_costs = fetchColumn($pdo, 'costs', 'amount', 'amount ASC');
$current_durations = fetchColumn($pdo, 'durations', 'hours', 'hours ASC');
$uber_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');

$destinations_text = implode("\n", $current_destinations);

// Which presets have been checked against Google yet (needs the address-checking migration)
$destinationRows = [];
$verifyColumnsReady = true;
try {
    $destinationRows = $pdo->query("SELECT name, place_id, latitude, longitude, verified_at FROM destinations ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $verifyColumnsReady = false;
}
$unverifiedCount = count(array_filter($destinationRows, fn($r) => empty($r['verified_at'])));
?>

<!-- Dropdown Lists Form -->
<div class="form-container">
    <h2>🔧 Dropdown Lists</h2>
    <form id="listsForm">
        <div class="form-group">
            <label for="destinations">Destinations</label>
            <textarea id="destinations" name="destinations" rows="8"
                placeholder="Enter one destination per line"><?php echo htmlspecialchars($destinations_text); ?></textarea>
            <small>Used in booking pickup/destination dropdowns</small>
            <?php if (!$verifyColumnsReady): ?>
                <small>Address checking is not set up yet. Run the latest update in Services &rarr; Database Updates.</small>
            <?php elseif (!empty($destinationRows)): ?>
                <br><small><?= $unverifiedCount === 0 ? '&#9989; All destinations are checked.' : $unverifiedCount . ' of ' . count($destinationRows) . ' destinations not checked against Google yet (see below).' ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="costs">Costs (ZAR)</label>
            <textarea id="costs" name="costs" rows="8"
                placeholder="Enter one cost per line, e.g.&#10;250&#10;300.50&#10;400"><?php echo htmlspecialchars($costs_text); ?></textarea>
            <small>Enter amounts without 'R' or commas</small>
        </div>

        <div class="form-group">
            <label for="durations">Durations (hours)</label>
            <textarea id="durations" name="durations" rows="5"
                placeholder="Enter one duration per line, e.g.&#10;0.5 (30 mins)&#10;1&#10;1.5"><?php echo htmlspecialchars($durations_text); ?></textarea>
            <small>Use decimal format: 0.5 = 30 mins</small>
        </div>

        <div class="form-group">
            <label for="uber_cost_reasons">Uber Additional Cost Reasons</label>
            <textarea id="uber_cost_reasons" name="uber_cost_reasons" rows="8"
                placeholder="Enter one reason per line"><?php echo htmlspecialchars($uber_reasons_text); ?></textarea>
            <small>Reasons for additional Uber expenses</small>
        </div>

        <button type="submit" class="page-action-btn save" id="submitBtn">
            💾 Save Changes
        </button>
    </form>
    <div id="listsResult"></div>
</div>

<?php if ($verifyColumnsReady && !empty($destinationRows)): ?>
<!-- Destination location check -->
<div class="form-container">
    <h2>📍 Check Destinations</h2>
    <p><small>Pick the right place from Google's suggestions, or use Check to see whether Google can find the name as it is. A destination that is checked is used exactly as saved when distances are worked out. New destinations added above appear here after you save the list and reload.</small></p>
    <table class="bookings-table" id="destTable">
        <thead>
            <tr>
                <th>Destination</th>
                <th>Status</th>
                <th>Google location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($destinationRows as $d): $done = !empty($d['verified_at']); ?>
            <tr data-name="<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>">
                <td data-label="Destination"><?= htmlspecialchars($d['name']) ?></td>
                <td data-label="Status">
                    <span class="at-dest-status <?= $done ? 'at-dest-status-ok' : 'at-dest-status-todo' ?>"><?= $done ? '✅ Checked' : '⚠️ Not checked' ?></span>
                </td>
                <td data-label="Google location">
                    <input type="text" class="at-dest-find" autocomplete="off" value="<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>" placeholder="Search Google for this place">
                    <div class="at-addr-note"></div>
                </td>
                <td data-label="Actions">
                    <button type="button" class="action-btn edit-btn at-dest-check-btn">📍 Check</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/address-check.js"></script>
<script>
    $(document).ready(function () {
        var apiUrl = '<?= BASE_URL ?>/services/api/index.php';

        // Destination check: picking a Google suggestion saves that exact place; Check asks Google about the text as typed
        AtAddress.init('<?= htmlspecialchars(GOOGLE_MAPS_BROWSER_KEY, ENT_QUOTES) ?>');

        function markChecked($row) {
            $row.find('.at-dest-status').attr('class', 'at-dest-status at-dest-status-ok').text('✅ Checked');
        }

        $('#destTable tbody tr').each(function () {
            var $row  = $(this);
            var name  = $row.data('name');
            var $note = $row.find('.at-addr-note');
            var $find = $row.find('.at-dest-find');

            AtAddress.attachSearch($find[0], function (p) {
                AtAddress.showNote($note, 'info', 'Saving…');
                $.ajax({
                    type: 'POST',
                    url: apiUrl + '?action=save_destination_location',
                    data: { name: name, place_id: p.placeId, lat: p.lat, lng: p.lng },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            markChecked($row);
                            $find.val(p.address);
                            AtAddress.showNote($note, 'ok', '✅ Saved: ' + p.address);
                        } else {
                            AtAddress.showNote($note, 'fail', res.message || 'Could not save.');
                        }
                    },
                    error: function () { AtAddress.showNote($note, 'fail', 'Could not save. Please try again.'); }
                });
            });

            $row.find('.at-dest-check-btn').on('click', function () {
                var $btn = $(this);
                var text = $.trim($find.val()) || name;
                $btn.prop('disabled', true).text('Checking…');
                AtAddress.showNote($note, 'info', 'Asking Google…');
                AtAddress.serverCheck(apiUrl + '?action=check_destination', { text: text }, function (res) {
                    if (res.level !== 'ok') {
                        $btn.prop('disabled', false).text('📍 Check');
                        AtAddress.showNote($note, res.level === 'fail' ? 'fail' : 'warn', '⚠️ ' + (res.message || 'The check could not be completed.'));
                        return;
                    }
                    $.ajax({
                        type: 'POST',
                        url: apiUrl + '?action=save_destination_location',
                        data: { name: name },
                        dataType: 'json',
                        success: function (saved) {
                            $btn.prop('disabled', false).text('📍 Check');
                            if (saved.success) {
                                markChecked($row);
                                AtAddress.showNote($note, 'ok', '✅ ' + res.message);
                            } else {
                                AtAddress.showNote($note, 'fail', saved.message || 'Could not save.');
                            }
                        },
                        error: function () {
                            $btn.prop('disabled', false).text('📍 Check');
                            AtAddress.showNote($note, 'fail', 'Could not save. Please try again.');
                        }
                    });
                });
            });
        });

        // Handle dropdown lists form
        $('#listsForm').on('submit', function (e) {
            e.preventDefault();
            var submitBtn = $('#submitBtn');
            var result = $('#listsResult');

            submitBtn.prop('disabled', true).text('Saving...');
            result.html('');

            $.ajax({
                type: 'POST',
                url: '<?= BASE_URL ?>/services/api/index.php?action=update_lists',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    submitBtn.prop('disabled', false).text('💾 Save Changes');
                    if (response.success) {
                        result.html('<div class="success-message">' + response.message + '</div>');
                    } else {
                        result.html('<div class="error-message">' + response.message + '</div>');
                    }

                    setTimeout(function () {
                        result.fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function (xhr, status, error) {
                    submitBtn.prop('disabled', false).text('💾 Save Changes');
                    result.html('<div class="error-message">❌ Failed to save. Please try again.</div>');
                    console.error('Error:', error, xhr.responseText);
                    logJsError('ERROR', 'Lists: failed to save changes', { response: xhr.responseText });
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
