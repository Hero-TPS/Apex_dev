# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### [bookings] Add `payment_received` column

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS payment_received TINYINT(1) NOT NULL DEFAULT 0;
```

> Tracks whether payment has actually been received for a booking — independent of `payment_method` (cash/EFT), which only records how it's meant to be paid. Editable via checkbox on Add/Edit Booking. Shown as a ✅ icon next to Cost in the Bookings index, as "Payment Received: Yes/No" on the booking view, and — only when true — as a "✅ Payment Received" line in the client WhatsApp confirmation message (`createWhatsAppMessage()` in `includes/helpers.php`).

---

### [contacts] Add `is_archived` column

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0;
```

> Lets a once-off client be hidden from the main Clients list without deleting the contact record (bookings must stay for records, so the row can never actually be deleted while bookings exist). Toggled via an Archive/Unarchive button on the Clients list (`toggle_archive` action in `modules/Clients/api/index.php`); archived clients are excluded from the `all`/`with_bookings`/`without_bookings` filters and only show up under a new `📦 Archived` filter tab. They still appear (flagged with a "📦 archived" note) in the client picker on Bookings/Prebookings add & edit, and are automatically un-archived the moment a new booking or prebooking is made for them (`reactivateContactIfArchived()` in `includes/helpers.php`) — no manual unarchive step needed.

---

### [bookings] Add `passenger_name` and `passenger_phone` columns

- [ ] Done on dev
- [ ] Done on live

```sql
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS passenger_name VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS passenger_phone VARCHAR(30) NULL;
```

> Optional fields on Add/Edit Booking for when the person being picked up isn't the client who made the booking. When `passenger_name` is filled and differs from the client's own name, a "👤 Picking up: ..." line (plus phone, if given) is added to the client WhatsApp confirmation, the evening reminder, and the driver message (`buildPassengerInfoLine()`, `createWhatsAppMessage()`, `createEveningConfirmationMessage()`, `createDriverBookingMessage()` in `includes/helpers.php`). The driver message also now includes the booking's flight number, if set.

---

### [prebookings] Add `passenger_name` and `passenger_phone` columns

- [ ] Done on dev
- [ ] Done on live

```sql
ALTER TABLE prebookings
    ADD COLUMN IF NOT EXISTS passenger_name VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS passenger_phone VARCHAR(30) NULL;
```

> Same optional "picking up someone else" fields as on Bookings, now on Add/Edit Prebooking too, shown in the prebooking WhatsApp reminder message (`createPrebookingWhatsAppMessage()` in `includes/helpers.php`) via the same `buildPassengerInfoLine()` helper. Carried over automatically when a prebooking is converted to a booking (`handleConvert()` in `modules/Prebookings/api/index.php` now passes both fields through to `modules/Bookings/add.php` as prefill query params).

---

### [fuel_logs] Add `vehicle_changed` column

- [ ] Done on dev
- [ ] Done on live

```sql
ALTER TABLE fuel_logs
    ADD COLUMN IF NOT EXISTS vehicle_changed TINYINT(1) NOT NULL DEFAULT 0;
```

> A "🚙 Vehicle changed at this fill-up" checkbox on Add/Edit Fuel Log — a note that the odometer reset/jumped because a different vehicle was used, no calc changes. Shown as a 🚙 badge next to the date in the Fuel Log list. Also excluded from the recent-average used by the new trip-km sanity check on Add Fuel Log (`modules/Fuel/add.php`, computed at page load from the last 10 non-flagged fill-ups) — a flagged entry's trip km is expected to be unrelated to the trend, so it shouldn't skew or trigger the warning for the next entry either.

---

### [uber_income] Add `notes` column

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE uber_income
    ADD COLUMN IF NOT EXISTS notes TEXT NULL;
```

> Free-text comments per weekly Uber income record, entered on Add/Edit Uber Income and shown as a "Notes" row on that week's block in the Uber Reports page. Record-keeping only — not used in any calculation.

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
