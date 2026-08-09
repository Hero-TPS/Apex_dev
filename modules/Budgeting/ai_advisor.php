<?php
$page_title = 'Budget Advisor';
$page_subtitle = "This week's budget plan";
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'Budgeting', 'url' => BASE_URL . '/modules/Budgeting/'],
    ['label' => 'AI Advisor'],
]);
include ROOT_DIR . '/includes/header.php';

$plan = getWeeklyBudgetPlan($pdo);
?>

<div class="at-budget-wrapper">
    <h2>📊 Weekly Budget Plan</h2>
    <div class="at-budget-week-label">Week of <?= htmlspecialchars($plan['week_start']) ?></div>

    <div class="at-budget-section">
        <h3>🏠 Rent</h3>
        <div class="at-budget-row">
            <span class="label">Target</span>
            <span class="value">R<?= number_format($plan['rent']['target'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Earmarked from bookings</span>
            <span class="value">R<?= number_format($plan['rent']['earmarked'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Shortfall</span>
            <span class="value <?= $plan['rent']['shortfall'] > 0 ? 'at-budget-warn' : 'at-budget-ok' ?>">
                R<?= number_format($plan['rent']['shortfall'], 2) ?>
            </span>
        </div>
    </div>

    <div class="at-budget-section">
        <h3>💳 Debt</h3>
        <div class="at-budget-row">
            <span class="label">Target</span>
            <span class="value">R<?= number_format($plan['debt']['target'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Earmarked from bookings</span>
            <span class="value">R<?= number_format($plan['debt']['earmarked'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Shortfall</span>
            <span class="value <?= $plan['debt']['shortfall'] > 0 ? 'at-budget-warn' : 'at-budget-ok' ?>">
                R<?= number_format($plan['debt']['shortfall'], 2) ?>
            </span>
        </div>
    </div>

    <div class="at-budget-section">
        <h3>🚗 Car Rental &amp; Fuel</h3>
        <div class="at-budget-row">
            <span class="label">Car rental (via Uber)</span>
            <span class="value">R<?= number_format($plan["car_rental"]["amount"], 2) ?> <small>(<?= htmlspecialchars($plan["car_rental"]["note"]) ?>)</small></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Uber fuel (⅓ of rental)</span>
            <span class="value">R<?= number_format($plan['fuel']['uber_target'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Booking fuel (forecast)</span>
            <span class="value">R<?= number_format($plan['fuel']['booking_forecast'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label"><strong>Total fuel</strong></span>
            <span class="value">R<?= number_format($plan['fuel']['total'], 2) ?></span>
        </div>
        <?php if (!empty($plan['fuel']['booking_details'])): ?>
            <small>
                <?php foreach ($plan['fuel']['booking_details'] as $b): ?>
                    <?= htmlspecialchars($b['trip_date']) ?>:
                    <?= $b['status'] === 'ok' ? number_format($b['distance_km'], 1) . 'km, R' . number_format($b['fuel_cost'], 2) : 'distance lookup failed' ?><br>
                <?php endforeach; ?>
            </small>
        <?php endif; ?>
    </div>

    <div class="at-budget-section">
        <h3>🧾 Running Costs &amp; Living</h3>
        <div class="at-budget-row">
            <span class="label">Running costs (8-week average)</span>
            <span class="value">R<?= number_format($plan['running_costs_planned'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Living expenses target</span>
            <span class="value">R<?= number_format($plan['living_expenses_target'], 2) ?></span>
        </div>
    </div>

    <div class="at-budget-section">
        <h3>💰 Income vs. Obligations</h3>
        <div class="at-budget-row">
            <span class="label">Booking income</span>
            <span class="value">R<?= number_format($plan['income']['booking_income'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Uber income so far</span>
            <span class="value">
                <?php if ($plan['income']['uber_logged']): ?>
                    R<?= number_format($plan['income']['uber_income_so_far'], 2) ?>
                <?php else: ?>
                    <em>Not yet logged (Sundays)</em>
                <?php endif; ?>
            </span>
        </div>
        <div class="at-budget-row">
            <span class="label"><strong>Total income</strong></span>
            <span class="value">R<?= number_format($plan['income']['total'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label"><strong>Total obligations</strong></span>
            <span class="value">R<?= number_format($plan['total_obligations'], 2) ?></span>
        </div>
    </div>

    <div class="at-budget-buffer <?= $plan['buffer'] >= 0 ? 'positive' : 'negative' ?>">
        <?= $plan['buffer'] >= 0 ? '✅ Buffer: ' : '⚠️ Shortfall: ' ?>
        R<?= number_format(abs($plan['buffer']), 2) ?>
    </div>

    <h2 style="margin-top: 24px;">🤖 AI Weekly Briefing</h2>
    <button type="button" class="btn" id="getRecommendationBtn">💬 Get This Week's Briefing</button>
    <div id="recommendationResult" style="margin-top: 12px;"></div>
</div>

<script>
$(document).ready(function () {
    $('#getRecommendationBtn').on('click', function () {
        const btn = $(this);
        const result = $('#recommendationResult');

        btn.prop('disabled', true).text('Thinking...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Budgeting/api/index.php',
            data: { action: 'get_recommendation' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    result.html('<div class="at-budget-ai-box">' + response.message + '</div>');
                } else {
                    result.html('<div class="error-message">✗ ' + response.message + '</div>');
                }
            },
            error: function () {
                result.html('<div class="error-message">❌ Could not reach the advisor. Try again.</div>');
            },
            complete: function () {
                btn.prop('disabled', false).text("💬 Get This Week's Briefing");
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
