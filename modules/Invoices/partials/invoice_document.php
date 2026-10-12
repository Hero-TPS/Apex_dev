<?php
// modules/Invoices/partials/invoice_document.php
// The printable invoice sheet, shared by view.php (logged in) and
// public.php (client link). Does not output <html>/<body> or any buttons.
//
// Expects in scope:
//   array $invoice   Row from invoices
//   array $items     Rows from getInvoiceItems()
//   array $settings  From getInvoiceSettings()
//   array $summary   From summariseInvoice()

$business = getInvoiceBusinessDetails();
$statusKey = $summary['status'];
$hasPaymentDetails = $settings['payment_details'] !== '';
$hasPaymentTerms = $settings['payment_terms'] !== '';
$showPaymentBlock = $summary['balance'] > 0.004 && ($hasPaymentDetails || $hasPaymentTerms);
?>
<div class="at-inv-sheet">
    <div class="at-inv-accent"></div>

    <div class="at-inv-body">
        <header class="at-inv-head">
            <div class="at-inv-brand">
                <img class="at-inv-logo" src="<?= BASE_URL ?>/assets/android-chrome-192x192.png"
                    width="96" height="96" alt="<?= htmlspecialchars($business['name']) ?> logo">
                <div class="at-inv-brand-text">
                    <h1 class="at-inv-business"><?= htmlspecialchars($business['name']) ?></h1>
                    <div class="at-inv-business-details">
                    <?php if (trim($business['address']) !== ''): ?>
                        <?= nl2br(htmlspecialchars($business['address'])) ?><br>
                    <?php endif; ?>
                    <?php if ($business['phone'] !== ''): ?>
                        <?= htmlspecialchars($business['phone']) ?><br>
                    <?php endif; ?>
                    <?php if ($business['email'] !== ''): ?>
                        <?= htmlspecialchars($business['email']) ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="at-inv-title-block">
                <div class="at-inv-title">Invoice</div>
                <div class="at-inv-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                <span class="at-inv-status at-inv-status--<?= htmlspecialchars($statusKey) ?>">
                    <?= htmlspecialchars(getInvoiceStatusLabel($statusKey)) ?>
                </span>
            </div>
        </header>

        <section class="at-inv-parties">
            <div class="at-inv-billto">
                <div class="at-inv-label">Billed to</div>
                <div class="at-inv-client-name"><?= htmlspecialchars($invoice['bill_to_name']) ?></div>
                <?php if (!empty($invoice['bill_to_address'])): ?>
                    <div><?= nl2br(htmlspecialchars($invoice['bill_to_address'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($invoice['bill_to_email'])): ?>
                    <div><?= htmlspecialchars($invoice['bill_to_email']) ?></div>
                <?php endif; ?>
            </div>
            <table class="at-inv-details">
                <tr>
                    <th>Invoice date</th>
                    <td><?= date('d M Y', strtotime($invoice['issue_date'])) ?></td>
                </tr>
                <tr>
                    <th>Amount due</th>
                    <td class="at-inv-details-due"><?= htmlspecialchars(formatInvoiceMoney($summary['balance'])) ?></td>
                </tr>
            </table>
        </section>

        <table class="at-inv-items">
            <thead>
                <tr>
                    <th class="at-inv-col-date">Date</th>
                    <th>Description</th>
                    <th class="at-inv-col-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="at-inv-col-date"><?= date('d M Y', strtotime($item['trip_date'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($item['description'])) ?></td>
                        <td class="at-inv-col-amount">
                            <?= htmlspecialchars(formatInvoiceMoney((float) $item['amount'])) ?>
                            <?php if (!empty($item['payment_received'])): ?>
                                <span class="at-inv-line-paid">Paid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <section class="at-inv-totals">
            <div class="at-inv-totals-row">
                <span>Total</span>
                <span><?= htmlspecialchars(formatInvoiceMoney($summary['total'])) ?></span>
            </div>
            <?php if ($summary['paid'] > 0.004): ?>
                <div class="at-inv-totals-row">
                    <span>Paid</span>
                    <span>− <?= htmlspecialchars(formatInvoiceMoney($summary['paid'])) ?></span>
                </div>
            <?php endif; ?>
            <div class="at-inv-totals-row at-inv-totals-row--due">
                <span>Balance due</span>
                <span><?= htmlspecialchars(formatInvoiceMoney($summary['balance'])) ?></span>
            </div>
        </section>

        <?php if ($showPaymentBlock): ?>
            <section class="at-inv-payment">
                <?php if ($hasPaymentTerms): ?>
                    <div class="at-inv-label">Payment terms</div>
                    <div class="at-inv-payment-terms"><?= nl2br(htmlspecialchars($settings['payment_terms'])) ?></div>
                <?php endif; ?>
                <?php if ($hasPaymentDetails): ?>
                    <div class="at-inv-label<?= $hasPaymentTerms ? ' at-inv-label--spaced' : '' ?>">Payment details</div>
                    <div><?= nl2br(htmlspecialchars($settings['payment_details'])) ?></div>
                    <div class="at-inv-reference">Please use <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong> as your payment reference.</div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($invoice['notes'])): ?>
            <section class="at-inv-notes">
                <div class="at-inv-label">Notes</div>
                <div><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($settings['footer_note'] !== ''): ?>
            <footer class="at-inv-footer"><?= htmlspecialchars($settings['footer_note']) ?></footer>
        <?php endif; ?>
    </div>
</div>
