-- migrations/20261123_1600_gps_log_import.sql
-- GPS log, part 2: the CSV import from GPSLogger (Services -> GPS Import) also keeps where each point came
-- from. provider is "gps" or "network" (cell or wifi, much less exact) and satellites is how many satellites
-- the phone saw, so poor points can be filtered out later.
-- Additive only: two new empty columns on gps_log, nothing existing changes.
-- Run this BEFORE using the GPS Import page, otherwise importing shows an error.
-- Needs 20261121_1800_gps_log.sql to have run first. Safe to run more than once.
ALTER TABLE gps_log
    ADD COLUMN IF NOT EXISTS provider VARCHAR(16) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS satellites TINYINT UNSIGNED NULL DEFAULT NULL;
