-- migrations/20261127_2100_bookings_driver_leg.sql
-- Same-day round trip: which part of the trip the allocated driver does.
-- driver_leg is 'whole' (the full round trip, the default), 'outbound' (only the trip out) or 'return'
-- (only the trip back). A single leg is paid and charged a booking fee on half of the booking cost.
-- Only used when the booking is a same-day round trip. Every other booking keeps 'whole'.
-- Additive only: one new column on bookings. Safe to run more than once.
-- Run this BEFORE deploying the new Bookings files, otherwise adding or editing a booking fails.
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS driver_leg VARCHAR(8) NOT NULL DEFAULT 'whole';
