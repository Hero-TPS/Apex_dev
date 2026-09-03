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

- [ ] Done on dev
- [ ] Done on live

```sql
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0;
```

> Lets a once-off client be hidden from the main Clients list without deleting the contact record (bookings must stay for records, so the row can never actually be deleted while bookings exist). Toggled via an Archive/Unarchive button on the Clients list (`toggle_archive` action in `modules/Clients/api/index.php`); archived clients are excluded from the `all`/`with_bookings`/`without_bookings` filters and only show up under a new `📦 Archived` filter tab. They still appear (flagged with a "📦 archived" note) in the client picker on Bookings/Prebookings add & edit, and are automatically un-archived the moment a new booking or prebooking is made for them (`reactivateContactIfArchived()` in `includes/helpers.php`) — no manual unarchive step needed.

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
