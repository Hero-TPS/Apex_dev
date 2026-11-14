<?php
// modules/Uber/repairs.php
// Vehicle repairs: the total due for each repair and how much has been paid off
// through the weekly Additional Costs (reason "Vehicle Repairs").
$page_title = 'Vehicle Repairs';
$page_subtitle = 'Repair balances';
$show_breadcrumb = true;

require_once __DIR__ . '/../../config.php';
require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/helpers.php';
require_once __DIR__ . '/helper.php';

$breadcrumb = buildBreadcrumb([
    ['label' => 'Uber', 'url' => BASE_URL . '/modules/Uber/'],
    ['label' => 'Vehicle Repairs'],
]);

$loadError = false;
$repairs = [];
$payments = [];
try {
    $repairs = getUberRepairs($pdo);
    $payments = getUberRepairPayments($pdo);
} catch (PDOException $e) {
    $loadError = true;
    logError('UBER_REPAIRS', 'Failed to load vehicle repairs', ['error' => $e->getMessage()]);
}

$statusLabels = [
    'open'     => 'Open',
    'paid_off' => 'Paid off',
    'closed'   => 'Closed',
];

$outstanding = 0.0;
$openCount = 0;
foreach ($repairs as $repair) {
    if ($repair['status'] === 'open') {
        $outstanding += $repair['remaining'];
        $openCount++;
    }
}

$today = (new DateTime('now', new DateTimeZone(TIME_ZONE)))->format('Y-m-d');

include ROOT_DIR . '/includes/header.php';
?>

<h2>🔧 Vehicle Repairs</h2>

<?php if (!$loadError): ?>
    <button type="button" class="page-action-btn save" id="addRepairBtn">➕ Add Repair</button>
<?php endif; ?>

<div id="notification-area"></div>

<?php if ($loadError): ?>
    <div class="error-message">Could not load vehicle repairs. If this is the first time, run the pending updates under Services → Database Updates.</div>
<?php elseif (empty($repairs)): ?>
    <div class="no-bookings">
        <h3>🔧 No repairs yet</h3>
        <p>Add a repair with its total cost, then pay it off from the weekly Uber entries.</p>
    </div>
