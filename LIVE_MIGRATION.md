# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### [bookings] Add `no_booking_fee` and `driver_notes` columns

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS no_booking_fee TINYINT(1) NOT NULL DEFAULT 0 AFTER booking_fee,
    ADD COLUMN IF NOT EXISTS driver_notes   TEXT       NULL DEFAULT NULL   AFTER no_booking_fee;
```

---

### [prebookings] Add `original_pickup` and `was_swapped` columns

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE prebookings
    ADD COLUMN IF NOT EXISTS original_pickup VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS was_swapped     TINYINT(1)  NOT NULL DEFAULT 0;
```

---

### [prebookings] Create prebookings table

- [x] Done on dev
- [x] Done on live

```sql
CREATE TABLE IF NOT EXISTS prebookings (
    id                       INT            AUTO_INCREMENT PRIMARY KEY,
    contact_id               INT            NOT NULL,
    trip_date                DATE           NOT NULL,
    start_time               TIME           NULL DEFAULT NULL,
    original_destination     VARCHAR(255)   NULL DEFAULT NULL,
    cost                     DECIMAL(10,2)  NULL DEFAULT NULL,
    description              TEXT           NULL DEFAULT NULL,
    google_calendar_event_id VARCHAR(255)   NULL DEFAULT NULL,
    converted_booking_id     INT            NULL DEFAULT NULL,
    date_created             DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

### [drivers] Create drivers table & add booking columns
> Extracted from `ensureDriverSchema()` in `includes/helpers.php` (function removed — DB changes must not run inline on every request).

- [x] Done on dev
- [x] Done on live

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