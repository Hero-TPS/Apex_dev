<?php
$page_title = 'Uber Reports';
$page_subtitle = 'Monthly Summary';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';
$breadcrumb = buildBreadcrumb([['label' => 'Uber']]);

$monthsBack = (int) getSystemVariable($pdo, 'financial_months_back');
if ($monthsBack < 1) {
    $monthsBack = 3;
}

$months = [];
$today        = new DateTime();
$firstOfMonth = new DateTime($today->format('Y-m-01'));
for ($i = 0; $i < $monthsBack; $i++) {
    $date  = clone $firstOfMonth;
    $date->modify("-$i months");
    $months[] = [
        'year'  => (int) $date->format('Y'),
        'month' => (int) $date->format('n'),
    ];
}
usort($months, function ($a, $b) {
    if ($a['year'] !== $b['year']) {
        return $b['year'] - $a['year'];
    }
    return $b['month'] - $a['month'];
});

// Calculate current working week boundaries (Mon–Sun)
$tz = new DateTimeZone(TIME_ZONE);
$todayDt    = new DateTime('now', $tz);
$dayOfWeek  = (int) $todayDt->format('N'); // 1=Mon, 7=Sun
$currentWeekMonday = clone $todayDt;
$currentWeekMonday->modify('-' . ($dayOfWeek - 1) . ' days');
$currentWeekMonday->setTime(0, 0, 0);
$currentWeekSunday = clone $currentWeekMonday;
$currentWeekSunday->modify('+6 days');
$currentWeekSunday->setTime(23, 59, 59);

include ROOT_DIR . '/includes/header.php';

$carRentalPrice = (float) getSystemVariable($pdo, 'car_rental_price');

// Computed once for the whole page — every month's "Balance" row looks
// up its own entry from this rather than recomputing.
$balanceWalk = calculateUberBalanceWalk($pdo);

// === EXPORT SINCE LAST CORRECTION ===
// Finds the most recent week with a correction set, then gathers every
// week from that point forward (inclusive) for the PDF export button.
$lastOverrideId = null;
foreach ($balanceWalk as $wid => $entry) {
    if ($entry['is_override']) {
        $lastOverrideId = $wid; // last one wins — walk is in week order
    }
}

