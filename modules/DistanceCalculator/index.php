<?php
/**
 * modules/DistanceCalculator/index.php
 * Trip Distance Calculator — multi-stop route planning
 * @version 1.1.0 — CSS consolidated into assets/css/styles.css (distcalc.css removed)
 */

$page_title = 'Trip Distance Calculator';
$page_subtitle = 'Calculate distance and time for multi-stop routes';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';

$breadcrumb = buildBreadcrumb([['label' => 'Trip Distance Calculator']]);
include ROOT_DIR . '/includes/header.php';

$legs = [];
$totalDistanceM = 0;
$totalDurationS = 0;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate stops
    $start = trim($_POST['start_address'] ?? '');
    $stops = array_filter(array_map('trim', $_POST['stops'] ?? []), fn($s) => !empty($s));
    $final = trim($_POST['final_destination'] ?? '');

    // Validate we have at least start and final destination
    if (empty($start) || empty($final)) {
        $error = 'Start address and final destination are required.';
    } elseif (GOOGLE_API_KEY === 'YOUR_GOOGLE_API_KEY_HERE') {
        $error = 'Google API key not configured. Please check config.php.';
    } else {
        // Build full route: start → stops[] → final
        $allStops = [$start];
        $allStops = array_merge($allStops, $stops);
        $allStops[] = $final;

        // Calculate each leg
        for ($i = 0; $i < count($allStops) - 1; $i++) {
            $origin = $allStops[$i];
            $destination = $allStops[$i + 1];

            $url = GOOGLE_DISTANCE_MATRIX_URL . '?' . http_build_query([
                'origins'      => $origin,
                'destinations' => $destination,
                'units'        => 'metric',
                'key'          => GOOGLE_API_KEY,
            ]);

            $response = @file_get_contents($url);
            $data = $response ? json_decode($response, true) : null;
            $element = $data['rows'][0]['elements'][0] ?? null;

            if (!$element || $element['status'] !== 'OK') {
                $legs[] = [
                    'from'    => $origin,
                    'to'      => $destination,
                    'status'  => 'error',
                    'message' => $element['status'] ?? 'UNKNOWN_ERROR',
                ];
            } else {
                $legs[] = [
                    'from'         => $origin,
                    'to'           => $destination,
                    'distance_m'   => $element['distance']['value'],
                    'distance_txt' => $element['distance']['text'],
                    'duration_s'   => $element['duration']['value'],
                    'duration_txt' => $element['duration']['text'],
                    'status'       => 'ok',
                ];

                $totalDistanceM += $element['distance']['value'];
                $totalDurationS += $element['duration']['value'];
            }
        }
    }
}
?>

<div class="at-distcalc-wrapper">
    <h2>🗺️ Trip Distance Calculator</h2>
    <p class="at-distcalc-description">
        Enter a start address, any number of stops (in order), and a final destination. 
        We'll calculate the distance and time for each leg.
    </p>

    <form method="post">
        <!-- Start Address -->
        <div class="form-group">
            <label for="start_address">Start Address <span class="required">*</span></label>
            <input type="text" id="start_address" name="start_address" 
                   placeholder="e.g., 123 Main Street, Cape Town" 
                   value="<?= htmlspecialchars($_POST['start_address'] ?? '') ?>" 
                   required>
        </div>

        <!-- Dynamic Stops -->
        <div class="form-group">
            <label>Stops (Optional)</label>
            <div id="at-distcalc-stops-container" class="at-distcalc-stops-container">
                <!-- Stops will be inserted here via JavaScript -->
            </div>
            <button type="button" id="at-distcalc-add-stop-btn" class="at-distcalc-add-btn">+ Add Stop</button>
        </div>

        <!-- Final Destination -->
        <div class="form-group">
            <label for="final_destination">Final Destination <span class="required">*</span></label>
            <input type="text" id="final_destination" name="final_destination" 
                   placeholder="e.g., 456 Beach Road, Gordon's Bay" 
                   value="<?= htmlspecialchars($_POST['final_destination'] ?? '') ?>" 
                   required>
        </div>

        <button type="submit" class="btn">🔍 Calculate Route</button>
    </form>

    <?php if ($error): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($legs)): ?>
        <div class="at-distcalc-results">
            <h3>📍 Route Breakdown</h3>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>From</th>
                        <th>To</th>
                        <th>Distance</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($legs as $leg): ?>
                        <tr class="<?= $leg['status'] === 'error' ? 'at-distcalc-leg-error' : 'at-distcalc-leg-ok' ?>">
                            <td><?= htmlspecialchars($leg['from']) ?></td>
                            <td><?= htmlspecialchars($leg['to']) ?></td>
                            <td>
                                <?php if ($leg['status'] === 'ok'): ?>
                                    <?= htmlspecialchars($leg['distance_txt']) ?>
                                <?php else: ?>
                                    <span class="at-distcalc-error-badge">Error: <?= htmlspecialchars($leg['message']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($leg['status'] === 'ok'): ?>
                                    <?= htmlspecialchars($leg['duration_txt']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

<?php if ($totalDistanceM > 0): ?>
    <div class="at-distcalc-totals">
        <div class="at-distcalc-total-item">
            <strong>Total Distance:</strong>
            <span><?= number_format($totalDistanceM / 1000, 1) ?> km</span>
        </div>
        <div class="at-distcalc-total-item">
            <strong>Total Driving Time:</strong>
            <span>~<?= round($totalDurationS / 60) ?> minutes</span>
        </div>
        <?php
            $ratePerKm = (float) getSystemVariable($pdo, 'rate_per_km');
            if ($ratePerKm > 0) {
                $estimatedCost = ($totalDistanceM / 1000) * $ratePerKm;
        ?>
        <div class="at-distcalc-total-item">
            <strong>Estimated Cost (@ R<?= number_format($ratePerKm, 2) ?>/km):</strong>
            <span>R<?= number_format($estimatedCost, 2) ?></span>
        </div>
        <?php } ?>
    </div>
<?php endif; ?>
            
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function () {
    // Load previously entered stops from form submission
    const previousStops = <?= json_encode(array_filter(array_map('trim', $_POST['stops'] ?? []), fn($s) => !empty($s))) ?>;

    const container = $('#at-distcalc-stops-container');

    // Render existing stops
    function renderStops() {
        const count = container.find('.at-distcalc-stop-row').length;
        if (count === 0 && previousStops.length === 0) {
            container.html(''); // Empty by default
        } else {
            previousStops.forEach((stop, index) => {
                if (container.find('.at-distcalc-stop-row').length <= index) {
                    addStopRow(stop);
                }
            });
        }
    }

    function addStopRow(value = '') {
        const index = container.find('.at-distcalc-stop-row').length;
        const row = $(`
            <div class="at-distcalc-stop-row">
                <input type="text" name="stops[]" class="at-distcalc-stop-input" 
                       placeholder="Stop ${index + 1} (optional)" 
                       value="${escapeHtml(value)}">
                <button type="button" class="at-distcalc-remove-stop-btn" data-index="${index}">Remove</button>
            </div>
        `);
        container.append(row);

        // Attach remove handler
        row.find('.at-distcalc-remove-stop-btn').on('click', function (e) {
            e.preventDefault();
            row.remove();
        });
    }

    $('#at-distcalc-add-stop-btn').on('click', function (e) {
        e.preventDefault();
        addStopRow();
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Initialize with previous stops or empty
    renderStops();
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
