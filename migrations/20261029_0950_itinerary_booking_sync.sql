-- migrations/20261029_0950_itinerary_booking_sync.sql
-- Itinerary step 3b: tell when an itinerary has changed since its booking was last updated from it.
-- edited_at is set whenever the itinerary is saved. booking_synced_at is set when its booking is created from it
-- or updated from it. An itinerary whose booking still exists is "out of date" when edited_at is later.
-- Purely additive: two new NULLable columns. The older `status` column stays in place but is no longer used:
-- an itinerary is "Booked" while its booking exists and "Planning" otherwise, worked out from the link.

ALTER TABLE itineraries
    ADD COLUMN IF NOT EXISTS edited_at         DATETIME NULL,
    ADD COLUMN IF NOT EXISTS booking_synced_at DATETIME NULL;
