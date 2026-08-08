<?php
// modules/Uber/report.php
//
// Rental shortfall running-balance history report. Read-only — this is
// for showing the rental company your payment history, not for editing.

$page_title = 'Uber Rental Shortfall History';
$page_subtitle = 'Payment & Balance Report';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';
$breadcrumb = buildBreadcrumb([
    ['label' => 'Uber', 'url' => BASE_URL . '/modules/Uber/'],
    ['label' => 'History Report'],
]);
include ROOT_DIR . '/includes/header.php';

$monthsBack = (int) getSystemVariable($pdo, 'financial_months_back');
if ($monthsBack < 1) {
    $monthsBack = 3;
}

$report = getUberLedgerReport($pdo, $monthsBack);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<div class="financial-dashboard">
    <h2>🏠 Rental Shortfall History (Last <?= htmlspecialchars($monthsBack) ?> Months)</h2>

    <div class="form-container" style="margin-bottom: 16px;">
        <div class="metric-row"><span>Current Outstanding Balance</span><strong>R <span id="currentBalanceDisplay"><?= number_format($report['current_balance'], 2) ?></span></strong></div>
        <button type="button" class="btn" id="downloadPdfBtn" style="margin-top: 10px;">📄 Download PDF</button>
    </div>

    <?php if (empty($report['months'])): ?>
        <p style="color:#999; font-style:italic;">No Uber income recorded in this period yet.</p>
    <?php endif; ?>

    <?php foreach ($report['months'] as $m): ?>
        <div class="financial-month-block">
            <div class="month-header">
                <h3><?= htmlspecialchars($m['label']) ?></h3>
                <span class="net-amount <?= $m['totals']['net'] >= 0 ? 'profit' : 'loss' ?>">R<?= number_format($m['totals']['net'], 2) ?></span>
            </div>

            <div class="metric-row"><span>Total Card Income:</span> <strong>R<?= number_format($m['totals']['card_income'], 2) ?></strong></div>
            <div class="metric-row"><span>Total Net:</span> <strong>R<?= number_format($m['totals']['net'], 2) ?></strong></div>
            <div class="metric-row"><span>Total Paid In:</span> <span>R<?= number_format($m['totals']['shortfall_paid'], 2) ?></span></div>
            <div class="metric-row"><span>Total Paid Out To You:</span> <span>R<?= number_format($m['totals']['paid_out'], 2) ?></span></div>
            <div class="metric-row"><span>Balance At Month End:</span> <strong>R<?= number_format($m['balance_at_month_end'], 2) ?></strong></div>

            <table style="width:100%; border-collapse:collapse; font-size:0.85em; margin-top:10px;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="text-align:left; padding:4px 8px;">Week</th>
                        <th style="text-align:right; padding:4px 8px;">Card Income</th>
                        <th style="text-align:right; padding:4px 8px;">Rental</th>
                        <th style="text-align:right; padding:4px 8px;">Fines</th>
                        <th style="text-align:right; padding:4px 8px;">Repairs</th>
                        <th style="text-align:right; padding:4px 8px;">Net</th>
                        <th style="text-align:right; padding:4px 8px;">Paid In</th>
                        <th style="text-align:right; padding:4px 8px;">Paid Out</th>
                        <th style="text-align:right; padding:4px 8px;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($m['weeks'] as $w): ?>
                        <tr>
                            <td style="padding:4px 8px;"><?= htmlspecialchars($w['week_display']) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['card_income'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['car_rental'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['fines'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['vehicle_repairs'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['net'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['shortfall_paid'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;">R<?= number_format($w['paid_out'], 2) ?></td>
                            <td style="text-align:right; padding:4px 8px;"><strong>R<?= number_format($w['balance_after'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>

<script>
    // Full report dataset, embedded for the PDF export — same data the
    // page above was rendered from, so the PDF always matches what's shown.
    const reportData = <?= json_encode($report) ?>;

    $(document).ready(function () {
        $('#downloadPdfBtn').on('click', function () {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape' });

            doc.setFontSize(14);
            doc.text('Uber Rental Shortfall History', 14, 15);
            doc.setFontSize(10);
            doc.text('Current Outstanding Balance: R' + parseFloat(reportData.current_balance).toFixed(2), 14, 22);

            let y = 30;

            reportData.months.forEach(function (m) {
                if (y > 180) {
                    doc.addPage();
                    y = 20;
                }

                doc.setFontSize(12);
                doc.text(m.label + '  (Net: R' + m.totals.net.toFixed(2) + ', Balance at month end: R' + m.balance_at_month_end.toFixed(2) + ')', 14, y);

                const rows = m.weeks.map(function (w) {
                    return [
                        w.week_display,
                        'R' + w.card_income.toFixed(2),
                        'R' + w.car_rental.toFixed(2),
                        'R' + w.fines.toFixed(2),
                        'R' + w.vehicle_repairs.toFixed(2),
                        'R' + w.net.toFixed(2),
                        'R' + w.shortfall_paid.toFixed(2),
                        'R' + w.paid_out.toFixed(2),
                        'R' + w.balance_after.toFixed(2)
                    ];
                });

                doc.autoTable({
                    startY: y + 4,
                    head: [['Week', 'Card Income', 'Rental', 'Fines', 'Repairs', 'Net', 'Paid In', 'Paid Out', 'Balance']],
                    body: rows,
                    styles: { fontSize: 8 },
                    headStyles: { fillColor: [60, 60, 60] },
                    margin: { left: 14, right: 14 }
                });

                y = doc.lastAutoTable.finalY + 12;
            });

            doc.save('uber-rental-shortfall-history.pdf');
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
