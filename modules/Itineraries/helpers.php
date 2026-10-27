<?php
// modules/Itineraries/helpers.php
// Data layer for tour itineraries: plain per-table functions, no classes.
// An itinerary belongs to one prebooking (step 1) or one booking (from step 2) and holds
// an ordered list of stops BETWEEN the trip's pickup and destination.

const ITINERARY_MAX_STOPS = 30;
const ITINERARY_NOTES_MAX_LENGTH = 1000;

/**
 * The itinerary row for a prebooking, or null when it has none yet.
 * Itineraries are created lazily on the first save that has stops.
 */
function getItineraryForPrebooking(PDO $pdo, int $prebookingId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM itineraries WHERE prebooking_id = ? LIMIT 1");
    $stmt->execute([$prebookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Stops of an itinerary in trip order, shaped for the editor (times as HH:MM).
 */
function getItineraryStops(PDO $pdo, int $itineraryId): array
{
    $stmt = $pdo->prepare("
        SELECT location, arrival_time, wait_minutes, internal_notes, client_notes
        FROM itinerary_stops
        WHERE itinerary_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$itineraryId]);

    $stops = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $stops[] = [
            'location'       => $row['location'],
            'arrival_time'   => $row['arrival_time'] !== null ? substr($row['arrival_time'], 0, 5) : '',
            'wait_minutes'   => $row['wait_minutes'] !== null ? (int) $row['wait_minutes'] : '',
            'internal_notes' => $row['internal_notes'] ?? '',
            'client_notes'   => $row['client_notes'] ?? '',
        ];
    }
    return $stops;
}

/**
 * Validate and clean stops submitted from the editor.
 * Completely blank rows are dropped. Returns ['stops' => [...], 'error' => null]
 * or ['stops' => [], 'error' => 'message'] on the first problem found.
 */
function normaliseItineraryStops($raw): array
{
    if (!is_array($raw)) {
        return ['stops' => [], 'error' => 'Invalid stops data.'];
    }

    $stops = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            return ['stops' => [], 'error' => 'Invalid stops data.'];
        }

        $location = trim((string) ($item['location'] ?? ''));
        $time     = trim((string) ($item['arrival_time'] ?? ''));
        $wait     = trim((string) ($item['wait_minutes'] ?? ''));
        $internal = trim((string) ($item['internal_notes'] ?? ''));
        $client   = trim((string) ($item['client_notes'] ?? ''));

        if ($location === '' && $time === '' && $wait === '' && $internal === '' && $client === '') {
            continue;
        }

        $number = count($stops) + 1;

        if ($location === '') {
            return ['stops' => [], 'error' => "Stop $number needs a location."];
        }
        if (mb_strlen($location) > 255) {
            return ['stops' => [], 'error' => "Stop $number: location is too long (255 characters max)."];
        }

        $timeVal = null;
        if ($time !== '') {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return ['stops' => [], 'error' => "Stop $number: invalid arrival time."];
            }
            $timeVal = $time . ':00';
        }

        $waitVal = null;
        if ($wait !== '') {
            if (!ctype_digit($wait) || (int) $wait > 1440) {
                return ['stops' => [], 'error' => "Stop $number: wait time must be 0 to 1440 minutes."];
            }
            $waitVal = (int) $wait;
        }

        if (mb_strlen($internal) > ITINERARY_NOTES_MAX_LENGTH || mb_strlen($client) > ITINERARY_NOTES_MAX_LENGTH) {
            return ['stops' => [], 'error' => "Stop $number: notes are too long (" . ITINERARY_NOTES_MAX_LENGTH . " characters max)."];
        }

        $stops[] = [
            'location'       => $location,
            'arrival_time'   => $timeVal,
            'wait_minutes'   => $waitVal,
            'internal_notes' => $internal !== '' ? $internal : null,
            'client_notes'   => $client !== '' ? $client : null,
        ];

        if (count($stops) > ITINERARY_MAX_STOPS) {
            return ['stops' => [], 'error' => 'Too many stops (' . ITINERARY_MAX_STOPS . ' max).'];
        }
    }

    return ['stops' => $stops, 'error' => null];
}

/**
 * Replace the whole stop list of a prebooking's itinerary in one transaction.
 * Creates the itinerary on first use; with no stops and no itinerary, creates nothing.
 * $stops must already have passed normaliseItineraryStops().
 * Returns the itinerary id, or 0 when there is no itinerary.
 *
 * @throws PDOException  Rolled back before rethrowing; the caller logs it.
 */
function saveItineraryStopsForPrebooking(PDO $pdo, int $prebookingId, array $stops): int
{
    $itinerary = getItineraryForPrebooking($pdo, $prebookingId);
    if ($itinerary === null && empty($stops)) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        if ($itinerary === null) {
            $ins = $pdo->prepare("INSERT INTO itineraries (prebooking_id) VALUES (?)");
            $ins->execute([$prebookingId]);
            $itineraryId = (int) $pdo->lastInsertId();
        } else {
            $itineraryId = (int) $itinerary['id'];
        }

        $pdo->prepare("DELETE FROM itinerary_stops WHERE itinerary_id = ?")->execute([$itineraryId]);

        $insStop = $pdo->prepare("
            INSERT INTO itinerary_stops
                (itinerary_id, sort_order, location, arrival_time, wait_minutes, internal_notes, client_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($stops as $i => $stop) {
            $insStop->execute([
                $itineraryId,
                $i + 1,
                $stop['location'],
                $stop['arrival_time'],
                $stop['wait_minutes'],
                $stop['internal_notes'],
                $stop['client_notes'],
            ]);
        }

        // Touch the parent so updated_at reflects stop edits too.
        $pdo->prepare("UPDATE itineraries SET updated_at = NOW() WHERE id = ?")->execute([$itineraryId]);

        $pdo->commit();
        return $itineraryId;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Delete a prebooking's itinerary (its stops go with it via ON DELETE CASCADE).
 * Called when the prebooking itself is deleted; safe when there is no itinerary.
 */
function deleteItineraryForPrebooking(PDO $pdo, int $prebookingId): void
{
    $stmt = $pdo->prepare("DELETE FROM itineraries WHERE prebooking_id = ?");
    $stmt->execute([$prebookingId]);
}
