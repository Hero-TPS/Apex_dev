<!-- ITINERARY_CHANGES.md -->
# Itinerary Branch — What To Do When Merging To Main

Kept for the merge of `feature/itinerary` into `main`. It lists everything that is **not just code**: the database
changes that must run on dev and live, and the manual changes (system variables, one-off data clean-ups, WhatsApp
message texts) that do not travel with the code.

Add a line whenever a new migration, system variable, data clean-up or WhatsApp placeholder is delivered.
Which migrations have run on a server is recorded by that server's `schema_migrations` table
(Services → Database Updates), not here.

## Merge checklist (do on dev first, then live)

1. [ ] Merge the code.
2. [ ] Services → Database Updates: run the pending migrations (section 1, in file-name order).
3. [ ] Services → System Variables: check the four itinerary variables (section 2); defaults apply until saved.
4. [ ] Services → Address Check: run the duplicate merge and the Google check on that server's destinations and clients (section 3).
5. [ ] Services → WhatsApp Messages: add `{stops}` to the client message(s) and `{stops_driver}` to the driver message (section 4).
6. [ ] Check that the Google Maps browser key allows that server's domain (address suggestions).

---

## 1. Migrations (in `migrations/`)

Everything on this branch is additive: new tables and new NULLable / defaulted columns. Nothing existing is renamed,
dropped or redefined.

| File | What it adds |
|---|---|
| `20261026_1710_itinerary_tables.sql` | New tables `itineraries` (id, prebooking_id, booking_id, status, timestamps) and `itinerary_stops` (sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes; deleted with its itinerary). |
| `20261027_2040_itinerary_standalone_columns.sql` | `itineraries`: contact_id, trip_date, start_time, pickup, destination, passenger_name, passenger_phone, cost, description, plus indexes on trip_date and contact_id. `prebooking_id` is left in place and unused. |
| `20261028_1320_booking_stops.sql` | New table `booking_stops`: the booking's own copy of the stops, taken on convert / Update Booking. Removed in code when the booking is deleted. |
| `20261029_0440_itinerary_distances.sql` | `itinerary_stops`: place_id, distance_km. `itineraries`: pickup_place_id, destination_place_id, return_to_pickup, final_leg_km, return_leg_km, total_km, unknown_legs, distance_calculated_at. `booking_stops`: distance_km. |
| `20261029_0950_itinerary_booking_sync.sql` | `itineraries`: edited_at, booking_synced_at (drives the "changed since the booking was updated" marker). The old `status` column is no longer used; Planning / Booked comes from the booking link. |
| `20261030_1600_address_checking_columns.sql` | `destinations`: place_id, latitude, longitude, verified_at. `contacts`: address_place_id, address_verified_at. The client GPS pin stays in the existing `pickup_lat` / `pickup_lng`. |
| `20261101_1600_itinerary_times.sql` | `itinerary_stops`: drive_minutes, time_fixed. `itineraries`: final_leg_minutes, return_leg_minutes. |

No migration was needed for: the booking flag and cost reference, the calendar changes, the WhatsApp stops
placeholders, or the printable page.

### Tables the itinerary code relies on
`itineraries`, `itinerary_stops`, `booking_stops`, plus the new columns on `destinations` and `contacts` above.
Pages show a "run the pending updates" notice when something is missing.

---

## 2. System variables (Services → System Variables)

Defined in code (`SYSTEM_VARIABLES` in `includes/helpers.php`), so no migration. A value is only stored in the
database once it is saved on that page; until then the default applies.

| Variable | Default | Used for |
|---|---|---|
| `rate_per_km` | 15.00 | Reference cost on itineraries and bookings (display only). Existing variable. |
| `distance_warning_km` | 200 | Warning when one itinerary leg is longer than this. |
| `village_match_text` | Helderberg Village | Client address text that means "use the estate gate" when there is no GPS pin. |
| `village_gate_address` | 59 Bakkerskloof Rd, Helderview, Somerset West | Gate address used for distances only. |

---

## 3. One-off data changes (not migrations)

| What | Where | Notes |
|---|---|---|
| Merge duplicate preset destinations | Services → Address Check | Changes rows in `destinations` on whichever server it is run on. Run it again on dev / live after the merge, it is not carried by code. |
| Mark addresses checked / save a destination location | Services → Address Check | Writes the new place_id / latitude / longitude / verified_at columns. |

---

## 4. WhatsApp messages

Message texts live in the database (Services → WhatsApp Messages), one set per server. The code only supplies the
placeholders. After the merge, add the placeholders below to the templates on dev and live by hand.

### New placeholders (booking messages, incl. the driver message)

| Placeholder | Value |
|---|---|
| `{stops}` | Stops list for the client. Empty when the booking has no itinerary, so a line holding only `{stops}` drops out. |
| `{stops_driver}` | Same list with a Waze link under each stop. Kept separate so the links can be dropped by editing the driver template only (drivers dislike Waze links, so this is a trial). |

Format:

```
🗺️ Stops
1. 09:30 Checkers Somerset Mall (wait 0:30) - client note
   🧭 https://waze.com/ul?q=...        <- {stops_driver} only
2. 11:15 Dr Smith
```

- Afrikaans bookings get "Stoppe" and "wag".
- Client notes only. Internal notes are admin only and never go into a message (to be looked at again).
- Source of the stops: the booking's own copy in `booking_stops`.

### Combined message (several bookings made around the same time)
Stops are added automatically under each trip that has an itinerary. No template change is needed.

### Templates still to edit by hand
Add `{stops}` to the client booking message(s) and `{stops_driver}` to the driver message. Until then nothing changes in
the sent text. Tick off per server:

- [ ] Itinerary server: client template(s) / driver template
- [ ] Dev (after merge)
- [ ] Live (after merge)

### Planned
- "Changed since last sent" reminder when a Booked itinerary changes after its message was sent.
- Internal notes in messages (decide later).
