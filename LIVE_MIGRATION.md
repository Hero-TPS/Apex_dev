# Live Migration Scripts

SQL scripts that must be run on the **live database** during the next update.
Run in order. Check off each one after executing.

---

## Pending

### 1. System Variables Table Cleanup
- [ ] Run on live DB

```sql
-- Ensure name is unique (required for ON DUPLICATE KEY UPDATE)
ALTER TABLE system_variables ADD UNIQUE KEY uq_name (name);

-- Optional cleanup (label/type now live in code, not DB)
ALTER TABLE system_variables DROP COLUMN label;
ALTER TABLE system_variables DROP COLUMN type;
```

---

### 2. Uber Additional Costs — New Table & Migration
- [ ] Run on live DB

```sql
-- Create new uber_additional_costs table
CREATE TABLE uber_additional_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uber_income_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL DEFAULT '',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    INDEX (uber_income_id)
);

-- Migrate existing mobile_data_cost rows
INSERT INTO uber_additional_costs (uber_income_id, reason, amount)
SELECT id, 'Mobile Data', mobile_data_cost
FROM uber_income
WHERE mobile_data_cost > 0;

-- Migrate existing additional_cost rows
INSERT INTO uber_additional_costs (uber_income_id, reason, amount)
SELECT id, COALESCE(NULLIF(cost_reason, ''), 'Other'), additional_cost
FROM uber_income
WHERE additional_cost > 0;

-- Drop old columns
ALTER TABLE uber_income
    DROP COLUMN mobile_data_cost,
    DROP COLUMN additional_cost,
    DROP COLUMN cost_reason;
```

---

### 2. Save gate code
- [ ] Run on live DB

```sql
-- Add gate_code
ALTER TABLE bookings ADD COLUMN gate_code VARCHAR(255) NULL DEFAULT NULL;
---

## ✅ Completed

_(none yet)_

---

**Notes:**
- Always run on local first and verify before running on live
- Check off each item after executing
- Move completed items to the Completed section with the date done