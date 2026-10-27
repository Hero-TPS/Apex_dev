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
                   passenger_name, passenger_phone, cost, description, status
            FROM itineraries LIMIT 0
        ");
        $pdo->query("
            SELECT id, itinerary_id, sort_order, location, arrival_time,
                   wait_minutes, internal_notes, client_notes
            FROM itinerary_stops LIMIT 0
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
