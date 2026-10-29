-- migrations/20261029_0440_itinerary_distances.sql
-- Itinerary step 3a: distance per leg, total km, return-to-pickup option and Google place ids.
-- Purely additive: only new columns on existing tables, nothing is renamed, dropped or redefined.
-- A "leg" is one stretch of the route: pickup -> stop 1 -> ... -> stop N -> destination (-> pickup when returning).
-- itinerary_stops.distance_km holds the leg that ARRIVES at that stop. The leg to the destination and the optional
-- leg back to the pickup are held on the itinerary itself. unknown_legs counts legs that have no distance
-- (address not found, or pickup/destination still blank), so a total is only trusted when it is 0.
-- place_id columns hold the exact Google place picked from the address suggestions (NULL when typed or picked
-- from the saved destinations list).

ALTER TABLE itinerary_stops
    ADD COLUMN IF NOT EXISTS place_id    VARCHAR(255) NULL AFTER location,
    ADD COLUMN IF NOT EXISTS distance_km DECIMAL(7,1) NULL AFTER place_id;

ALTER TABLE itineraries
    ADD COLUMN IF NOT EXISTS pickup_place_id        VARCHAR(255) NULL AFTER pickup,
    ADD COLUMN IF NOT EXISTS destination_place_id   VARCHAR(255) NULL AFTER destination,
    ADD COLUMN IF NOT EXISTS return_to_pickup       TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS final_leg_km           DECIMAL(7,1) NULL,
    ADD COLUMN IF NOT EXISTS return_leg_km          DECIMAL(7,1) NULL,
    ADD COLUMN IF NOT EXISTS total_km               DECIMAL(8,1) NULL,
    ADD COLUMN IF NOT EXISTS unknown_legs           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS distance_calculated_at DATETIME NULL;

ALTER TABLE booking_stops
    ADD COLUMN IF NOT EXISTS distance_km DECIMAL(7,1) NULL AFTER location;
