<?php
$page_title = 'Budget Advisor';
$page_subtitle = 'Next 7 days & monthly pace';
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

$forecast = getSevenDayForecast($pdo);
$pace = getMonthlyPace($pdo);
?>

<div class="at-budget-wrapper">
    <h2>📅 Next 7 Days</h2>

    <?php foreach ($forecast['days'] as $d): ?>
        <div class="at-budget-section">
            <h3><?= htmlspecialchars($d['day_name']) ?> — <?= htmlspecialchars($d['date']) ?></h3>
            <div class="at-budget-row">
                <span class="label">Booking income</span>
                <span class="value">R<?= number_format($d['booking_income'], 2) ?></span>
            </div>
            <div class="at-budget-row">
                <span class="label">Booking fuel (est.)</span>
                <span class="value">R<?= number_format($d['booking_fuel'], 2) ?></span>
            </div>
            <?php if ($d['is_sunday_settlement']): ?>
                <div class="at-budget-row">
                    <span class="label">🚗 Car rental due</span>
                    <span class="value">R<?= number_format($d['settlement']['car_rental'], 2) ?></span>
                </div>
                <div class="at-budget-row">
                    <span class="label">⛽ Uber fuel target</span>
                    <span class="value">R<?= number_format($d['settlement']['uber_fuel_target'], 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="at-budget-row">
                <span class="label">🍽️ Living</span>
                <span class="value">R<?= number_format($d['living_expense'], 2) ?></span>
            </div>
            <div class="at-budget-row">
                <span class="label"><strong>Day net</strong></span>
                <span class="value <?= $d['day_net'] >= 0 ? 'at-budget-ok' : 'at-budget-warn' ?>">
                    R<?= number_format($d['day_net'], 2) ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="at-budget-buffer <?= $forecast['final_net'] >= 0 ? 'positive' : 'negative' ?>">
        7-day projected net: R<?= number_format($forecast['final_net'], 2) ?>
    </div>

    <h2 style="margin-top: 24px;">📆 Monthly Pace</h2>

    <div class="at-budget-section">
        <h3>🏠 Rent</h3>
        <div class="at-budget-row">
            <span class="label">Expected payments so far</span>
            <span class="value"><?= $pace['rent']['expected_payments_so_far'] ?> / 4</span>
        </div>
        <div class="at-budget-row">
            <span class="label">Actually earmarked</span>
            <span class="value">R<?= number_format($pace['rent']['actual_to_date'], 2) ?> (≈<?= $pace['rent']['payments_equivalent'] ?> payments)</span>
        </div>
        <div class="at-budget-row">
            <span class="label">Target-to-date</span>
            <span class="value">R<?= number_format($pace['rent']['target_to_date'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Behind by</span>
            <span class="value <?= $pace['rent']['behind_by'] > 0 ? 'at-budget-warn' : 'at-budget-ok' ?>">
                R<?= number_format($pace['rent']['behind_by'], 2) ?>
            </span>
        </div>
        <small>Monthly target: R<?= number_format($pace['rent']['monthly_target'], 2) ?> (R<?= number_format($pace['rent']['weekly_rate'], 2) ?> × 4)</small>
    </div>

    <div class="at-budget-section">
        <h3>💳 Debt</h3>
        <div class="at-budget-row">
            <span class="label">Expected payments so far</span>
            <span class="value"><?= $pace['debt']['expected_payments_so_far'] ?> / 4</span>
        </div>
        <div class="at-budget-row">
            <span class="label">Actually earmarked</span>
            <span class="value">R<?= number_format($pace['debt']['actual_to_date'], 2) ?> (≈<?= $pace['debt']['payments_equivalent'] ?> payments)</span>
        </div>
        <div class="at-budget-row">
            <span class="label">Target-to-date</span>
            <span class="value">R<?= number_format($pace['debt']['target_to_date'], 2) ?></span>
        </div>
        <div class="at-budget-row">
            <span class="label">Behind by</span>
            <span class="value <?= $pace['debt']['behind_by'] > 0 ? 'at-budget-warn' : 'at-budget-ok' ?>">
                R<?= number_format($pace['debt']['behind_by'], 2) ?>
            </span>
        </div>
        <small>Monthly target: R<?= number_format($pace['debt']['monthly_target'], 2) ?> (R<?= number_format($pace['debt']['weekly_rate'], 2) ?> × 4)</small>
    </div>

    <h2 style="margin-top: 24px;">🤖 Daily Digest</h2>
    <button type="button" class="btn" id="getRecommendationBtn">💬 Get Factual Digest</button>
    <div id="recommendationResult" style="margin-top: 12px;"></div>
</div>

<script>
$(document).ready(function () {
    $('#getRecommendationBtn').on('click', function () {
        const btn = $(this);
        const result = $('#recommendationResult');

        btn.prop('disabled', true).text('Loading...');
        result.html('');

        $.ajax({
            type: 'POST',
            url: '<?= BASE_URL ?>/modules/Budgeting/api/index.php',
            data: { action: 'get_recommendation' },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    result.html('<div class="at-budget-ai-box"><pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' + response.message + '</pre></div>');
                } else {
                    result.html('<div class="error-message">✗ ' + response.message + '</div>');
                }
            },
            error: function () {
                result.html('<div class="error-message">❌ Could not reach the advisor. Try again.</div>');
            },
            complete: function () {
                btn.prop('disabled', false).text('💬 Get Factual Digest');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
