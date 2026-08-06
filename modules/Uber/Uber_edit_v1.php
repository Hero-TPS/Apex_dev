<?php
$page_title = 'Edit Uber Income';
$page_subtitle = 'Edit Weekly Record';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/helpers.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Uber', 'url' => BASE_URL . '/modules/Uber/'],
    ['label' => 'Edit'],
]);
include ROOT_DIR . '/includes/header.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="content"><p class="error-message">Invalid record ID.</p></div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}
?>

<div class="content">
    <h2>✏️ Edit Uber Income</h2>
    <div id="edit-form-container"><p>Loading...</p></div>
</div>

<script>
$(document).ready(function () {
    const id = <?= $id ?>;

    // Load existing record
    $.ajax({
        url: '<?= BASE_URL ?>/modules/Uber/api/index.php?action=get_single&id=' + id,
        dataType: 'json',
        success: function (res) {
            if (!res.success) {
                $('#edit-form-container').html('<p class="error-message">Record not found.</p>');
                return;
            }
            const r = res.record;
            window.carRentalPrice = r.shortfall.car_rental;

            // Build additional cost rows
            let costRowsHtml = '';
            if (r.additional_costs && r.additional_costs.length > 0) {
                r.additional_costs.forEach(c => {
                    costRowsHtml += costRowTemplate(c.reason, c.amount);
                });
            } else {
                costRowsHtml = costRowTemplate('', '');
            }

            $('#edit-form-container').html(`
                <form id="edit-uber-form">
                    <input type="hidden" name="id" value="${r.id}">

                    <div class="form-group">
                        <label>Total Income (R)</label>
                        <input type="number" step="0.01" name="total_income" id="total_income" value="${r.total_income}" required>
                    </div>
                    <div class="form-group">
                        <label>Cash Received (R)</label>
                        <input type="number" step="0.01" name="cash_received" value="${r.cash_received}" required>
                    </div>
                    <div class="form-group">
                        <label>Total Trips</label>
                        <input type="number" name="total_trips" value="${r.total_trips}" required>
                    </div>
                    <div class="form-group">
                        <label>Time Online (hrs)</label>
                        <input type="number" step="0.1" name="total_time_online" value="${r.total_time_online}" required>
                    </div>

                    <h3>Additional Costs</h3>
                    <div id="cost-rows">${costRowsHtml}</div>
                    <button type="button" id="add-cost-row" class="action-btn">+ Add Cost</button>

                    <div class="form-group">
                        <h3>🏠 Rental Shortfall</h3>
                        <p class="at-help-text">Record-keeping only — never used in Financials or Budgeting reports.</p>

                        <div class="metric-row"><span>Car Rental (weekly)</span><span>R <span id="shortfallCarRental">${r.shortfall.car_rental.toFixed(2)}</span></span></div>
                        <div class="metric-row"><span>Fines (from Additional Costs, reason "Fines")</span><span>R <span id="shortfallFines">${r.shortfall.fines.toFixed(2)}</span></span></div>
                        <div class="metric-row"><span>Shortfall Due This Week</span><strong>R <span id="shortfallDue">0.00</span></strong></div>

                        <div class="form-group">
                            <label for="shortfall_carried_in">Carried Over In (R)</label>
                            <input type="number" id="shortfall_carried_in" name="shortfall_carried_in" step="0.01" min="0" value="${r.shortfall_carried_in}">
                        </div>

                        <div class="metric-row"><span>Total Owed</span><strong>R <span id="shortfallTotalOwed">0.00</span></strong></div>

                        <div class="form-group">
                            <label for="shortfall_paid">Amount Paid In This Week (R) <span class="required">*</span></label>
                            <input type="number" id="shortfall_paid" name="shortfall_paid" step="0.01" min="0" value="${r.shortfall_paid}" required>
                        </div>

                        <div class="metric-row"><span>Balance To Carry Forward</span><strong>R <span id="shortfallCarriedOut">0.00</span></strong></div>
                    </div>

                    <div class="form-actions" style="margin-top:20px;">
                        <button type="submit" class="action-btn edit-btn">💾 Save Changes</button>
                        <a href="<?= BASE_URL ?>/modules/Uber/index.php" class="action-btn">✖ Cancel</a>
                    </div>
                </form>
            `);

            recalcShortfall();
        },
        error: function () {
            $('#edit-form-container').html('<p class="error-message">Failed to load record.</p>');
        }
    });

    function recalcShortfall() {
        const totalIncome = parseFloat($('#total_income').val()) || 0;

        let fines = 0;
        $('.cost-row').each(function () {
            const reason = $(this).find('input[name="cost_reasons[]"]').val();
            const amount = parseFloat($(this).find('input[name="cost_amounts[]"]').val()) || 0;
            if (reason === 'Fines') {
                fines += amount;
            }
        });

        const carriedIn = parseFloat($('#shortfall_carried_in').val()) || 0;
        const paid = parseFloat($('#shortfall_paid').val()) || 0;

        const shortfallDue = Math.max((window.carRentalPrice || 0) + fines - totalIncome, 0);
        const totalOwed = shortfallDue + carriedIn;
        const carriedOut = Math.max(totalOwed - paid, 0);

        $('#shortfallFines').text(fines.toFixed(2));
        $('#shortfallDue').text(shortfallDue.toFixed(2));
        $('#shortfallTotalOwed').text(totalOwed.toFixed(2));
        $('#shortfallCarriedOut').text(carriedOut.toFixed(2));
    }

    function costRowTemplate(reason, amount) {
        return `
            <div class="cost-row" style="display:flex;gap:10px;margin-bottom:8px;">
                <input type="text" name="cost_reasons[]" placeholder="Reason" value="${reason}" style="flex:2;">
                <input type="number" step="0.01" name="cost_amounts[]" placeholder="Amount" value="${amount}" style="flex:1;">
                <button type="button" class="action-btn delete-btn remove-cost-row">✖</button>
            </div>
        `;
    }

    // Add cost row
    $(document).on('click', '#add-cost-row', function () {
        $('#cost-rows').append(costRowTemplate('', ''));
    });

    // Remove cost row
    $(document).on('click', '.remove-cost-row', function () {
        $(this).closest('.cost-row').remove();
        recalcShortfall();
    });

    // Live-recalculate the shortfall as relevant fields change
    $(document).on('input change', '#total_income, #shortfall_carried_in, #shortfall_paid, input[name="cost_reasons[]"], input[name="cost_amounts[]"]', recalcShortfall);

    // Submit
    $(document).on('submit', '#edit-uber-form', function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=update';

        $.ajax({
            url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    window.location.href = '<?= BASE_URL ?>/modules/Uber/index.php';
                } else {
                    alert('❌ ' + res.message);
                }
            },
            error: function () {
                alert('❌ Failed to save changes.');
            }
        });
    });
});
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>