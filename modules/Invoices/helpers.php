<?php
// modules/Invoices/helpers.php
// Shared helper functions for the Invoices module.
// Expects config.php and includes/helpers.php to be loaded by the caller.

/**
 * Business details shown on invoices. BUSINESS_ADDRESS is read defensively
 * so a missing constant in an older config.php leaves the address blank
 * instead of fatally breaking the invoice page.
 * Used by: partials/invoice_document.php
 *
 * @return array{name:string,address:string,phone:string,email:string}
 */
function getInvoiceBusinessDetails(): array
{
    return [
        'name'    => defined('BUSINESS_NAME') ? BUSINESS_NAME : '',
        'address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'phone'   => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'email'   => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
    ];
}

/**
 * Invoice-related system variables (editable under Maintenance).
 * Used by: partials/invoice_document.php, api/index.php
 *
 * @return array{payment_details:string,payment_terms:string,footer_note:string}
 */
function getInvoiceSettings(PDO $pdo): array
{
    return [
        'payment_details' => trim((string) getSystemVariable($pdo, 'invoice_payment_details')),
        'payment_terms'   => trim((string) getSystemVariable($pdo, 'invoice_payment_terms')),
        'footer_note'     => trim((string) getSystemVariable($pdo, 'invoice_footer_note')),
    ];
}

/**
 * Format an amount as Rands for invoices, e.g. "R 1,250.00".
 */
function formatInvoiceMoney(float $amount): string
{
    return 'R ' . number_format($amount, 2);
}

/**
 * Public (no-login) URL for a client to view an invoice.
 */
function buildInvoicePublicUrl(string $token): string
{
    return BASE_URL . '/modules/Invoices/public.php?t=' . $token;
}

/**
 * Build the frozen line item for a booking at invoice-creation time.
 * Applies the same pickup/destination swap rule used everywhere else.
 * Used by: createInvoice()
 *
 * @param array $booking  Row from bookings
 * @return array{booking_id:int,trip_date:string,description:string,amount:float}
 */
function buildInvoiceItemFromBooking(array $booking): array
{
    $pickup = $booking['was_swapped'] ? $booking['original_destination'] : $booking['original_pickup'];
    $destination = $booking['was_swapped'] ? $booking['original_pickup'] : $booking['original_destination'];

    $description = 'Transport from ' . $pickup . ' to ' . $destination;
    if (!empty($booking['flight_number'])) {
        $description .= "\nFlight: " . $booking['flight_number'];
    }

    return [
        'booking_id'  => (int) $booking['id'],
        'trip_date'   => $booking['trip_date'],
        'description' => $description,
        'amount'      => round((float) $booking['cost'], 2),
    ];
}

/**
 * Payment status from totals: 'paid', 'part' or 'unpaid'.
 */
function getInvoicePaymentStatus(float $total, float $paid): string
{
    if ($total - $paid <= 0.004) {
        return 'paid';
    }
    return $paid > 0.004 ? 'part' : 'unpaid';
}

/**
 * Human label for a payment status key.
 */
function getInvoiceStatusLabel(string $status): string
{
    return match ($status) {
        'paid' => 'Paid',
        'part' => 'Part paid',
        default => 'Unpaid',
    };
}

/**
 * Totals for an invoice's items. "Paid" is the sum of items whose booking
 * is marked payment_received; items whose booking was since deleted count
 * as unpaid.
 *
 * @param float $total  Stored invoices.total
 * @param array $items  Rows from getInvoiceItems()
 * @return array{total:float,paid:float,balance:float,status:string}
 */
function summariseInvoice(float $total, array $items): array
{
    $paid = 0.0;
    foreach ($items as $item) {
        if (!empty($item['payment_received'])) {
            $paid += (float) $item['amount'];
        }
    }
    $paid = round($paid, 2);
    return [
        'total'   => $total,
        'paid'    => $paid,
        'balance' => round(max(0.0, $total - $paid), 2),
        'status'  => getInvoicePaymentStatus($total, $paid),
    ];
}

/**
 * One invoice by ID, with the client's current phone (for WhatsApp).
 */
