-- migrations/20261025_1340_bookings_table_comment.sql
-- Test migration for the Database Updates runner: sets a description (table comment) on `bookings`.
-- Harmless and safe to run more than once: it only changes the table's comment, not its structure or data.
ALTER TABLE bookings
    COMMENT = 'One row per trip booking. Cancelled bookings are hard-deleted, so there is no cancelled status.';
