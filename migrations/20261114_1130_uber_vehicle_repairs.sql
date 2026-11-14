-- migrations/20261114_1130_uber_vehicle_repairs.sql
-- Vehicle repairs tracker for the Uber module.
-- uber_vehicle_repairs holds one row per repair (the total due).
-- Weekly payments stay as normal Additional Cost rows and point at their repair through repair_id.
-- The remaining balance is always calculated (total minus linked payments), never stored.
-- Existing Vehicle Repairs rows keep repair_id empty, so weekly Net and Financials do not change.
-- Run this BEFORE deploying the Vehicle Repairs page, otherwise the page shows a load error.
-- Safe to run more than once.
CREATE TABLE IF NOT EXISTS uber_vehicle_repairs (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    description   VARCHAR(255) NOT NULL,
    repair_date   DATE NOT NULL,
    total_amount  DECIMAL(10,2) NOT NULL,
    weekly_amount DECIMAL(10,2) NULL DEFAULT NULL,
    is_closed     TINYINT(1) NOT NULL DEFAULT 0,
    notes         TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE uber_additional_costs
    ADD COLUMN IF NOT EXISTS repair_id INT UNSIGNED NULL DEFAULT NULL;

ALTER TABLE uber_additional_costs
    ADD INDEX IF NOT EXISTS idx_uac_repair_id (repair_id);
