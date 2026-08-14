<?php
$page_title = 'Log Uber Income';
$page_subtitle = 'Log Weekly Uber Earnings';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Uber', 'url' => BASE_URL . '/modules/Uber/'],
    ['label' => 'Add'],
]);
include ROOT_DIR . '/includes/header.php';

// Generate last 8 Mondays (2 months back), starting from the current week's Monday.
// This allows logging the current week on any day including Sunday.
$mondays = [];
$today = new DateTime('now', new DateTimeZone(TIME_ZONE));
$dayOfWeek = (int) $today->format('N'); // 1 = Monday, 7 = Sunday
$currentMonday = clone $today;
$currentMonday->modify('-' . ($dayOfWeek - 1) . ' days');
$currentMonday->setTime(0, 0, 0);
for ($i = 0; $i < 8; $i++) {
    $monday = clone $currentMonday;
    if ($i > 0) {
        $monday->modify("-{$i} weeks");
    }
    $mondays[] = $monday->format('Y-m-d');
}
$default_monday = $mondays[0];

// Get existing weeks as Y-m-d strings
$stmt = $pdo->query("SELECT week_start FROM uber_income");
$existing_weeks_ymd = [];
while ($row = $stmt->fetch()) {
    $dt = new DateTime();
    $dt->setTimestamp($row['week_start']);
    $dt->setTimezone(new DateTimeZone(TIME_ZONE));
    $existing_weeks_ymd[] = $dt->format('Y-m-d');
}

// Fetch cost reasons for dropdown
$cost_reasons = fetchColumn($pdo, 'uber_cost_reasons', 'reason', 'reason ASC');

// Current car rental price, used for the shortfall calculation display
$car_rental_price = (float) getSystemVariable($pdo, 'car_rental_price');
?>

<div class="form-container">
    <h2>🚗 Log Uber Income</h2>
    <form id="uberForm">
        <div class="form-group">
            <label>Week Start (Monday)</label>
            <select id="week_monday" name="week_monday" required>
                <?php foreach ($mondays as $monday): ?>
                    <?php
                    $dt = new DateTime($monday);
                    $display = $dt->format('d M Y');
                    $exists = in_array($monday, $existing_weeks_ymd) ? ' (Exists)' : '';
                    $selected = ($monday == $default_monday) ? 'selected' : '';
                    ?>
                    <option value="<?= $monday ?>" <?= $selected ?>><?= $display ?><?= $exists ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="total_income">Total Uber Income (R) <span class="required">*</span></label>
            <input type="number" id="total_income" name="total_income" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="cash_received">Cash Received (R) <span class="required">*</span></label>
            <input type="number" id="cash_received" name="cash_received" step="0.01" required>
        </div>

        <div class="form-group">
            <label for="total_trips">Total Trips <span class="required">*</span></label>
            <input type="number" id="total_trips" name="total_trips" min="0" required>
        </div>

        <div class="form-group">
            <label for="total_time_online">Total Time Online (hours) <span class="required">*</span></label>
            <input type="number" id="total_time_online" name="total_time_online" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>Additional Costs</label>
            <div id="additional-costs-container">
                <!-- Cost rows added dynamically -->
            </div>
            <button type="button" class="btn" id="addCostBtn" style="margin-top: 8px; width: auto;">+ Add Cost</button>
        </div>

        <div class="form-group">
            <h3>🏠 Rental Shortfall</h3>
            <p class="at-help-text">Record-keeping only — never used in Financials or Budgeting reports.</p>

            <div class="metric-row"><span>Card Income (Total Income − Cash)</span><span>R <span id="shortfallCardIncome">0.00</span></span></div>
            <div class="metric-row"><span>Car Rental (weekly)</span><span>R <span id="shortfallCarRental"><?= number_format($car_rental_price, 2) ?></span></span></div>
            <div class="metric-row"><span>Fines (Additional Costs)</span><span>R <span id="shortfallFines">0.00</span></span></div>
            <div class="metric-row"><span>Vehicle Repairs (Additional Costs)</span><span>R <span id="shortfallRepairs">0.00</span></span></div>
            <div class="metric-row"><span>Net This Week</span><strong>R <span id="shortfallNet">0.00</span></strong></div>

            <div class="form-group">
                <label for="shortfall_paid">Amount Paid In (R)</label>
                <input type="number" id="shortfall_paid" name="shortfall_paid" step="0.01" min="0" value="0">
                <p class="at-help-text">Only fill this in if you actually paid money toward the rental company this week.</p>
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn">💾 Save Income</button>
    </form>
    <div id="result"></div>
