-- migrations/20261115_1400_prebookings_driver.sql
-- Tentative bookings (prebookings) can now have a driver allocated, like a normal booking:
-- the driver, the booking fee percentage and the driver notes are kept on the tentative booking
-- and carried over when it is converted to a booking.
-- Additive only: three new empty columns, nothing existing changes.
-- Run this BEFORE deploying the Prebookings add/edit pages, otherwise saving a tentative booking shows an error.
-- Safe to run more than once.
ALTER TABLE prebookings
    ADD COLUMN IF NOT EXISTS driver_id INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS booking_fee_pct TINYINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS driver_notes TEXT NULL;
