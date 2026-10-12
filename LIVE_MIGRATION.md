# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

### [invoices] Create `invoices` and `invoice_items` tables, add `bookings.invoice_id`

- [x] Done on dev
- [x] Done on live

```sql
CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_year SMALLINT UNSIGNED NOT NULL,
    invoice_seq INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(20) NOT NULL,
    public_token CHAR(32) NOT NULL,
    contact_id INT NOT NULL,
    bill_to_name VARCHAR(150) NOT NULL,
    bill_to_address TEXT NULL,
    bill_to_email VARCHAR(150) NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoice_number (invoice_year, invoice_seq),
    UNIQUE KEY uq_invoice_token (public_token),
    KEY idx_invoice_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    booking_id INT NULL,
    trip_date DATE NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_item_invoice (invoice_id),
    KEY idx_item_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS invoice_id INT UNSIGNED NULL,
    ADD INDEX IF NOT EXISTS idx_bookings_invoice (invoice_id);
```

> Backs the new Invoices module (`modules/Invoices/`). One invoice can cover several bookings of the same client. `invoices` holds the header (number `AT-YYYY-NNNN` built from `invoice_year` + `invoice_seq`, a random `public_token` for the client link, a snapshot of the client's name/address/email, issue/due dates, total). `invoice_items` holds a frozen copy of each line (date, description, amount) so an invoice never changes if a booking is edited or deleted later; `booking_id` is just a pointer for payment status. `bookings.invoice_id` marks a booking as already invoiced so it can't be billed twice — it is cleared again when an invoice is deleted. No foreign keys on purpose (existing column types can differ between dev and live). Payment status on an invoice is derived from `bookings.payment_received`, not stored. New system variables (`invoice_payment_details`, `invoice_payment_terms_days`, `invoice_footer_note`) need no SQL — they fall back to defaults until saved under Maintenance → System Variables.

---

### [invoices] Make `due_date` optional

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE invoices
    MODIFY due_date DATE NULL;
```

> Invoices no longer calculate a due date from a "days until due" number. Payment terms are now free text (`invoice_payment_terms` system variable, empty by default, shown on unpaid invoices only when filled in), so new invoices are saved without a due date. The column is kept (not dropped) so any invoices already created keep their stored value; it is no longer displayed. Run this after the `[invoices] Create invoices and invoice_items tables` entry above.

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