$exportRows = [];
if ($lastOverrideId !== null) {
    $walkIds = array_keys($balanceWalk);
    $startIndex = array_search($lastOverrideId, $walkIds, true);
    $relevantIds = array_slice($walkIds, $startIndex);

    $placeholders = implode(',', array_fill(0, count($relevantIds), '?'));
    $exportStmt = $pdo->prepare("SELECT * FROM uber_income WHERE id IN ($placeholders) ORDER BY week_start ASC, id ASC");
    $exportStmt->execute($relevantIds);

    foreach ($exportStmt->fetchAll(PDO::FETCH_ASSOC) as $wr) {
        $fin = calculateUberWeekFinancials($pdo, $wr);

        $wStart = new DateTime();
        $wStart->setTimestamp((int) $wr['week_start']);
        $wStart->setTimezone($tz);
        $wEnd = new DateTime();
        $wEnd->setTimestamp((int) $wr['week_end']);
        $wEnd->setTimezone($tz);

        $balEntry = $balanceWalk[(int) $wr['id']];

        $exportRows[] = [
            'week_display'    => $wStart->format('d M Y') . ' – ' . $wEnd->format('d M Y'),
            'card_income'     => $fin['card_income'],
            'car_rental'      => $fin['car_rental'],
            'fines'           => $fin['fines'],
            'vehicle_repairs' => $fin['vehicle_repairs'],
            'net'             => $fin['net'],
            'shortfall_paid'  => $fin['shortfall_paid'],
            'balance'         => $balEntry['balance'],
            'is_override'     => $balEntry['is_override'],
        ];
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<div class="financial-dashboard">
    <h2>🚗 Uber Reports (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <?php if (!empty($exportRows)): ?>
        <button type="button" id="exportSinceCorrectionBtn" class="action-btn">📄 Export Since Last Correction (PDF)</button>
    <?php endif; ?>

    <?php foreach ($months as $m):
        $startDate = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $endDate   = clone $startDate;
        $endDate->modify('last day of this month');

        $startUnix = $startDate->getTimestamp();
        $endUnix   = $endDate->getTimestamp();

        // === INCOME TOTALS ===
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_income), 0)     AS total_income,
                    COALESCE(SUM(cash_received), 0)     AS cash_received,
                    COALESCE(SUM(total_trips), 0)       AS total_trips,
                    COALESCE(SUM(total_time_online), 0) AS total_time_online,
                    COALESCE(SUM(shortfall_paid), 0)    AS shortfall_paid,
                    COUNT(*)                            AS week_count
             FROM uber_income
             WHERE week_start BETWEEN ? AND ?"
        );
        $stmt->execute([$startUnix, $endUnix]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // === ADDITIONAL COSTS (separate to avoid row multiplication) ===
        $costStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(uac.amount), 0) AS additional_costs
             FROM uber_additional_costs uac
             JOIN uber_income ui ON uac.uber_income_id = ui.id
             WHERE ui.week_start BETWEEN ? AND ?"
        );
        $costStmt->execute([$startUnix, $endUnix]);
        $row['additional_costs'] = (float) $costStmt->fetchColumn();

        // Fines + Vehicle Repairs only, for the Total Net calc — matches
        // calculateUberWeekFinancials() in helper.php
        $shortfallCostStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(uac.amount), 0) AS amount
             FROM uber_additional_costs uac
             JOIN uber_income ui ON uac.uber_income_id = ui.id
             WHERE ui.week_start BETWEEN ? AND ?
             AND uac.reason IN ('Fines', 'Vehicle Repairs')"
        );
        $shortfallCostStmt->execute([$startUnix, $endUnix]);
        $finesAndRepairs = (float) $shortfallCostStmt->fetchColumn();

        $cardIncome = $row['total_income'] - $row['cash_received'];
        $totalNet   = $cardIncome - ($carRentalPrice * $row['week_count']) - $finesAndRepairs;
        $monthLabel = date('F Y', mktime(0, 0, 0, $m['month'], 1, $m['year']));

        // === BALANCE (last resolved week in this month, if any) ===
        $lastWeekStmt = $pdo->prepare(
            "SELECT id FROM uber_income WHERE week_start BETWEEN ? AND ? ORDER BY week_start DESC LIMIT 1"
        );
        $lastWeekStmt->execute([$startUnix, $endUnix]);
        $lastWeekId = $lastWeekStmt->fetchColumn();
        $monthBalance = $lastWeekId ? ($balanceWalk[(int) $lastWeekId]['balance'] ?? null) : null;

        $correctionStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM uber_income WHERE week_start BETWEEN ? AND ? AND balance_override IS NOT NULL"
        );
        $correctionStmt->execute([$startUnix, $endUnix]);
        $monthHasCorrection = (int) $correctionStmt->fetchColumn() > 0;

        $monthStart = new DateTime("{$m['year']}-{$m['month']}-01", $tz);
        $monthEnd   = clone $monthStart;
        $monthEnd->modify('last day of this month');
        $monthEnd->setTime(23, 59, 59);
        $isInProgressMonth = ($currentWeekMonday <= $monthEnd && $currentWeekSunday >= $monthStart);
    ?>
        <div class="financial-month-block<?= $isInProgressMonth ? ' week-in-progress-block' : '' ?>" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
            <div class="month-header">
                <h3><?= htmlspecialchars($monthLabel) ?><?= $isInProgressMonth ? ' <span class="week-in-progress">⏳ In Progress</span>' : '' ?></h3>
                <span class="net-amount profit">R<?= number_format($row['total_income'], 2) ?></span>
            </div>

            <div class="metric-row"><span>Total Income:</span>      <strong>R<?= number_format($row['total_income'], 2) ?></strong></div>
            <div class="metric-row"><span>Cash Received:</span>     <span>R<?= number_format($row['cash_received'], 2) ?></span></div>
            <div class="metric-row"><span>Card Income:</span>        <span>R<?= number_format($cardIncome, 2) ?></span></div>
            <div class="metric-row"><span>Total Trips:</span>        <strong><?= (int) $row['total_trips'] ?></strong></div>
            <div class="metric-row"><span>Time Online:</span>        <span><?= number_format($row['total_time_online'], 1) ?> hrs</span></div>
            <div class="metric-row"><span>Additional Costs:</span>   <span>R<?= number_format($row['additional_costs'], 2) ?></span></div>
            <div class="metric-row"><span>Total Net:</span>          <strong class="net-amount <?= $totalNet >= 0 ? 'profit' : 'loss' ?>">R<?= number_format($totalNet, 2) ?></strong></div>
            <div class="metric-row"><span>Total Paid In:</span>      <span>R<?= number_format($row['shortfall_paid'], 2) ?></span></div>
            <?php if ($monthBalance !== null): ?>
                <div class="metric-row"><span>Balance:</span> <strong>R<?= number_format($monthBalance, 2) ?></strong></div>
            <?php endif; ?>
            <?php if ($monthHasCorrection): ?>
                <div class="metric-row"><span></span><span class="at-balance-flag">⚠️ Balance manually corrected this month</span></div>
            <?php endif; ?>

            <button class="toggle-weeks-btn" data-year="<?= $m['year'] ?>" data-month="<?= $m['month'] ?>">
                🔽 View Weeks
            </button>
            <div class="weeks-container hidden"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    const exportRows = <?= json_encode($exportRows) ?>;

    function exportSinceLastCorrection() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape' });

        doc.setFontSize(14);
        doc.text('Uber Rental Balance — Since Last Correction', 14, 15);

        const rows = exportRows.map(function (w) {
            const balanceText = w.balance !== null
                ? 'R' + parseFloat(w.balance).toFixed(2) + (w.is_override ? ' (corrected)' : '')
                : '—';
            return [
                w.week_display,
                'R' + parseFloat(w.card_income).toFixed(2),
                'R' + parseFloat(w.car_rental).toFixed(2),
                'R' + parseFloat(w.fines).toFixed(2),
                'R' + parseFloat(w.vehicle_repairs).toFixed(2),
                'R' + parseFloat(w.net).toFixed(2),
                'R' + parseFloat(w.shortfall_paid).toFixed(2),
                balanceText
            ];
        });

        doc.autoTable({
            startY: 22,
            head: [['Week', 'Card Income', 'Rental', 'Fines', 'Repairs', 'Net', 'Paid In', 'Balance']],
            body: rows,
            styles: { fontSize: 8 },
            headStyles: { fillColor: [60, 60, 60] },
            margin: { left: 14, right: 14 }
        });

        doc.save('uber-balance-since-last-correction.pdf');
    }

    function buildWeekBlock(log) {
        let costsHtml = '—';
        if (log.additional_costs && log.additional_costs.length > 0) {
            costsHtml = log.additional_costs.map(c =>
                `${c.reason}: R ${parseFloat(c.amount).toFixed(2)}`
            ).join('<br>');
        }
        const cardIncome = (parseFloat(log.total_income) - parseFloat(log.cash_received)).toFixed(2);
        const inProgressBadge = log.in_progress
            ? '<span class="week-in-progress">⏳ In Progress</span>'
            : '';

        let balanceRowsHtml = '';
        const bal = log.balance || { balance: null, is_override: false, override_at: null };
        if (bal.balance !== null) {
            balanceRowsHtml = `<div class="metric-row"><span>Balance:</span><strong>R${parseFloat(bal.balance).toFixed(2)}</strong></div>`;
            if (bal.is_override) {
                const d = bal.override_at ? new Date(bal.override_at.replace(' ', 'T')) : null;
                const dateStr = d ? d.toLocaleDateString() : '';
                balanceRowsHtml += `<div class="metric-row"><span></span><span class="at-balance-flag">⚠️ Balance manually corrected${dateStr ? ' on ' + dateStr : ''}</span></div>`;
            }
        }

        const currentBalanceForInput = bal.balance !== null ? bal.balance : '';

        return `
            <div class="weekly-block${log.in_progress ? ' week-in-progress-block' : ''}">
                <div class="week-header">
                    <strong>Week: ${log.week_display} ${inProgressBadge}</strong>
                    <span class="net-amount profit">R${parseFloat(log.total_income || 0).toFixed(2)}</span>
                </div>
                <div class="metric-row"><span>Total Income:</span>    <strong>R${parseFloat(log.total_income || 0).toFixed(2)}</strong></div>
                <div class="metric-row"><span>Cash Received:</span>   <span>R${parseFloat(log.cash_received || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Card Income:</span>     <span>R${cardIncome}</span></div>
                <div class="metric-row"><span>Total Trips:</span>     <strong>${log.total_trips}</strong></div>
                <div class="metric-row"><span>Time Online:</span>     <span>${parseFloat(log.total_time_online || 0).toFixed(1)} hrs</span></div>
                <div class="metric-row"><span>Additional Costs:</span><span>${costsHtml}</span></div>
                <div class="metric-row"><span>Car Rental:</span><span>R${parseFloat(log.financials.car_rental || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Fines:</span><span>R${parseFloat(log.financials.fines || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Vehicle Repairs:</span><span>R${parseFloat(log.financials.vehicle_repairs || 0).toFixed(2)}</span></div>
                <div class="metric-row"><span>Net This Week:</span><strong class="net-amount ${parseFloat(log.financials.net || 0) >= 0 ? 'profit' : 'loss'}">R${parseFloat(log.financials.net || 0).toFixed(2)}</strong></div>
                <div class="metric-row"><span>Paid In:</span><span>R${parseFloat(log.financials.shortfall_paid || 0).toFixed(2)}</span></div>
                ${balanceRowsHtml}
                <div class="metric-row">
                    <span></span>
                    <span>
                        <button type="button" class="action-btn correct-balance-btn" data-id="${log.id}" data-current="${currentBalanceForInput}" data-has-override="${bal.is_override}">⚙️ Correct Balance</button>
                    </span>
                </div>
                <div class="balance-correction-form hidden" data-id="${log.id}">
                    <p class="at-balance-flag at-balance-correction-hint">Positive = you're ahead. Negative = you're behind.</p>
                    <input type="number" step="0.01" class="balance-correction-input" placeholder="Corrected balance">
                    <button type="button" class="action-btn save-correction-btn" data-id="${log.id}">💾 Save</button>
                    ${bal.is_override ? `<button type="button" class="action-btn delete-btn clear-correction-btn" data-id="${log.id}">🗑️ Clear</button>` : ''}
                    <button type="button" class="action-btn cancel-correction-btn">✖ Cancel</button>
                </div>
                <div class="metric-row">
                    <span></span>
                    <span>
                        <a href="<?= BASE_URL ?>/modules/Uber/edit.php?id=${log.id}" class="action-btn edit-btn">✏️ Edit</a>
                        <button class="action-btn delete-btn" data-id="${log.id}">🗑️ Delete</button>
                    </span>
                </div>
            </div>
        `;
    }

    $(document).ready(function () {
        $('#exportSinceCorrectionBtn').on('click', exportSinceLastCorrection);
        let currentlyOpenContainer = null;

        $(document).on('click', '.toggle-weeks-btn', function () {
            const button    = $(this);
            const year      = button.data('year');
            const month     = button.data('month');
            const container = button.next('.weeks-container');

            if (currentlyOpenContainer && currentlyOpenContainer !== container[0]) {
                $(currentlyOpenContainer).addClass('hidden').empty();
                $(currentlyOpenContainer).prev('.toggle-weeks-btn').text('🔽 View Weeks');
            }

            if (container.hasClass('hidden')) {
                if (container.is(':empty')) {
                    container.html('<div style="text-align:center; padding:10px;">Loading weeks…</div>');
                    $.ajax({
                        url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                        method: 'GET',
                        data: { action: 'get_by_month', year: year, month: month },
                        dataType: 'json',
                        success: function (response) {
                            container.empty();
                            if (response.success && response.data.length > 0) {
                                response.data.forEach(function (log) {
                                    container.append(buildWeekBlock(log));
                                });
                            } else if (response.success) {
                                container.html('<div class="error-message">No Uber records found for this month.</div>');
                            } else {
                                container.html('<div class="error-message">⚠️ ' + (response.message || 'Failed to load weeks') + '</div>');
                            }
                        },
                        error: function (xhr, status, err) {
                            container.html('<div class="error-message">⚠️ Network error: ' + err + '</div>');
                        }
                    });
                }
                container.removeClass('hidden');
                button.text('🔼 Hide Weeks');
                currentlyOpenContainer = container[0];
            } else {
                container.addClass('hidden');
                button.text('🔽 View Weeks');
                currentlyOpenContainer = null;
            }
        });

        // Delete handler for Uber records inside weekly blocks
        $(document).on('click', '.delete-btn:not(.clear-correction-btn)', function () {
            if (!confirm('Delete this week\'s income?')) return;
            const id = $(this).data('id');

            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        // Remove the weekly-block and reset its container so it reloads next time
                        const weekBlock = $('button[data-id="' + id + '"]').closest('.weekly-block');
                        const container = weekBlock.closest('.weeks-container');
                        weekBlock.fadeOut(function () {
                            $(this).remove();
                            if (container.find('.weekly-block').length === 0) {
                                container.html('<div class="error-message">No Uber records found for this month.</div>');
                            }
                        });
                        showNotification('✓ ' + res.message, 'success');
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to delete record', 'error');
                }
            });
        });

        // Open the correction form for a week
        $(document).on('click', '.correct-balance-btn', function () {
            const id = $(this).data('id');
            const current = $(this).data('current');
            const form = $('.balance-correction-form[data-id="' + id + '"]');
            form.find('.balance-correction-input').val(current);
            form.removeClass('hidden');
        });

        $(document).on('click', '.cancel-correction-btn', function () {
            $(this).closest('.balance-correction-form').addClass('hidden');
        });

        $(document).on('click', '.save-correction-btn', function () {
            const id = $(this).data('id');
            const form = $(this).closest('.balance-correction-form');
            const value = form.find('.balance-correction-input').val();

            if (value === '' || isNaN(parseFloat(value))) {
                showNotification('✗ Enter a value first', 'error');
                return;
            }

            saveBalanceCorrection(id, value);
        });

        $(document).on('click', '.clear-correction-btn', function () {
            const id = $(this).data('id');
            if (!confirm('Clear this correction? The balance will go back to being calculated normally.')) return;
            saveBalanceCorrection(id, '');
        });

        function saveBalanceCorrection(id, value) {
            $.ajax({
                url: '<?= BASE_URL ?>/modules/Uber/api/index.php',
                type: 'POST',
                data: { action: 'set_balance_override', id: id, value: value },
                dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showNotification('✓ ' + res.message + ' — reloading…', 'success');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        showNotification('✗ ' + res.message, 'error');
                    }
                },
                error: function () {
                    showNotification('❌ Failed to save correction', 'error');
                }
            });
        }

        function showNotification(message, type) {
            const className = type === 'success' ? 'success-message' : 'error-message';
            const notification = $('<div class="' + className + '">' + message + '</div>');
            $('.financial-dashboard').before(notification);
            setTimeout(function () {
                notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
