-- migrations/20261125_2200_gps_import_files.sql
-- GPS log, part 3: remembers each CSV file the GPS import has read, with its size and modified time at
-- that moment. This lets the import run by itself whenever a GPS page is opened and only read files that
-- are new or have changed. last_error is filled when a file could not be used and is shown on Services -> GPS Import.
-- Additive only: one new table. Safe to run more than once.
-- Run this BEFORE deploying the new GPS Import page, otherwise the page shows an error.
CREATE TABLE IF NOT EXISTS gps_import_files (
    filename    VARCHAR(190) NOT NULL PRIMARY KEY,
    file_size   BIGINT UNSIGNED NOT NULL,
    file_mtime  INT UNSIGNED NOT NULL,
    row_count   INT UNSIGNED NOT NULL DEFAULT 0,
    new_points  INT UNSIGNED NOT NULL DEFAULT 0,
    last_error  VARCHAR(255) NULL DEFAULT NULL,
    imported_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
