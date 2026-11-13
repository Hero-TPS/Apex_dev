-- migrations/20261107_1405_bookings_booking_fee_pct.sql
-- Per-booking booking fee percentage (0 to 30, in steps of 5), chosen in the driver section of Add / Edit / View Booking.
-- 0 = no booking fee (the existing no_booking_fee flag is set alongside it).
-- NULL = a booking made before this column existed: it keeps its stored booking_fee / no_booking_fee as they are.
-- Safe to run more than once.
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS booking_fee_pct TINYINT UNSIGNED NULL DEFAULT NULL AFTER booking_fee;