function getInvoiceById(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT i.*, c.phone AS client_phone
        FROM invoices i
        LEFT JOIN contacts c ON c.id = i.contact_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * One invoice by its public token (the client link). Returns null for any
 * token that isn't exactly 32 lowercase hex characters.
 */
function getInvoiceByToken(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Line items for an invoice, oldest trip first, each with the live
 * payment_received flag of its booking (0 if the booking no longer exists).
 */
function getInvoiceItems(PDO $pdo, int $invoiceId): array
{
    $stmt = $pdo->prepare("
        SELECT ii.*, COALESCE(b.payment_received, 0) AS payment_received
        FROM invoice_items ii
        LEFT JOIN bookings b ON b.id = ii.booking_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.trip_date ASC, ii.id ASC
    ");
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll();
}

/**
 * All invoices (newest first) with item count and paid amount, for the
 * Invoices list.
 */
function getInvoiceList(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT i.*,
               (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) AS item_count,
               (SELECT COALESCE(SUM(ii.amount), 0)
                  FROM invoice_items ii
                  JOIN bookings b ON b.id = ii.booking_id
                 WHERE ii.invoice_id = i.id AND b.payment_received = 1) AS paid_amount
        FROM invoices i
        ORDER BY i.issue_date DESC, i.id DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Every booking for a client (newest first), flagged with the invoice it is
 * already on, if any. Used to build the "pick bookings" table.
 */
function getClientBookingsForInvoicing(PDO $pdo, int $contactId): array
{
    $stmt = $pdo->prepare("
        SELECT b.id, b.trip_date, b.start_time, b.status, b.cost, b.payment_received,
               b.original_pickup, b.original_destination, b.was_swapped, b.flight_number,
               b.invoice_id, i.invoice_number
        FROM bookings b
        LEFT JOIN invoices i ON i.id = b.invoice_id
        WHERE b.contact_id = ?
        ORDER BY b.trip_date DESC, b.start_time DESC, b.id DESC
    ");
    $stmt->execute([$contactId]);
    return $stmt->fetchAll();
}

/**
 * Clients that have at least one booking, for the client picker.
 */
function getClientsWithBookings(PDO $pdo): array
{
    return $pdo->query("
        SELECT c.id, c.name, c.is_archived
        FROM contacts c
        WHERE EXISTS (SELECT 1 FROM bookings b WHERE b.contact_id = c.id)
        ORDER BY c.name ASC
    ")->fetchAll();
}

/**
 * Create an invoice for the given bookings of one client.
 *
 * Runs in a single transaction: the chosen bookings are locked, checked
 * (must belong to this client and not already be on an invoice), line
 * items are frozen, the next AT-YYYY-NNNN number is taken, and the
 * bookings are marked as invoiced. If two requests race for the same
 * number, the unique key rejects one and it is retried.
 * Used by: api/index.php (create)
 *
 * @param PDO         $pdo
 * @param int         $contactId
 * @param int[]       $bookingIds
 * @param string      $issueDate  'Y-m-d'
 * @param string|null $notes
 * @return array{id:int,invoice_number:string}
 * @throws InvalidArgumentException  For problems the user can fix
 * @throws Throwable                 For anything unexpected
 */
function createInvoice(PDO $pdo, int $contactId, array $bookingIds, string $issueDate, ?string $notes): array
{
    $bookingIds = array_values(array_unique(array_filter(array_map('intval', $bookingIds), fn($v) => $v > 0)));
    if (empty($bookingIds)) {
        throw new InvalidArgumentException('Select at least one booking.');
    }

    $issue = DateTime::createFromFormat('Y-m-d', $issueDate, new DateTimeZone(TIME_ZONE));
    if (!$issue || $issue->format('Y-m-d') !== $issueDate) {
        throw new InvalidArgumentException('Invalid invoice date.');
    }

    $year = (int) $issue->format('Y');

    $attempts = 0;
    while (true) {
        $attempts++;
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT name, address, email FROM contacts WHERE id = ?");
            $stmt->execute([$contactId]);
            $contact = $stmt->fetch();
            if (!$contact) {
                throw new InvalidArgumentException('Client not found.');
            }

            $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
            $stmt = $pdo->prepare("
                SELECT * FROM bookings
                WHERE id IN ($placeholders) AND contact_id = ? AND invoice_id IS NULL
                FOR UPDATE
            ");
            $stmt->execute(array_merge($bookingIds, [$contactId]));
            $bookings = $stmt->fetchAll();

            if (count($bookings) !== count($bookingIds)) {
                throw new InvalidArgumentException(
                    'One or more selected bookings are already on an invoice or do not belong to this client. Reload the page and try again.'
                );
            }

            usort($bookings, fn($a, $b) => [$a['trip_date'], $a['start_time'], $a['id']] <=> [$b['trip_date'], $b['start_time'], $b['id']]);
            $items = array_map('buildInvoiceItemFromBooking', $bookings);
            $total = round(array_sum(array_column($items, 'amount')), 2);

            $stmt = $pdo->prepare("SELECT COALESCE(MAX(invoice_seq), 0) + 1 FROM invoices WHERE invoice_year = ? FOR UPDATE");
            $stmt->execute([$year]);
            $seq = (int) $stmt->fetchColumn();
            $number = sprintf('AT-%d-%04d', $year, $seq);
            $token = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("
                INSERT INTO invoices (
                    invoice_year, invoice_seq, invoice_number, public_token, contact_id,
                    bill_to_name, bill_to_address, bill_to_email,
                    issue_date, total, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $year, $seq, $number, $token, $contactId,
                $contact['name'], $contact['address'] ?: null, $contact['email'] ?: null,
                $issue->format('Y-m-d'), $total,
                ($notes !== null && trim($notes) !== '') ? trim($notes) : null,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, booking_id, trip_date, description, amount)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([$invoiceId, $item['booking_id'], $item['trip_date'], $item['description'], $item['amount']]);
            }

            $stmt = $pdo->prepare("UPDATE bookings SET invoice_id = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$invoiceId], $bookingIds));

            $pdo->commit();
            return ['id' => $invoiceId, 'invoice_number' => $number];

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 1062 = duplicate key: another request took this invoice number first.
            $isDuplicate = isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
            if ($isDuplicate && $attempts < 3) {
                continue;
            }
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

/**
 * Delete an invoice and its items, and release its bookings so they can be
 * invoiced again. Returns the deleted invoice number, or null if not found.
 * Used by: api/index.php (delete)
 */
function deleteInvoice(PDO $pdo, int $invoiceId): ?string
{
    $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $number = $stmt->fetchColumn();
    if ($number === false) {
        return null;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE bookings SET invoice_id = NULL WHERE invoice_id = ?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return (string) $number;
}
