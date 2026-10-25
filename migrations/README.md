# migrations/

One plain `.sql` file per database change. Run them from **Services → Database Updates**.

- Name: `YYYYMMDD_HHMM_description.sql` (lowercase, underscores), e.g. `20261025_1217_bookings_payment_received.sql`
- Never edit a file once it has run on any database — add a new file instead.
- Write them so running twice is harmless: `ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`.
- Plain DDL/DML only: no triggers, stored procedures or `DELIMITER` (statements are split on semicolons).
- Don't change structure by hand in phpMyAdmin — a change without a file never reaches the other databases.
- On a feature branch, prefer additive changes (new tables, new columns). Anything that renames, drops or redefines an existing column goes through `main` first.

Each database records what it has run in its own `schema_migrations` table, so dev, live and the itinerary server each know what is pending.

Example:

```sql
-- Tracks whether payment has actually been received for a booking.
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS payment_received TINYINT(1) NOT NULL DEFAULT 0;
```