<?php else: ?>
    <div class="at-repair-summary">
        <strong>Still owing:</strong> R<?= number_format($outstanding, 2) ?>
        across <?= (int) $openCount ?> open repair<?= $openCount === 1 ? '' : 's' ?>
    </div>

    <table class="bookings-table at-repair-table">
        <thead>
            <tr>
                <th>Repair</th>
                <th>Date</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Remaining</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($repairs as $repair):
                $repairId = (int) $repair['id'];
                $repairPayments = $payments[$repairId] ?? [];
            ?>
                <tr id="repair-row-<?= $repairId ?>"
                    data-id="<?= $repairId ?>"
                    data-description="<?= htmlspecialchars($repair['description']) ?>"
                    data-date="<?= htmlspecialchars($repair['repair_date']) ?>"
                    data-total="<?= htmlspecialchars(number_format($repair['total_amount'], 2, '.', '')) ?>"
                    data-weekly="<?= $repair['weekly_amount'] !== null ? htmlspecialchars(number_format($repair['weekly_amount'], 2, '.', '')) : '' ?>"
                    data-notes="<?= htmlspecialchars((string) $repair['notes']) ?>">
                    <td data-label="Repair">
                        <strong><?= htmlspecialchars($repair['description']) ?></strong>
                        <?php if ($repair['weekly_amount'] !== null): ?>
                            <p class="at-help-text">Suggested weekly: R<?= number_format($repair['weekly_amount'], 2) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($repair['notes'])): ?>
                            <p class="at-help-text"><?= nl2br(htmlspecialchars($repair['notes'])) ?></p>
                        <?php endif; ?>
                    </td>
                    <td data-label="Date"><?= date('d/m/Y', strtotime($repair['repair_date'])) ?></td>
                    <td data-label="Total">R<?= number_format($repair['total_amount'], 2) ?></td>
                    <td data-label="Paid">R<?= number_format($repair['paid_amount'], 2) ?></td>
                    <td data-label="Remaining"><strong>R<?= number_format($repair['remaining'], 2) ?></strong></td>
                    <td data-label="Status">
                        <span class="at-repair-status at-repair-status--<?= htmlspecialchars($repair['status']) ?>">
                            <?= htmlspecialchars($statusLabels[$repair['status']] ?? $repair['status']) ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <div class="actions-container">
                            <button type="button" class="action-btn view-details-btn at-repair-payments-toggle"
                                data-target="repair-payments-<?= $repairId ?>">Payments (<?= (int) $repair['payment_count'] ?>)</button>
                            <button type="button" class="action-btn edit-btn at-repair-edit">Edit</button>
                            <?php if ($repair['status'] === 'open'): ?>
                                <button type="button" class="action-btn convert-btn at-repair-close"
                                    data-id="<?= $repairId ?>" data-closed="1">Close</button>
                            <?php elseif ($repair['status'] === 'closed'): ?>
                                <button type="button" class="action-btn invoice-btn at-repair-close"
                                    data-id="<?= $repairId ?>" data-closed="0">Reopen</button>
                            <?php endif; ?>
                            <button type="button" class="action-btn delete-btn at-repair-delete"
                                data-id="<?= $repairId ?>"
                                data-description="<?= htmlspecialchars($repair['description']) ?>">Delete</button>
                        </div>
                    </td>
                </tr>
                <tr id="repair-payments-<?= $repairId ?>" class="at-repair-payments-row hidden">
                    <td colspan="7" data-label="Payments">
                        <?php if (empty($repairPayments)): ?>
                            <p class="at-help-text">No payments yet. Choose this repair on a weekly Uber entry to pay towards it.</p>
                        <?php else: ?>
                            <ul class="at-repair-payments-list">
                                <?php foreach ($repairPayments as $payment): ?>
                                    <li>
                                        <span><?= htmlspecialchars($payment['week_display']) ?></span>
                                        <strong>R<?= number_format($payment['amount'], 2) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Add / Edit Repair Modal -->
