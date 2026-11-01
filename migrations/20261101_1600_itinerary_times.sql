-- migrations/20261101_1600_itinerary_times.sql
-- Itinerary arrival times: drive time for each leg, and a "this time is fixed" tick on each stop.
-- Purely additive: new NULLable columns. Safe to run twice.
-- itinerary_stops.drive_minutes is the driving time of the leg that arrives at the stop.
-- itinerary_stops.time_fixed: 1 = the arrival time was set by hand and never changes by itself,
-- 0 = the time follows the previous stop automatically. NULL (stops saved before this update) is treated as
-- fixed when the stop has an arrival time, so nothing already planned is changed.
-- itineraries.final_leg_minutes and return_leg_minutes are the drive times of the last leg
-- (to the destination, or back to the pickup).

ALTER TABLE itinerary_stops
    ADD COLUMN IF NOT EXISTS drive_minutes SMALLINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS time_fixed    TINYINT(1)        NULL;

ALTER TABLE itineraries
    ADD COLUMN IF NOT EXISTS final_leg_minutes  SMALLINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS return_leg_minutes SMALLINT UNSIGNED NULL;
