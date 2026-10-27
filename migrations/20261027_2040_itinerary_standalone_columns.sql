-- migrations/20261027_2040_itinerary_standalone_columns.sql
-- Itinerary step 1 (standalone rework): an itinerary is now its own record, not an add-on to a prebooking.
-- Adds the trip details to `itineraries`. Purely additive: no existing column is renamed, dropped or redefined.
-- The older prebooking_id column is left in place and is simply no longer used by the code.
-- All new columns are NULLable so any row created by the earlier migration stays valid.
-- contact_id has no foreign key on purpose (same reason as before: the contacts.id column type is not defined in the repo).

ALTER TABLE itineraries
    ADD COLUMN IF NOT EXISTS contact_id      INT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS trip_date       DATE NULL AFTER contact_id,
    ADD COLUMN IF NOT EXISTS start_time      TIME NULL AFTER trip_date,
    ADD COLUMN IF NOT EXISTS pickup          VARCHAR(255) NULL AFTER start_time,
    ADD COLUMN IF NOT EXISTS destination     VARCHAR(255) NULL AFTER pickup,
    ADD COLUMN IF NOT EXISTS passenger_name  VARCHAR(255) NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS passenger_phone VARCHAR(50) NULL AFTER passenger_name,
    ADD COLUMN IF NOT EXISTS cost            DECIMAL(10,2) NULL AFTER passenger_phone,
    ADD COLUMN IF NOT EXISTS description     TEXT NULL AFTER cost,
    ADD INDEX IF NOT EXISTS idx_itineraries_trip_date (trip_date),
    ADD INDEX IF NOT EXISTS idx_itineraries_contact (contact_id);
