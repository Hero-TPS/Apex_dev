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

### [whatsapp] Create `whatsapp_templates` and seed the built-in WhatsApp messages

- [x] Done on dev
- [x] Done on live

```sql
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    placement VARCHAR(20) NOT NULL DEFAULT 'booking_menu',
    body TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    is_builtin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_whatsapp_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO whatsapp_templates (template_key, label, placement, body, active, sort_order, is_builtin) VALUES
('confirmation', 'Booking confirmation', 'booking_menu', '{if is_update}
*BOOKING UPDATED AND CONFIRMED* ✅
{else}
*BOOKING CONFIRMED* ✅
{/if}

Good day {client_name},

📍 Pickup: {pickup}
📅 Date: {date} at {time}
🎯 Destination: {destination}
{if passenger_name}
👤 Picking up: {passenger_name}
📱 Passenger phone: {passenger_phone}
{/if}
💰 Cost: {cost}
{if payment_received}
✅ Payment Received
{/if}
✈️ Flight Number: {flight_number}
📝 Notes: {notes}
{if driver_name}
🚗 Your driver will be: {driver_name}
📱 Driver phone: {driver_phone}
{/if}

{if is_update}
✏️ Updated: {updated_at}
{else}
🕒 Created: {created_at}
{/if}

🚗 Looking forward to being of service to you. 👍

Regards,
{business_owner}
{business_name}', 1, 10, 1),

('bank_details', 'Bank details', 'booking_menu', '{payment_details}', 1, 20, 0),

('pickup_message', 'Airport pickup message', 'booking_menu', 'Good day {client_name},

When you collected your bags, please send me a message. I will then meet you at the pickup in parking area P2
When you walk out of the terminal, it will be the left exit ( by Woolworths). Follow the signs to P2.

White Nissan Almera
CAA 526-434', 1, 30, 0),

('evening_confirmation', 'Evening confirmation (dashboard)', 'evening', 'Good day {client_name}! 👋

Just confirming your booking for tomorrow:

📅 Date: {date}
🕐 Pickup Time: {time}
📍 From: {pickup}
🎯 To: {destination}
{if passenger_name}
👤 Picking up: {passenger_name}
📱 Passenger phone: {passenger_phone}
{/if}
{if driver_name}

🚗 Your driver will be: {driver_name}
📱 Driver phone: {driver_phone}
{/if}

See you tomorrow! 🚗', 1, 100, 1),

('prebooking_reminder', 'Prebooking reminder', 'prebooking', '*BOOKING REMINDER* 📋

Good day {client_name},

We have a tentative booking on record for you:

📅 Date: {date}
⏰ Time: {time}
📍 Pickup: {pickup}
🎯 Destination: {destination}
{if passenger_name}
👤 Picking up: {passenger_name}
📱 Passenger phone: {passenger_phone}
{/if}
💰 Cost: {cost}
📝 Notes: {notes}

Please let us know when you have all the details so we can finalise your booking. 😊

Regards,
{business_owner}
{business_name}', 1, 110, 1),

('driver_notification', 'Message to driver', 'driver', 'Good day {driver_name}! 🚗

You have a booking allocated to you:

📅 Date: {date} at {time}
👤 Client: {client_name}
📱 Client phone: {client_phone}
{if passenger_name}
👤 Picking up: {passenger_name}
📱 Passenger phone: {passenger_phone}
{/if}
📍 Pickup: {pickup}
🧭 Waze: {waze_pickup}
🎯 Destination: {destination}
🧭 Waze: {waze_destination}
{if flight_number}
✈️ Flight Number: {flight_number}
📡 Track: {flightradar_url}
{/if}
💰 Trip Cost: {trip_cost}
{if no_booking_fee}

✅ No booking fee — full amount goes to you.
{if is_eft}
📲 This is an EFT booking with no booking fee. Apex Transit will pay you {settlement_amount} once payment is received from the client.
{else}
💵 This is a cash booking with no booking fee. No payment due to Apex Transit
{/if}
{else}
{if has_booking_fee}
💼 Apex Booking Fee: {booking_fee}

{if is_eft}
📲 This is an EFT booking. Apex Transit will pay you {settlement_amount} (after deducting the booking fee) once payment is received from the client.
{else}
💵 This is a cash booking. Please pay Apex Transit the booking fee of {settlement_amount}.
{/if}
{else}

{if is_eft}
📲 This is an EFT booking.
{else}
💵 This is a cash booking.
{/if}
{/if}
{/if}
{if driver_notes}

📝 Notes: {driver_notes}
{/if}

Thank you! 🙏
Drive safe! 🚗', 1, 120, 1);
```

