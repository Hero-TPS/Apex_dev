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
$unverifiedDestinations = [];
$verifyColumnsReady = true;
try {
    $unverifiedDestinations = $pdo->query("SELECT name FROM destinations WHERE place_id IS NULL AND (latitude IS NULL OR longitude IS NULL) ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $ex) {
    $verifyColumnsReady = false;
}
$costs_text = implode("\n", $current_costs);
$durations_text = implode("\n", $current_durations);
$uber_reasons_text = implode("\n", $uber_reasons);
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
            <?php elseif (empty($current_destinations)): ?>
            <?php elseif (empty($unverifiedDestinations)): ?>
                <br><small>&#9989; All destinations have a checked location.</small>
            <?php else: ?>
                <details>
                    <summary><small><?= count($unverifiedDestinations) ?> of <?= count($current_destinations) ?> destinations not checked against Google yet</small></summary>
                    <small><?= htmlspecialchars(implode(' · ', $unverifiedDestinations)) ?></small>
                </details>
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

<script>
    $(document).ready(function () {
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
