<?php
// services/address_check.php
// One-off clean-up and check of saved destinations and client addresses against Google.
// Everything here is a report first: nothing changes until you press Merge or "Save found as checked".

$page_title = 'Address Check';
$page_subtitle = 'Address Check';
$show_breadcrumb = true;

require_once __DIR__ . '/../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Services'],
    ['label' => 'Address Check'],
]);
include ROOT_DIR . '/includes/header.php';

// Needs the address-checking columns from migration 20261030_1600
$ready = addressColumnsReady($pdo);
if ($ready) {
    try {
        $pdo->query("SELECT id, name, place_id, verified_at FROM destinations LIMIT 0");
    } catch (PDOException $ex) {
        $ready = false;
    }
}
if (!$ready) {
    echo '<div class="error-message">Address checking is not set up on this database yet. Run the latest updates in Services &rarr; Database Updates, then come back.</div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

// ---- Duplicate destinations ----
$destinations = $pdo->query("SELECT id, name, verified_at FROM destinations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
foreach ($destinations as $d) {
    $groups[normalizeDestinationName($d['name'])][] = $d;
}
$duplicateGroups = array_values(array_filter($groups, fn($g) => count($g) > 1));

// How often each saved name is used (bookings, prebookings, itineraries and their stops)
$usage = [];
$usageQueries = [
    "SELECT original_pickup AS n, COUNT(*) AS c FROM bookings GROUP BY original_pickup",
    "SELECT original_destination AS n, COUNT(*) AS c FROM bookings GROUP BY original_destination",
    "SELECT original_pickup AS n, COUNT(*) AS c FROM prebookings GROUP BY original_pickup",
    "SELECT original_destination AS n, COUNT(*) AS c FROM prebookings GROUP BY original_destination",
    "SELECT pickup AS n, COUNT(*) AS c FROM itineraries GROUP BY pickup",
    "SELECT destination AS n, COUNT(*) AS c FROM itineraries GROUP BY destination",
    "SELECT location AS n, COUNT(*) AS c FROM itinerary_stops GROUP BY location",
];
foreach ($usageQueries as $sql) {
    try {
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $usage[$r['n']] = ($usage[$r['n']] ?? 0) + (int) $r['c'];
        }
    } catch (PDOException $ex) {
        // a table that does not exist on this database simply has no usage
    }
}

// ---- Not yet checked ----
$uncheckedDestinations = array_values(array_filter($destinations, fn($d) => empty($d['verified_at'])));

$villageMatch = trim((string) getSystemVariable($pdo, 'village_match_text'));
$clientRows = $pdo->query("
    SELECT id, name, address, pickup_lat, pickup_lng, address_verified_at
    FROM contacts
    WHERE is_archived = 0
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$uncheckedClients = [];
$skipPin = $skipVillage = $skipNoAddress = $alreadyChecked = 0;
foreach ($clientRows as $c) {
    $address = trim((string) $c['address']);
    if ($address === '') { $skipNoAddress++; continue; }
    if (!empty($c['pickup_lat']) && !empty($c['pickup_lng'])) { $skipPin++; continue; }
    if ($villageMatch !== '' && stripos($address, $villageMatch) !== false) { $skipVillage++; continue; }
    if (!empty($c['address_verified_at'])) { $alreadyChecked++; continue; }
    $uncheckedClients[] = $c;
}
?>

<div class="form-container">
    <h2>📍 Address Check</h2>
    <p><small>This page only reports. Nothing is changed until you press Merge or Save. Distances are not involved: Google is only asked whether it can find each address.</small></p>
</div>

<!-- 1. Duplicate destinations -->
<div class="form-container">
    <h2>1. Duplicate destinations</h2>
    <?php if (empty($duplicateGroups)): ?>
        <p>✅ No duplicates found. (Names are compared ignoring capitals, spaces and punctuation.)</p>
    <?php else: ?>
        <p><small>Each group below looks like the same place saved more than once. Pick the one to keep and press Merge. The others are removed from the list, and anything not yet done (future bookings, prebookings, itineraries) that uses them is changed to the kept name. Bookings from past days are left as they were.</small></p>
        <div id="mergeResult"></div>
        <?php foreach ($duplicateGroups as $gi => $group): ?>
            <div class="at-addrchk-group" data-group="<?= (int) $gi ?>">
                <?php foreach ($group as $idx => $d): ?>
                    <label class="at-addrchk-option">
                        <input type="radio" name="keep<?= (int) $gi ?>" value="<?= e($d['name']) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                        <strong><?= e($d['name']) ?></strong>
                        <small>(used <?= (int) ($usage[$d['name']] ?? 0) ?> time<?= ($usage[$d['name']] ?? 0) === 1 ? '' : 's' ?><?= !empty($d['verified_at']) ? ', checked' : '' ?>)</small>
                    </label>
                <?php endforeach; ?>
                <button type="button" class="page-action-btn edit at-addrchk-merge">🔀 Merge into the selected one</button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- 2 and 3. Lookups -->
<div class="form-container">
    <h2>2. Check against Google</h2>
    <p><small>
        <?= count($uncheckedDestinations) ?> saved destination<?= count($uncheckedDestinations) === 1 ? '' : 's' ?> and
        <?= count($uncheckedClients) ?> client address<?= count($uncheckedClients) === 1 ? '' : 'es' ?> still to check.
        Left out on purpose:
        <?= $skipPin ?> client<?= $skipPin === 1 ? '' : 's' ?> with a GPS pin,
        <?= $skipVillage ?> with a <?= e($villageMatch) ?> address (measured from the estate gate),
        <?= $skipNoAddress ?> with no address<?= $alreadyChecked > 0 ? ', ' . $alreadyChecked . ' already checked' : '' ?>.
    </small></p>

    <div class="at-addrchk-bar">
        <button type="button" class="page-action-btn save" id="runBtn">▶ Check all</button>
        <button type="button" class="page-action-btn toggle" id="stopBtn" disabled>⏹ Stop</button>
        <button type="button" class="page-action-btn edit" id="saveFoundBtn" disabled>💾 Save found as checked</button>
        <span id="progress" class="at-addr-note"></span>
    </div>
    <div id="saveResult"></div>

    <?php if (!empty($uncheckedDestinations)): ?>
    <h3>Saved destinations</h3>
    <table class="bookings-table at-addrchk-table">
        <thead><tr><th>Destination</th><th>Result</th><th>Fix</th></tr></thead>
        <tbody>
        <?php foreach ($uncheckedDestinations as $d): ?>
            <tr class="at-addrchk-row" data-type="destination" data-id="<?= (int) $d['id'] ?>">
                <td data-label="Destination"><?= e($d['name']) ?></td>
                <td data-label="Result" class="at-addrchk-result"><span class="at-addr-note">Not checked yet</span></td>
                <td data-label="Fix"><a href="<?= BASE_URL ?>/services/lists.php" target="_blank">Dropdown Lists</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($uncheckedClients)): ?>
    <h3>Client addresses</h3>
    <table class="bookings-table at-addrchk-table">
        <thead><tr><th>Client</th><th>Address</th><th>Result</th><th>Fix</th></tr></thead>
        <tbody>
        <?php foreach ($uncheckedClients as $c): ?>
            <tr class="at-addrchk-row" data-type="client" data-id="<?= (int) $c['id'] ?>">
                <td data-label="Client"><?= e($c['name']) ?></td>
                <td data-label="Address"><?= e($c['address']) ?></td>
                <td data-label="Result" class="at-addrchk-result"><span class="at-addr-note">Not checked yet</span></td>
                <td data-label="Fix"><a href="<?= BASE_URL ?>/modules/Clients/edit.php?id=<?= (int) $c['id'] ?>" target="_blank">Edit client</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (empty($uncheckedDestinations) && empty($uncheckedClients)): ?>
        <p>✅ Everything is checked.</p>
    <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/assets/js/address-check.js"></script>
<script>
$(document).ready(function () {
    var api = '<?= BASE_URL ?>/services/api/index.php';

    function esc(s) { return $('<div>').text(s === null || s === undefined ? '' : String(s)).html(); }

    // ---- Merge duplicates ----
    $('.at-addrchk-merge').on('click', function () {
        var $g    = $(this).closest('.at-addrchk-group');
        var keep  = $g.find('input[type=radio]:checked').val();
        var remove = [];
        $g.find('input[type=radio]').each(function () { if (this.value !== keep) { remove.push(this.value); } });
        if (!confirm('Keep "' + keep + '" and remove ' + remove.length + ' duplicate(s)?\n\nFuture bookings, prebookings and itineraries that use a removed name will be changed to the kept one.')) { return; }
        var $btn = $(this).prop('disabled', true).text('Merging…');
        $.ajax({
            type: 'POST', url: api + '?action=merge_destinations', dataType: 'json',
            data: { keep: keep, remove: remove },
            success: function (res) {
                if (res.success) {
                    $g.fadeOut(300);
                    $('#mergeResult').html('<div class="success-message">' + esc(res.message) + '</div>');
                } else {
                    $btn.prop('disabled', false).text('🔀 Merge into the selected one');
                    $('#mergeResult').html('<div class="error-message">' + esc(res.message) + '</div>');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('🔀 Merge into the selected one');
                $('#mergeResult').html('<div class="error-message">❌ Request failed. Please try again.</div>');
            }
        });
    });

    // ---- Check one row at a time, so a long list never hits the host's time limit ----
    var running = false;
    var $rows = $('.at-addrchk-row');

    function updateProgress() {
        var done = $rows.filter('[data-level]').length;
        $('#progress').text($rows.length ? done + ' of ' + $rows.length + ' checked' : '');
        $('#saveFoundBtn').prop('disabled', $rows.filter('[data-level="ok"]').length === 0);
    }

    function checkNext() {
        if (!running) { return; }
        var $row = $rows.filter(':not([data-level])').first();
        if (!$row.length) { finishRun(); return; }
        var $cell = $row.find('.at-addrchk-result');
        $cell.html('<span class="at-addr-note at-addr-info">Asking Google…</span>');
        AtAddress.serverCheck(api + '?action=check_item', { type: $row.data('type'), id: $row.data('id') }, function (res) {
            var level = res.level || 'warn';
            if (res.success === false) { level = 'warn'; }
            // A "warn" means Google did not answer: leave the row open so it is tried again on the next run
            if (level === 'warn') {
                $cell.html('<span class="at-addr-note at-addr-warn">⚠️ ' + esc(res.message || 'No answer, try again.') + '</span>');
                $row.attr('data-level', 'warn');
            } else {
                $row.attr('data-level', level);
                $cell.html('<span class="at-addr-note at-addr-' + (level === 'ok' ? 'ok' : 'fail') + '">' + (level === 'ok' ? '✅ ' : '❌ ') + esc(res.message) + '</span>');
            }
            updateProgress();
            setTimeout(checkNext, 150);
        });
    }

    function finishRun() {
        running = false;
        $('#runBtn').prop('disabled', false).text('▶ Check all');
        $('#stopBtn').prop('disabled', true);
        updateProgress();
    }

    $('#runBtn').on('click', function () {
        // Rows that got no answer last time are tried again
        $rows.filter('[data-level="warn"]').removeAttr('data-level');
        running = true;
        $(this).prop('disabled', true).text('Checking…');
        $('#stopBtn').prop('disabled', false);
        checkNext();
    });
    $('#stopBtn').on('click', function () { running = false; finishRun(); });

    // ---- Save everything Google found ----
    $('#saveFoundBtn').on('click', function () {
        var found = { client: [], destination: [] };
        $rows.filter('[data-level="ok"]').each(function () { found[$(this).data('type')].push($(this).data('id')); });
        var $btn = $(this).prop('disabled', true).text('Saving…');
        var jobs = [];
        ['client', 'destination'].forEach(function (type) {
            if (found[type].length) {
                jobs.push($.ajax({ type: 'POST', url: api + '?action=mark_checked', dataType: 'json', data: { type: type, ids: found[type] } }));
            }
        });
        $.when.apply($, jobs).done(function () {
            $rows.filter('[data-level="ok"]').fadeOut(300, function () { $(this).remove(); $rows = $('.at-addrchk-row'); updateProgress(); });
            $('#saveResult').html('<div class="success-message">✅ Saved. Anything Google could not find stays listed so you can fix it.</div>');
            $btn.text('💾 Save found as checked');
        }).fail(function () {
            $btn.prop('disabled', false).text('💾 Save found as checked');
            $('#saveResult').html('<div class="error-message">❌ Could not save. Please try again.</div>');
        });
    });

    updateProgress();
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