<div id="repairFormModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="repairFormTitle">Add Repair</h3>
        <form id="repairForm">
            <input type="hidden" id="repair_id" name="id" value="">

            <div class="form-group">
                <label for="repair_description">What was repaired <span class="required">*</span></label>
                <input type="text" id="repair_description" name="description" maxlength="255" required>
            </div>

            <div class="form-group">
                <label for="repair_date">Repair date <span class="required">*</span></label>
                <input type="date" id="repair_date" name="repair_date" value="<?= htmlspecialchars($today) ?>" required>
            </div>

            <div class="form-group">
                <label for="repair_total">Total cost (R) <span class="required">*</span></label>
                <input type="number" id="repair_total" name="total_amount" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="repair_weekly">Suggested weekly amount (R)</label>
                <input type="number" id="repair_weekly" name="weekly_amount" step="0.01" min="0">
                <p class="at-help-text">Optional. Only a reminder — you still choose the amount on each weekly entry.</p>
            </div>

            <div class="form-group">
                <label for="repair_notes">Notes</label>
                <textarea id="repair_notes" name="notes" rows="3" maxlength="1000"></textarea>
            </div>

            <div id="repairFormError"></div>

            <div class="modal-buttons">
                <button type="submit" id="saveRepairBtn" class="modal-btn confirm-btn">Save</button>
                <button type="button" id="cancelRepairBtn" class="modal-btn cancel-btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteRepairModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Delete repair?</h3>
        <p id="deleteRepairText"></p>
        <div class="modal-buttons">
            <button id="confirmDeleteRepairBtn" class="modal-btn confirm-btn">Yes, Delete</button>
            <button id="cancelDeleteRepairBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var apiUrl = '<?= BASE_URL ?>/modules/Uber/api/repairs.php';
        var today = '<?= htmlspecialchars($today) ?>';
        var formModal = $('#repairFormModal');
        var deleteModal = $('#deleteRepairModal');
        var repairToDelete = null;
        var notificationArea = $('#notification-area');

        function showMessage(cls, text) {
            notificationArea.html($('<div>').addClass(cls).text(text));
        }

        // The page shows balances worked out on the server, so reload to refresh them.
        function reloadSoon() {
            setTimeout(function () { window.location.reload(); }, 800);
        }

        function ajaxError(xhr, fallback) {
            var message = fallback;
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.message) { message = response.message; }
            } catch (e) { /* keep fallback */ }
            return message;
        }

        // ----- Add / Edit -----
        function openForm(row) {
            $('#repairFormError').empty();
            if (row) {
                $('#repairFormTitle').text('Edit Repair');
                $('#repair_id').val(row.attr('data-id'));
                $('#repair_description').val(row.attr('data-description'));
                $('#repair_date').val(row.attr('data-date'));
                $('#repair_total').val(row.attr('data-total'));
                $('#repair_weekly').val(row.attr('data-weekly'));
                $('#repair_notes').val(row.attr('data-notes'));
            } else {
                $('#repairFormTitle').text('Add Repair');
                $('#repairForm')[0].reset();
                $('#repair_id').val('');
                $('#repair_date').val(today);
            }
            formModal.css('display', 'flex');
        }

        $('#addRepairBtn').on('click', function () { openForm(null); });

        $('.at-repair-edit').on('click', function () {
            openForm($(this).closest('tr'));
        });

        $('#cancelRepairBtn').on('click', function () { formModal.hide(); });

        $('#repairForm').on('submit', function (e) {
            e.preventDefault();
            var saveBtn = $('#saveRepairBtn');
            var isEdit = $('#repair_id').val() !== '';
            var data = $(this).serializeArray();
            data.push({ name: 'action', value: isEdit ? 'update' : 'add' });

            saveBtn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: apiUrl,
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        formModal.hide();
                        showMessage('success-message', '✓ ' + response.message);
                        reloadSoon();
                    } else {
                        $('#repairFormError').html($('<div>').addClass('error-message').text('✗ ' + response.message));
                    }
                },
                error: function (xhr) {
                    $('#repairFormError').html($('<div>').addClass('error-message')
                        .text('✗ ' + ajaxError(xhr, 'Could not save the repair. Please try again.')));
                },
                complete: function () { saveBtn.prop('disabled', false); }
            });
        });

        // ----- Payments list -----
        $('.at-repair-payments-toggle').on('click', function () {
            $('#' + $(this).data('target')).toggleClass('hidden');
        });

        // ----- Close / Reopen -----
        $('.at-repair-close').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: apiUrl,
                data: { action: 'set_closed', id: btn.data('id'), closed: btn.attr('data-closed') },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showMessage('success-message', '✓ ' + response.message);
                        reloadSoon();
                    } else {
                        btn.prop('disabled', false);
                        showMessage('error-message', '✗ ' + response.message);
                    }
                },
                error: function (xhr) {
                    btn.prop('disabled', false);
                    showMessage('error-message', '✗ ' + ajaxError(xhr, 'Could not update the repair. Please try again.'));
                }
            });
        });

        // ----- Delete -----
        $('.at-repair-delete').on('click', function () {
            repairToDelete = $(this).data('id');
            $('#deleteRepairText').text(
                'This permanently deletes "' + $(this).attr('data-description') + '". ' +
                'A repair that already has payments linked to it cannot be deleted.'
            );
            deleteModal.css('display', 'flex');
        });

        $('#cancelDeleteRepairBtn').on('click', function () {
            deleteModal.hide();
            repairToDelete = null;
        });

        $('#confirmDeleteRepairBtn').on('click', function () {
            if (!repairToDelete) { return; }
            var id = repairToDelete;
            repairToDelete = null;
            deleteModal.hide();
            $.ajax({
                type: 'POST',
                url: apiUrl,
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showMessage('success-message', '✓ ' + response.message);
                        reloadSoon();
                    } else {
                        showMessage('error-message', '✗ ' + response.message);
                    }
                },
                error: function (xhr) {
                    showMessage('error-message', '✗ ' + ajaxError(xhr, 'Could not delete the repair. Please try again.'));
                }
            });
        });
    });
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>