> Backs the new WhatsApp module (`modules/WhatsApp/`). Every WhatsApp message the system sends now lives in this table instead of in PHP, and new ones can be added under Dashboard → Services → WhatsApp Messages. A table rather than `system_variables` because the list grows. Bodies use `{placeholders}`, plus `{if name}` / `{else}` / `{/if}` on lines of their own for the conditional parts (passenger, driver, payment received, created vs updated, booking fee / EFT / cash). `placement` says where a message is used: `booking_menu` = the WhatsApp dropdown (a message that only uses client placeholders also appears on the Clients pages), `evening` = Tomorrow's Confirmations, `prebooking` = prebooking reminder, `driver` = message to the driver. `active` hides a `booking_menu` message from the dropdown; `is_builtin` messages cannot be deleted (the system looks them up by `template_key`). The first four built-in rows reproduce the existing hardcoded messages word for word; `bank_details` (text comes from the Invoice Payment Details system variable) and `pickup_message` are the two new presets. Contains emoji, so import with the utf8mb4 connection charset. Safe to re-run: existing keys are skipped. The `whatsapp_log` table is unchanged; sent messages are logged under the template key (`confirmation`, `bank_details`, ...).

---

### [whatsapp-af] Add Afrikaans text to `whatsapp_templates` and seed the Afrikaans messages

- [ ] Done on dev
- [ ] Done on live

Run after the `[whatsapp]` entry above.

```sql
ALTER TABLE whatsapp_templates ADD COLUMN IF NOT EXISTS body_af TEXT NULL AFTER body;

UPDATE whatsapp_templates SET body_af = '{if is_update}
*BOEKING OPGEDATEER EN BEVESTIG* ✅
{else}
*BOEKING BEVESTIG* ✅
{/if}

Goeie dag {client_name},

📍 Oplaai: {pickup}
📅 Datum: {date} om {time}
🎯 Bestemming: {destination}
{if passenger_name}
👤 Haal op: {passenger_name}
📱 Passasier se foon: {passenger_phone}
{/if}
💰 Koste: {cost}
{if payment_received}
✅ Betaling ontvang
{/if}
✈️ Vlugnommer: {flight_number}
📝 Notas: {notes}
{if driver_name}
🚗 U drywer sal wees: {driver_name}
📱 Drywer se foon: {driver_phone}
{/if}

{if is_update}
✏️ Opgedateer: {updated_at}
{else}
🕒 Geskep: {created_at}
{/if}

🚗 Ons sien daarna uit om u van diens te wees. 👍

Groete,
{business_owner}
{business_name}' WHERE template_key = 'confirmation' AND body_af IS NULL;

UPDATE whatsapp_templates SET body_af = 'Goeie dag {client_name},

Wanneer u u bagasie gekry het, stuur asseblief vir my ''n boodskap. Ek sal u dan by die oplaaipunt in parkeerarea P2 ontmoet.
Wanneer u by die terminaal uitstap, is dit die linker uitgang (by Woolworths). Volg die tekens na P2.

Wit Nissan Almera
CAA 526-434' WHERE template_key = 'pickup_message' AND body_af IS NULL;

UPDATE whatsapp_templates SET body_af = 'Goeie dag {client_name}! 👋

Ons bevestig net u booking vir môre:

📅 Datum: {date}
🕐 Oplaaityd: {time}
📍 Van: {pickup}
🎯 Na: {destination}
{if passenger_name}
👤 Haal op: {passenger_name}
📱 Passasier se foon: {passenger_phone}
{/if}
{if driver_name}

🚗 U drywer sal wees: {driver_name}
📱 Drywer se foon: {driver_phone}
{/if}

Sien u môre! 🚗' WHERE template_key = 'evening_confirmation' AND body_af IS NULL;

UPDATE whatsapp_templates SET body_af = '*BOEKING HERINNERING* 📋

Goeie dag {client_name},

Ons het ''n voorlopige booking vir u op rekord:

📅 Datum: {date}
⏰ Tyd: {time}
📍 Oplaai: {pickup}
🎯 Bestemming: {destination}
{if passenger_name}
👤 Haal op: {passenger_name}
📱 Passasier se foon: {passenger_phone}
{/if}
💰 Koste: {cost}
📝 Notas: {notes}

Laat weet ons asseblief wanneer u al die besonderhede het sodat ons u booking kan finaliseer. 😊

Groete,
{business_owner}
{business_name}' WHERE template_key = 'prebooking_reminder' AND body_af IS NULL;
```

> Adds an optional Afrikaans text to every WhatsApp message (edit it under Dashboard → Services → WhatsApp Messages). A message with no Afrikaans text is sent in English. Seeds Afrikaans for the booking confirmation, airport pickup message, evening confirmation and prebooking reminder (draft translations, please review). `bank_details` and the driver message have no Afrikaans text (bank details are the same in both languages; the driver message stays English). Each UPDATE only fills a message whose Afrikaans text is still empty, so re-running never overwrites your edits. The Afrikaans airport-pickup and after-hours notices are System Variables (Maintenance), created with their defaults on first use. Contains emoji, so import with the utf8mb4 connection charset.

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
