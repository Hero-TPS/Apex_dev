# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### [contacts] Add `wa_sent_date` column for WA cleanup campaign

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS wa_sent_date DATE NULL DEFAULT NULL;
```

---

### [contacts] Add `wa_status` column for WA cleanup campaign tracking

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS wa_status VARCHAR(50) NULL DEFAULT NULL;
```

> Valid values: `NULL` (not contacted), `sent`, `positive`

---

### [uber_income] Add rental shortfall tracking column

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE uber_income
    ADD COLUMN IF NOT EXISTS shortfall_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00;
```

> `shortfall_paid`: actual amount paid toward the rental shortfall that week. Record-keeping only — never referenced by Financials/Budgeting.
>
> Note: an earlier version of this migration also added `shortfall_carried_in`. That's been superseded — the running balance is now computed live from history (see `modules/Uber/helper.php`) rather than stored per week. If you already ran the old version of this migration on live, also run:
> ```sql
> ALTER TABLE uber_income DROP COLUMN IF EXISTS shortfall_carried_in;
> ```

> Also add **"Vehicle Repairs"** to the Additional Cost Reasons list via Maintenance → Uber Additional Cost Reasons — this is a data change, not a schema change, so it's not a SQL migration, but it's required for the shortfall calculation to pick up repair costs.

---

### [Budgeting] Add `ai_recommendations` cache table

- [x] Done on dev
- [x] Done on live

```sql
CREATE TABLE IF NOT EXISTS ai_recommendations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    type           VARCHAR(50) NOT NULL DEFAULT 'daily_fuel',
    snapshot_hash  CHAR(32) NOT NULL,
    recommendation TEXT NOT NULL,
    generated_at   DATETIME NOT NULL,
    INDEX (type, generated_at)
);
```

---

### [Budgeting] Add `earmarked_for` to bookings (for rent/debt allocation)

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE bookings
    ADD COLUMN earmarked_for ENUM('rent','debt') NULL DEFAULT NULL AFTER status;
```

> Superseded by the next entry below — a single either/or value wasn't enough since one booking can partially fund both rent and debt. If you already ran this on live, run the replacement entry after it.

---

### [Budgeting] Replace `earmarked_for` with split `earmarked_rent` / `earmarked_debt` amounts

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE bookings
    DROP COLUMN earmarked_for,
    ADD COLUMN earmarked_rent DECIMAL(10,2) NULL DEFAULT NULL AFTER status,
    ADD COLUMN earmarked_debt DECIMAL(10,2) NULL DEFAULT NULL AFTER earmarked_rent;
```

---

### [Budgeting] Widen `system_variables.value` from VARCHAR(255) to TEXT

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE system_variables
    MODIFY COLUMN value TEXT NULL;
```

> Needed for `ai_prompt_template` — the AI prompt text is longer than 255 characters and was silently truncating on save.

---

### [uber_income] Add balance correction columns

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE uber_income
    ADD COLUMN IF NOT EXISTS balance_override DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS balance_override_at DATETIME NULL DEFAULT NULL;
```

> `balance_override`: manually set on a week to seed or correct the running Balance shown in Uber Reports. NULL on every week except one you've explicitly corrected. `balance_override_at`: timestamp of when the correction was made (for your own audit trail / to show the rental company when a fix was applied). See `modules/Uber/helper.php` — `calculateUberBalanceWalk()`. Record-keeping only — never referenced by Financials/Budgeting.

---

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
