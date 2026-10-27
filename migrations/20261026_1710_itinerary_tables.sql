-- migrations/20261026_1710_itinerary_tables.sql
-- Itinerary step 1: two new tables for tour itineraries. Nothing existing is changed.
-- itineraries: one per prebooking (and from step 2 per booking), holds the status.
-- itinerary_stops: the ordered stops of an itinerary, deleted together with it.
-- prebooking_id and booking_id have no foreign key on purpose: their column types are not
-- defined in the repo and a mismatch would make this fail. Cleanup is done in code instead.

CREATE TABLE IF NOT EXISTS itineraries (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prebooking_id INT NULL,
    booking_id    INT NULL,
    status        ENUM('planning', 'confirmed') NOT NULL DEFAULT 'planning',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_itineraries_prebooking (prebooking_id),
    UNIQUE KEY uq_itineraries_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS itinerary_stops (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    itinerary_id   INT UNSIGNED NOT NULL,
    sort_order     SMALLINT UNSIGNED NOT NULL,
    location       VARCHAR(255) NOT NULL,
    arrival_time   TIME NULL,
    wait_minutes   SMALLINT UNSIGNED NULL,
    internal_notes TEXT NULL,
    client_notes   TEXT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_itinerary_stops_order (itinerary_id, sort_order),
    CONSTRAINT fk_itinerary_stops_itinerary
        FOREIGN KEY (itinerary_id) REFERENCES itineraries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