</div>

<script>
    const costReasons = <?= json_encode($cost_reasons) ?>;
    const carRentalPrice = <?= json_encode($car_rental_price) ?>;

    function buildReasonOptions(selected = '') {
        let options = '<option value="">Select reason</option>';
        costReasons.forEach(r => {
            options += `<option value="${r}" ${r === selected ? 'selected' : ''}>${r}</option>`;
        });
        return options;
    }

    function addCostRow(reason = '', amount = '') {
        const row = $(`
            <div class="cost-row" style="display: flex; gap: 8px; margin-bottom: 6px; align-items: center;">
                <select name="cost_reasons[]" style="flex: 2;">
                    ${buildReasonOptions(reason)}
                </select>
                <input type="number" name="cost_amounts[]" step="0.01" min="0" placeholder="Amount (R)"
                    value="${amount}" style="flex: 1;">
                <button type="button" class="remove-cost-btn action-btn delete-btn" style="width: auto;">✕</button>
            </div>
        `);
        $('#additional-costs-container').append(row);
    }

    function recalcShortfall() {
        const totalIncome = parseFloat($('#total_income').val()) || 0;
        const cashReceived = parseFloat($('#cash_received').val()) || 0;
        const cardIncome = totalIncome - cashReceived;

        let fines = 0;
        let repairs = 0;
        $('.cost-row').each(function () {
            const reason = $(this).find('select[name="cost_reasons[]"]').val();
            const amount = parseFloat($(this).find('input[name="cost_amounts[]"]').val()) || 0;
            if (reason === 'Fines') {
                fines += amount;
            } else if (reason === 'Vehicle Repairs') {
                repairs += amount;
            }
        });

        const deductions = carRentalPrice + fines + repairs;
        const net = cardIncome - deductions;

        $('#shortfallCardIncome').text(cardIncome.toFixed(2));
        $('#shortfallFines').text(fines.toFixed(2));
        $('#shortfallRepairs').text(repairs.toFixed(2));
        $('#shortfallNet').text(net.toFixed(2));
    }

    $(document).ready(function () {

        $('#addCostBtn').on('click', function () {
            addCostRow();
        });

        $(document).on('click', '.remove-cost-btn', function () {
            $(this).closest('.cost-row').remove();
        });

        $(document).on('input change', '#total_income, #cash_received, #shortfall_paid, select[name="cost_reasons[]"], input[name="cost_amounts[]"]', recalcShortfall);

        // Initial calc on load (car rental already applies even with no income entered yet)
        recalcShortfall();

        $('#uberForm').on('submit', function (e) {
            e.preventDefault();

            const submitBtn = $('#submitBtn');
            const result = $('#result');

            submitBtn.prop('disabled', true).text('Saving...');

            const formData = {
                action: 'add',
                week_monday: $('#week_monday').val(),
                total_income: $('#total_income').val(),
                cash_received: $('#cash_received').val(),
                total_trips: $('#total_trips').val(),
                total_time_online: $('#total_time_online').val(),
                shortfall_paid: $('#shortfall_paid').val(),
                'cost_reasons[]': $('select[name="cost_reasons[]"]').map(function () { return $(this).val(); }).get(),
                'cost_amounts[]': $('input[name="cost_amounts[]"]').map(function () { return $(this).val(); }).get()
            };

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        result.html('<div class="success-message">✓ ' + response.message + '</div>');
                        $('#uberForm')[0].reset();
                        $('#additional-costs-container').empty();
                        setTimeout(() => {
                            window.location.href = '<?= BASE_URL ?>/modules/Uber/';
                        }, 1500);
                    } else {
                        result.html('<div class="error-message">✗ ' + response.message + '</div>');
                    }
                },
                error: function (xhr) {
                    let errorMsg = 'Failed to save Uber income';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) errorMsg = response.message;
                    } catch (e) {
                        errorMsg = xhr.responseText || 'Unknown error occurred';
                    }
                    result.html('<div class="error-message">✗ ' + errorMsg + '</div>');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).text('💾 Save Income');
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
