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

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
