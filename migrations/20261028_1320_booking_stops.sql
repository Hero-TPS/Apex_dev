-- migrations/20261028_1320_booking_stops.sql
-- Itinerary step 2: the stops of a booking that came from an itinerary.
-- This is a copy taken when the itinerary is converted (or the booking is updated from it), so the booking keeps
-- its own stops even if the itinerary is reopened and changed. Purely additive: a new table, nothing existing changes.
-- booking_id has no foreign key on purpose (same reason as the other itinerary tables: the bookings.id column type
-- is not defined in the repo). The stops are removed in code when the booking is deleted.

CREATE TABLE IF NOT EXISTS booking_stops (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id     INT NOT NULL,
    sort_order     SMALLINT UNSIGNED NOT NULL,
    location       VARCHAR(255) NOT NULL,
    arrival_time   TIME NULL,
    wait_minutes   SMALLINT UNSIGNED NULL,
    internal_notes TEXT NULL,
    client_notes   TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_stops_order (booking_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
