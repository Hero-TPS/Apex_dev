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

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done
