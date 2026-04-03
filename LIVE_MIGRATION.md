# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### [drivers] Create drivers table & add booking columns
> Extracted from `ensureDriverSchema()` in `includes/helpers.php` (function removed — DB changes must not run inline on every request).

- [x] Done on dev
- [ ] Done on live

```sql
CREATE TABLE IF NOT EXISTS drivers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    phone      VARCHAR(50)  NOT NULL DEFAULT '',
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS driver_id   INT            NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS booking_fee DECIMAL(10,2)  NULL DEFAULT NULL;
```

---

## Completed

*(none yet)*

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done