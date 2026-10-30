-- migrations/20261030_1600_address_checking_columns.sql
-- Address checking, step 1: somewhere to keep a checked location for each preset destination and each client address.
-- Purely additive: new NULLable columns, nothing existing is renamed or changed. Safe to run twice.
-- destinations: place_id is Google's id for the place, latitude/longitude an optional exact point, verified_at when it was checked.
-- contacts: address_place_id and address_verified_at do the same for the client's home address.
-- (The client's GPS pin stays in the existing pickup_lat / pickup_lng columns and still takes priority.)

ALTER TABLE destinations
    ADD COLUMN IF NOT EXISTS place_id    VARCHAR(255)  NULL,
    ADD COLUMN IF NOT EXISTS latitude    DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS longitude   DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS verified_at DATETIME      NULL;

ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS address_place_id    VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS address_verified_at DATETIME     NULL;
