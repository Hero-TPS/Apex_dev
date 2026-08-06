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

### [uber_income] Add rental shortfall tracking columns

- [x] Done on dev
- [ ] Done on live

```sql
ALTER TABLE uber_income
    ADD COLUMN IF NOT EXISTS shortfall_carried_in DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS shortfall_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00;
```

> `shortfall_carried_in`: unpaid rental shortfall balance carried over from the previous week (auto-suggested, manually adjustable).
> `shortfall_paid`: actual amount paid toward the shortfall that week. Record-keeping only — never referenced by Financials/Budgeting.

---

---

**Notes:**
- Each entry requires two checkboxes: **Done on dev** and **Done on live**
- Always run on dev first and verify before running on live
- Move completed items to the Completed section with the date done# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### [contacts] Add `wa_sent_date` column for WA cleanup campaign

- [x] Done on dev
- [x] Done on live
