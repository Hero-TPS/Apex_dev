-- migrations/20261126_2000_bookings_same_day_return.sql
-- Same-day round trip: one booking for a trip out, a wait, and the trip back on the same day.
-- is_same_day_return marks the booking. The outbound pickup is the booking's own date and start time,
-- return_pickup_time is the pickup time for the way back on that same date. It stays empty until the client
-- has decided on a time. The return leg is the same two places swapped, so it needs no extra place fields.
-- The discount is the round_trip_discount_pct system variable (Services -> System Variables).
-- Additive only: two new columns on bookings. Existing bookings get 0 and no return time.
-- Safe to run more than once. Run this BEFORE deploying the new Bookings files, otherwise adding or editing a
-- booking fails.
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS is_same_day_return TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS return_pickup_time TIME NULL DEFAULT NULL;
