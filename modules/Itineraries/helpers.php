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
            SELECT id, itinerary_id, sort_order, location, arrival_time,
                   wait_minutes, internal_notes, client_notes
            FROM itinerary_stops LIMIT 0
        ");
        $pdo->query("
            SELECT id, booking_id, sort_order, location, arrival_time,
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
        SELECT id, sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes
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
 * What is still missing before an itinerary can be confirmed (empty list = ready).
 * The booking form requires these, so a confirmed itinerary can always be converted.
 */
function itineraryConfirmProblems(array $itn): array
{
    $missing = [];
    if (empty($itn['start_time'])) {
        $missing[] = 'start time';
    }
    if (empty($itn['pickup'])) {
        $missing[] = 'pickup location';
    }
    if (empty($itn['destination'])) {
        $missing[] = 'destination';
    }
    return $missing;
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
        INSERT INTO booking_stops (booking_id, sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes)
        SELECT ?, sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes
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
        $stmt = $pdo->prepare("SELECT id, booking_id FROM itineraries WHERE id = ? LIMIT 1");
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
        $pdo->prepare("UPDATE itineraries SET booking_id = ? WHERE id = ?")->execute([$bookingId, $itineraryId]);
        $stops = copyItineraryStopsToBooking($pdo, $itineraryId, $bookingId);
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
            SELECT i.id, i.trip_date, i.pickup, i.destination, c.name AS client_name
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

    // Distance is recalculated (null on failure, never guessed)
    $distanceKm = calculateTripDistanceKm($pickup, $dest);
    if ($distanceKm !== null && !empty($booking['is_round_trip'])) {
        $distanceKm = round($distanceKm * 2, 1);
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

    // Stops: replace the booking's copy with the itinerary's current stops
    $pdo->prepare("DELETE FROM booking_stops WHERE booking_id = ?")->execute([$bookingId]);
    $ins = $pdo->prepare("
        INSERT INTO booking_stops (booking_id, sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($stops as $i => $s) {
        $ins->execute([
            $bookingId, $i + 1, $s['location'], $s['arrival_time'], $s['wait_minutes'],
            $s['internal_notes'], $s['client_notes'],
        ]);
    }

    return $newCost;
}
