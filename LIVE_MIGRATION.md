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

### [system_variables] Add `system_variable_history` table for rate history

- [x] Done on dev
- [x] Done on live

```sql
CREATE TABLE IF NOT EXISTS system_variable_history (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    variable_name  VARCHAR(100) NOT NULL,
    value          TEXT NOT NULL,
    effective_from DATE NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_variable_effective (variable_name, effective_from)
);
```

> Generic history table for any rate-type system variable, not just `car_rental_price`. Lookup rule: most recent row where `effective_from <= asOfDate` for that variable. If no rows exist yet for a variable, callers fall back to the current live `system_variables` value — this is what protects existing history from being retroactively rewritten. See `getHistoricalVariable()` in `includes/helpers.php`. First variable wired up: `car_rental_price`, in `modules/Uber/helper.php` and `modules/Financials/helper.php`.
>
> A one-off seed script (`maintenance/seed_variable_history_ONEOFF.php`, deleted after use) anchors every `SYSTEM_VARIABLES` key with its current value dated 1 Dec 2025 — this predates any reportable week, so it prevents the fallback from silently inheriting a *later* rate for periods before it existed. Also wired into `maintenance/api/index.php`'s `update_variables` action: any variable's first-ever save or genuine value change auto-logs a history row dated that day — this is both the ongoing rate-change mechanism and the auto-anchor for any future new variable.

---

### [uber_income] Add rental override columns

- [x] Done on dev
- [x] Done on live

```sql
ALTER TABLE uber_income
    ADD COLUMN IF NOT EXISTS rental_override DECIMAL(10,2) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS rental_override_at DATETIME NULL DEFAULT NULL;
```

> `rental_override`: manually set on a single week to override that week's car rental figure outright (e.g. a discounted week for repairs/service). NULL on every week except one you've explicitly overridden. `rental_override_at`: timestamp of when the override was set, for your own reference. Precedence per week: this override → `system_variable_history` lookup → flat `system_variables` value as ultimate fallback. Set only in Uber (see `modules/Uber/api/index.php` — `handleSetRentalOverride()`), but read by both `modules/Uber/helper.php` and `modules/Financials/helper.php` so the change reflects automatically in Financials too.

---

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
