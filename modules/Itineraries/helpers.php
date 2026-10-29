<?php
// modules/Itineraries/helpers.php

/**
 * True when the itinerary tables have every column this module needs.
 * Used by: all pages and the API in modules/Itineraries/
 */
function itineraryMigrationReady(PDO $pdo): bool
{
    try {
        $pdo->query("
            SELECT id, contact_id, trip_date, start_time, pickup, destination,
                   passenger_name, passenger_phone, cost, description, status, booking_id
            FROM itineraries LIMIT 0
        ");
        $pdo->query("
            SELECT pickup_place_id, destination_place_id, return_to_pickup, final_leg_km, return_leg_km,
                   total_km, unknown_legs, distance_calculated_at, edited_at, booking_synced_at
            FROM itineraries LIMIT 0
        ");
        $pdo->query("
            SELECT id, itinerary_id, sort_order, location, place_id, distance_km, arrival_time,
                   wait_minutes, internal_notes, client_notes
            FROM itinerary_stops LIMIT 0
        ");
        $pdo->query("
            SELECT id, booking_id, sort_order, location, distance_km, arrival_time,
                   wait_minutes, internal_notes, client_notes
            FROM booking_stops LIMIT 0
        ");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Error box shown when the itinerary migrations have not been run on this database.
 */
function itineraryMigrationNotice(): string
{
    return '<div class="error-message">The itinerary tables are not up to date on this database. '
        . 'Run the pending updates in <a href="' . BASE_URL . '/services/updates.php">Services → Database Updates</a> first.</div>';
}

/**
 * Fetch one itinerary with its client name, or null when it does not exist.
 */
function fetchItinerary(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT i.*, c.name AS client_name, c.phone AS client_phone
        FROM itineraries i
        JOIN contacts c ON i.contact_id = c.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Fetch the stops of an itinerary in display order.
 */
function fetchItineraryStops(PDO $pdo, int $itineraryId): array
{
    $stmt = $pdo->prepare("
        SELECT id, sort_order, location, place_id, distance_km, arrival_time, wait_minutes, internal_notes, client_notes
        FROM itinerary_stops
        WHERE itinerary_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$itineraryId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The booking an itinerary is linked to, or null when it is not linked or that booking no longer exists
 * (bookings are hard-deleted, so a stale booking_id counts as "not linked").
 */
function fetchLinkedBooking(PDO $pdo, ?int $bookingId): ?array
{
    if (empty($bookingId)) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT b.id, b.trip_date, b.start_time, b.end_time, b.cost, b.driver_id, b.no_booking_fee,
               b.after_hours_charge, b.is_round_trip, b.google_calendar_event_id, c.name AS client_name
        FROM bookings b
        JOIN contacts c ON b.contact_id = c.id
        WHERE b.id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * "Booked" while the booking made from the itinerary still exists, otherwise "Planning".
 * Worked out from the link, never stored, so it cannot drift when a booking is deleted.
 */
function itineraryStatusLabel(?array $linkedBooking): string
{
    return $linkedBooking ? 'Booked' : 'Planning';
}

/**
 * True when the itinerary was saved after its booking was last created or updated from it, i.e. the booking
 * no longer matches the plan. Only meaningful when the booking still exists.
 */
function itineraryBookingOutOfDate(array $itn, ?array $linkedBooking): bool
{
    if (!$linkedBooking || empty($itn['edited_at'])) {
        return false;
    }
    if (empty($itn['booking_synced_at'])) {
        return true;
    }
    return strtotime($itn['edited_at']) > strtotime($itn['booking_synced_at']);
}

/**
 * URL of the booking form prefilled from an itinerary (same idea as converting a prebooking).
 * The booking is only created when that form is saved; the stops are copied at that moment.
 */
function itineraryConvertUrl(array $itn): string
{
    $params = http_build_query(array_filter([
        'contact_id'      => $itn['contact_id'],
        'contact_name'    => $itn['client_name'] ?? '',
        'trip_date'       => $itn['trip_date'],
        'start_time'      => !empty($itn['start_time']) ? substr($itn['start_time'], 0, 5) : '',
        'pickup'          => $itn['pickup'] ?? '',
        'destination'     => $itn['destination'] ?? '',
        'cost'            => $itn['cost'] ?? '',
        'description'     => $itn['description'] ?? '',
        'passenger_name'  => $itn['passenger_name'] ?? '',
        'passenger_phone' => $itn['passenger_phone'] ?? '',
        'from_itinerary'  => $itn['id'],
    ], fn($v) => $v !== '' && $v !== null));

    return BASE_URL . '/modules/Bookings/add.php?' . $params;
}

// ========== BOOKING SIDE ==========
// Used by modules/Bookings/api/index.php, which also runs on databases where the itinerary tables do not exist yet,
// so these functions never throw: they log and carry on.

/**
 * Replace the stops of a booking with a copy of an itinerary's stops.
 */
function copyItineraryStopsToBooking(PDO $pdo, int $itineraryId, int $bookingId): int
{
    $pdo->prepare("DELETE FROM booking_stops WHERE booking_id = ?")->execute([$bookingId]);

    $ins = $pdo->prepare("
        INSERT INTO booking_stops (booking_id, sort_order, location, distance_km, arrival_time, wait_minutes, internal_notes, client_notes)
        SELECT ?, sort_order, location, distance_km, arrival_time, wait_minutes, internal_notes, client_notes
        FROM itinerary_stops
        WHERE itinerary_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $ins->execute([$bookingId, $itineraryId]);
    return $ins->rowCount();
}

/**
 * First conversion: link an itinerary to the booking just created from it and copy its stops across.
 * Does nothing if the itinerary is gone or is already linked to a booking that still exists.
 */
function linkItineraryToBooking(PDO $pdo, int $itineraryId, int $bookingId): bool
{
    try {
        $stmt = $pdo->prepare("SELECT id, booking_id, total_km, unknown_legs FROM itineraries WHERE id = ? LIMIT 1");
        $stmt->execute([$itineraryId]);
        $itn = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$itn) {
            return false;
        }
        if (!empty($itn['booking_id']) && (int)$itn['booking_id'] !== $bookingId
            && fetchLinkedBooking($pdo, (int)$itn['booking_id'])) {
            logWarning('ITINERARY', 'Itinerary already linked to another booking, link not changed', [
                'itinerary_id' => $itineraryId,
                'booking_id'   => $bookingId,
                'linked_to'    => (int)$itn['booking_id'],
            ]);
            return false;
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE itineraries SET booking_id = ?, booking_synced_at = NOW() WHERE id = ?")->execute([$bookingId, $itineraryId]);
        $stops = copyItineraryStopsToBooking($pdo, $itineraryId, $bookingId);

        // The booking's distance is the whole route, not the straight pickup-to-destination figure,
        // but only when every leg is known (otherwise the booking keeps the distance it calculated itself)
        $knownKm = itineraryKnownTotalKm($itn);
        if ($knownKm !== null) {
            $pdo->prepare("UPDATE bookings SET distance_km = ? WHERE id = ?")->execute([$knownKm, $bookingId]);
        }
        $pdo->commit();

        logInfo('ITINERARY', 'Itinerary converted to booking', [
            'itinerary_id' => $itineraryId,
            'booking_id'   => $bookingId,
            'stops'        => $stops,
        ]);
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError('ITINERARY', 'Failed to link itinerary to booking', [
            'error'        => $e->getMessage(),
            'itinerary_id' => $itineraryId,
            'booking_id'   => $bookingId,
        ]);
        return false;
    }
}

/**
 * The itinerary linked to a booking, or null (also null when the itinerary tables do not exist).
 */
function fetchItineraryForBooking(PDO $pdo, int $bookingId): ?array
{
    try {
        $stmt = $pdo->prepare("
            SELECT i.id, i.trip_date, i.pickup, i.destination, i.total_km, i.unknown_legs, c.name AS client_name
            FROM itineraries i
            JOIN contacts c ON i.contact_id = c.id
            WHERE i.booking_id = ?
            LIMIT 1
        ");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Tidy up after a booking has been deleted: remove its stop copy, then either delete the linked itinerary
 * (with its stops) or leave it in place as "not linked" so it can be converted again.
 */
function releaseItineraryForBooking(PDO $pdo, int $bookingId, bool $deleteItinerary): void
{
    try {
        $pdo->prepare("DELETE FROM booking_stops WHERE booking_id = ?")->execute([$bookingId]);

        $stmt = $pdo->prepare("SELECT id FROM itineraries WHERE booking_id = ? LIMIT 1");
        $stmt->execute([$bookingId]);
        $itineraryId = (int) $stmt->fetchColumn();
        if ($itineraryId <= 0) {
            return;
        }

        if ($deleteItinerary) {
            $pdo->prepare("DELETE FROM itinerary_stops WHERE itinerary_id = ?")->execute([$itineraryId]);
            $pdo->prepare("DELETE FROM itineraries WHERE id = ?")->execute([$itineraryId]);
            logInfo('ITINERARY', 'Itinerary deleted together with its booking', [
                'itinerary_id' => $itineraryId,
                'booking_id'   => $bookingId,
            ]);
        } else {
            $pdo->prepare("UPDATE itineraries SET booking_id = NULL WHERE id = ?")->execute([$itineraryId]);
            logInfo('ITINERARY', 'Booking deleted, itinerary kept and unlinked', [
                'itinerary_id' => $itineraryId,
                'booking_id'   => $bookingId,
            ]);
        }
    } catch (PDOException $e) {
        // The itinerary tables may not exist on this database yet: nothing to tidy up then.
        if (stripos($e->getMessage(), "doesn't exist") === false) {
            logError('ITINERARY', 'Failed to tidy up itinerary after booking delete', [
                'error'      => $e->getMessage(),
                'booking_id' => $bookingId,
            ]);
        }
    }
}

/**
 * Re-convert: apply an itinerary's trip details and stops to the booking it is already linked to.
 * Only trip details change. Driver, booking fee choice, payment, flight number, language and the round-trip flag are
 * never touched. The booking keeps its own duration. The Google Calendar event is NOT updated (planned for step 4).
 * Call inside a transaction. Returns the new cost.
 */
function applyItineraryToBooking(PDO $pdo, array $itn, array $booking, array $stops): float
{
    $bookingId = (int) $booking['id'];

    // Keep the booking's own duration (the itinerary has none)
    $oldStart = strtotime('1970-01-01 ' . $booking['start_time']);
    $oldEnd   = strtotime('1970-01-01 ' . $booking['end_time']);
    $seconds  = $oldEnd - $oldStart;
    if ($seconds <= 0) {
        $seconds += 86400;
    }

    $tripDate  = $itn['trip_date'];
    $startTime = !empty($itn['start_time']) ? $itn['start_time'] : $booking['start_time'];
    $pickup    = !empty($itn['pickup']) ? $itn['pickup'] : null;
    $dest      = !empty($itn['destination']) ? $itn['destination'] : null;
    if ($pickup === null || $dest === null) {
        throw new RuntimeException('The itinerary needs a pickup and a destination before the booking can be updated.');
    }

    $startObj = new DateTime($tripDate . ' ' . $startTime, new DateTimeZone(TIME_ZONE));
    $endObj   = (clone $startObj)->modify('+' . (int) $seconds . ' seconds');
    $endTime  = $endObj->format('H:i:s');

    // Cost: the itinerary's manual cost, plus the booking's after-hours surcharge if it had one and still applies
    $baseCost = !empty($itn['cost']) ? (float) $itn['cost'] : null;
    $afterHoursCharge = null;
    if ($baseCost === null) {
        $newCost = (float) $booking['cost'];
        $afterHoursCharge = $booking['after_hours_charge'] !== null ? (float) $booking['after_hours_charge'] : null;
    } else {
        $newCost = $baseCost;
        if ($booking['after_hours_charge'] !== null && isAfterHoursStartTime($pdo, $startObj->format('H:i'))) {
            $afterHoursCharge = (float) $booking['after_hours_charge'];
            $newCost += $afterHoursCharge;
        }
    }

    // Booking fee only exists when a driver is allocated and the fee is not waived
    $bookingFee = null;
    if (!empty($booking['driver_id']) && empty($booking['no_booking_fee'])) {
        $fee = calculateBookingFee($newCost, (float) getSystemVariable($pdo, 'apex_booking_fee_pct'));
        if ($fee > 0) {
            $bookingFee = $fee;
        }
    }

    // Pickup is "custom" when it is not one of the saved destinations
    $chk = $pdo->prepare("SELECT 1 FROM destinations WHERE name = ? LIMIT 1");
    $chk->execute([$pickup]);
    $pickupIsCustom = $chk->fetchColumn() ? 0 : 1;

    // Distance: the itinerary's whole-route total when every leg is known (it already includes any return leg).
    // Otherwise recalculated pickup to destination (null on failure, never guessed).
    $distanceKm = itineraryKnownTotalKm($itn);
    if ($distanceKm === null) {
        $distanceKm = calculateTripDistanceKm($pickup, $dest);
        if ($distanceKm !== null && !empty($booking['is_round_trip'])) {
            $distanceKm = round($distanceKm * 2, 1);
        }
    }

    $upd = $pdo->prepare("
        UPDATE bookings SET
            trip_date = ?, start_time = ?, end_time = ?,
            original_pickup = ?, original_destination = ?, was_swapped = 0,
            cost = ?, description = ?, passenger_name = ?, passenger_phone = ?,
            booking_fee = ?, pickup_is_custom = ?, distance_km = ?, after_hours_charge = ?,
            last_confirmed_at = NULL, updated_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([
        $tripDate, $startTime, $endTime,
        $pickup, $dest,
        $newCost, $itn['description'] ?? '', $itn['passenger_name'] ?: null, $itn['passenger_phone'] ?: null,
        $bookingFee, $pickupIsCustom, $distanceKm, $afterHoursCharge,
        $bookingId,
    ]);

    // The booking now matches the itinerary
    $pdo->prepare("UPDATE itineraries SET booking_synced_at = NOW() WHERE id = ?")->execute([(int) $itn['id']]);

    // Stops: replace the booking's copy with the itinerary's current stops
    $pdo->prepare("DELETE FROM booking_stops WHERE booking_id = ?")->execute([$bookingId]);
    $ins = $pdo->prepare("
        INSERT INTO booking_stops (booking_id, sort_order, location, distance_km, arrival_time, wait_minutes, internal_notes, client_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($stops as $i => $s) {
        $ins->execute([
            $bookingId, $i + 1, $s['location'], $s['distance_km'] ?? null, $s['arrival_time'], $s['wait_minutes'],
            $s['internal_notes'], $s['client_notes'],
        ]);
    }

    return $newCost;
}


// ========== DISTANCES (step 3a) ==========

/**
 * The itinerary's total km, but only when every leg has a distance. Null otherwise: a partial total
 * must never be handed to a booking as if it were the real route length.
 */
function itineraryKnownTotalKm(array $itn): ?float
{
    if (!isset($itn['total_km']) || $itn['total_km'] === null) {
        return null;
    }
    if ((int) ($itn['unknown_legs'] ?? 0) > 0) {
        return null;
    }
    return (float) $itn['total_km'];
}

/**
 * The route as an ordered list of points: pickup, each stop, destination and, when asked, the pickup again.
 * Each point: text, place_id (or null), kind ('pickup'|'stop'|'destination'|'return'), stop_index (stops only).
 */
function buildItineraryPoints(?string $pickup, ?string $pickupPlaceId, array $stops, ?string $destination, ?string $destinationPlaceId, bool $returnToPickup): array
{
    $points = [['text' => trim((string) $pickup), 'place_id' => $pickupPlaceId ?: null, 'kind' => 'pickup', 'stop_index' => null]];
    foreach (array_values($stops) as $i => $s) {
        $points[] = [
            'text'       => trim((string) ($s['location'] ?? '')),
            'place_id'   => !empty($s['place_id']) ? $s['place_id'] : null,
            'kind'       => 'stop',
            'stop_index' => $i,
        ];
    }
    $points[] = ['text' => trim((string) $destination), 'place_id' => $destinationPlaceId ?: null, 'kind' => 'destination', 'stop_index' => null];
    if ($returnToPickup && trim((string) $pickup) !== '') {
        $points[] = ['text' => trim((string) $pickup), 'place_id' => $pickupPlaceId ?: null, 'kind' => 'return', 'stop_index' => null];
    }
    return $points;
}

/**
 * Plain-language text for one leg, shown inline under the field it belongs to.
 * level: 'ok' (distance known, within the limit), 'warn' (over the limit), 'fail' (lookup failed),
 *        'info' (nothing to report yet, e.g. an end of the leg is still blank).
 */
function itineraryLegMessage(string $kind, array $leg, float $limit): array
{
    $km     = $leg['km'];
    $status = $leg['status'];
    $from   = $leg['from'];
    $to     = $leg['to'];

    if ($status === 'ok' && $km !== null) {
        $kmText = number_format($km, 1);
        $labels = ['destination' => 'to the destination', 'return' => 'back to the pickup', 'trip' => 'one way'];
        $label  = $labels[$kind] ?? 'from the previous point';
        if ($km > $limit) {
            $text = "⚠️ $kmText km $label is over $limit km. Check the address.";
            if (!empty($leg['from_address']) || !empty($leg['to_address'])) {
                $text .= ' Google read the route as: ' . ($leg['from_address'] ?: $from) . ' → ' . ($leg['to_address'] ?: $to) . '.';
            }
            return ['level' => 'warn', 'message' => $text];
        }
        return ['level' => 'ok', 'message' => "📏 $kmText km $label"];
    }

    if ($status === 'not_found') {
        if (empty($leg['origin_found']) && empty($leg['dest_found'])) {
            return ['level' => 'fail', 'message' => "❌ Google couldn't find “{$from}” or “{$to}”. Check the addresses."];
        }
        $bad = empty($leg['origin_found']) ? $from : $to;
        return ['level' => 'fail', 'message' => "❌ Google couldn't find “{$bad}”. Check the address."];
    }
    if ($status === 'no_route') {
        return ['level' => 'fail', 'message' => "❌ No driving route found between “{$from}” and “{$to}”. Check the addresses."];
    }
    if ($status === 'error') {
        return ['level' => 'fail', 'message' => '❌ The distance lookup failed (network or Google error). Try Recalculate.'];
    }
    if ($status === 'missing') {
        return ['level' => 'info', 'message' => 'Add both ends of this leg to calculate its distance.'];
    }
    return ['level' => 'info', 'message' => 'Distance not calculated yet.'];
}

/**
 * Look up every leg of a route. Gives up on the remaining legs after $budgetSeconds so a slow Google
 * cannot run the page into the host's time limit; those legs are reported as failed lookups.
 * Returns the payload shared by the API, the edit page and the browser script (see itineraryDistancePayload()).
 */
function calculateItineraryLegs(PDO $pdo, array $points, int $budgetSeconds = 20): array
{
    $limit    = getDistanceWarningKm($pdo);
    $started  = microtime(true);
    $legs     = [];

    for ($i = 1; $i < count($points); $i++) {
        $a = $points[$i - 1];
        $b = $points[$i];

        if ((microtime(true) - $started) > $budgetSeconds) {
            $res = ['status' => 'error', 'km' => null, 'origin_found' => true, 'dest_found' => true, 'from_address' => '', 'to_address' => ''];
        } else {
            $res = calculateLegDistance($a['text'], $b['text'], $a['place_id'], $b['place_id']);
        }

        $leg = [
            'kind'         => $b['kind'],
            'stop_index'   => $b['stop_index'],
            'from'         => $a['text'],
            'to'           => $b['text'],
            'km'           => $res['km'],
            'status'       => $res['status'],
            'origin_found' => $res['origin_found'],
            'dest_found'   => $res['dest_found'],
            'from_address' => $res['from_address'],
            'to_address'   => $res['to_address'],
        ];
        $leg += itineraryLegMessage($b['kind'], $leg, $limit);
        $legs[] = $leg;
    }

    return itineraryDistancePayload($legs, $limit, count(array_filter($points, fn($p) => $p['kind'] === 'return')) > 0);
}

/**
 * Wrap a list of legs with the totals the page needs.
 */
function itineraryDistancePayload(array $legs, float $limit, bool $returnToPickup): array
{
    $total    = 0.0;
    $known    = 0;
    $unknown  = 0;
    $problems = false;
    foreach ($legs as $leg) {
        if ($leg['km'] !== null) {
            $total += (float) $leg['km'];
            $known++;
        } else {
            $unknown++;
        }
        if ($leg['level'] === 'warn' || $leg['level'] === 'fail') {
            $problems = true;
        }
    }
    return [
        'legs'             => $legs,
        'total_km'         => $known > 0 ? round($total, 1) : null,
        'unknown_legs'     => $unknown,
        'limit'            => $limit,
        'problems'         => $problems,
        'return_to_pickup' => $returnToPickup,
    ];
}

/**
 * Save the result of calculateItineraryLegs() on the stops and the itinerary.
 * $stopIds is the list of stop ids in route order.
 */
function storeItineraryDistances(PDO $pdo, int $itineraryId, array $stopIds, array $payload): void
{
    $final  = null;
    $return = null;
    $updStop = $pdo->prepare("UPDATE itinerary_stops SET distance_km = ? WHERE id = ?");
    foreach ($payload['legs'] as $leg) {
        if ($leg['kind'] === 'stop' && isset($stopIds[$leg['stop_index']])) {
            $updStop->execute([$leg['km'], $stopIds[$leg['stop_index']]]);
        } elseif ($leg['kind'] === 'destination') {
            $final = $leg['km'];
        } elseif ($leg['kind'] === 'return') {
            $return = $leg['km'];
        }
    }

    $pdo->prepare("
        UPDATE itineraries
        SET final_leg_km = ?, return_leg_km = ?, total_km = ?, unknown_legs = ?, distance_calculated_at = NOW()
        WHERE id = ?
    ")->execute([$final, $return, $payload['total_km'], $payload['unknown_legs'], $itineraryId]);
}

/**
 * Recalculate and store the distances of a saved itinerary. Returns the payload, or null if it was not found.
 * Never throws for a failed lookup: failures are part of the payload.
 */
function computeAndStoreItineraryDistances(PDO $pdo, int $itineraryId): ?array
{
    $itn = fetchItinerary($pdo, $itineraryId);
    if (!$itn) {
        return null;
    }
    $stops  = fetchItineraryStops($pdo, $itineraryId);
    $points = buildItineraryPoints(
        $itn['pickup'], $itn['pickup_place_id'], $stops,
        $itn['destination'], $itn['destination_place_id'], !empty($itn['return_to_pickup'])
    );
    $payload = calculateItineraryLegs($pdo, $points);
    storeItineraryDistances($pdo, $itineraryId, array_column($stops, 'id'), $payload);

    logInfo('ITINERARY', 'Itinerary distances calculated', [
        'itinerary_id' => $itineraryId,
        'total_km'     => $payload['total_km'],
        'unknown_legs' => $payload['unknown_legs'],
        'problems'     => $payload['problems'],
    ]);
    return $payload;
}

/**
 * The same payload built from what is already stored (no Google call), used to draw the edit page.
 */
function itineraryStoredDistancePayload(PDO $pdo, array $itn, array $stops): array
{
    $limit  = getDistanceWarningKm($pdo);
    $points = buildItineraryPoints(
        $itn['pickup'], $itn['pickup_place_id'] ?? null, $stops,
        $itn['destination'], $itn['destination_place_id'] ?? null, !empty($itn['return_to_pickup'])
    );

    $storedKm = [];
    foreach (array_values($stops) as $i => $s) {
        $storedKm['stop' . $i] = $s['distance_km'] !== null ? (float) $s['distance_km'] : null;
    }
    $storedKm['destination'] = $itn['final_leg_km'] !== null ? (float) $itn['final_leg_km'] : null;
    $storedKm['return']      = $itn['return_leg_km'] !== null ? (float) $itn['return_leg_km'] : null;

    $legs = [];
    for ($i = 1; $i < count($points); $i++) {
        $a = $points[$i - 1];
        $b = $points[$i];
        $key = $b['kind'] === 'stop' ? 'stop' . $b['stop_index'] : $b['kind'];
        $km  = $storedKm[$key] ?? null;

        if ($a['text'] === '' || $b['text'] === '') {
            $status = 'missing';
        } else {
            $status = $km !== null ? 'ok' : 'unknown';
        }
        $leg = [
            'kind' => $b['kind'], 'stop_index' => $b['stop_index'], 'from' => $a['text'], 'to' => $b['text'],
            'km' => $km, 'status' => $status, 'origin_found' => true, 'dest_found' => true,
            'from_address' => '', 'to_address' => '',
        ];
        $leg += itineraryLegMessage($b['kind'], $leg, $limit);
        $legs[] = $leg;
    }
    return itineraryDistancePayload($legs, $limit, !empty($itn['return_to_pickup']));
}
