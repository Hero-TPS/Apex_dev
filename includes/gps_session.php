<?php
// includes/gps_session.php
// GPS logging sessions: the button presses made on services/gps_sessions.php while driving is being monitored.
// A session runs from "Start logging" to "End logging". Each press is stored as an event with the server time.
// A job has three steps, in this order: go to pickup, pickup, drop off. Each job is for Uber or for Apex.
// When a button is skipped or another job is started, the missing steps are filled in as "auto" events at the same
// moment as the press that caused them, so every job in the report has a start, a pickup and a drop off.
// Nothing here reads or changes other modules. The GPS points stay in gps_log and are matched by time later.
// Used by: services/gps_sessions.php, services/api/gps_sessions.php, includes/header.php (banner)

/** Seconds within which the same button pressed again is taken as an accidental double tap */
const GPS_SESSION_DUPLICATE_SECONDS = 5;

/** Job step number to the first part of the event type */
const GPS_SESSION_STEPS = [1 => 'go', 2 => 'pickup', 3 => 'drop'];

/** Job code to its display name */
const GPS_SESSION_JOBS = ['uber' => 'Uber', 'apex' => 'Apex'];

/**
 * Split an event type such as "pickup_uber" into ['job' => 'uber', 'step' => 2].
 * Returns null for start, end and anything unknown.
 */
function gpsSessionParseType(string $type): ?array
{
    $parts = explode('_', $type);
    if (count($parts) !== 2 || !isset(GPS_SESSION_JOBS[$parts[1]])) {
        return null;
    }
    $step = array_search($parts[0], GPS_SESSION_STEPS, true);
    if ($step === false) {
        return null;
    }
    return ['job' => $parts[1], 'step' => (int) $step];
}

/** Display text for an event type */
function gpsSessionLabel(string $type): string
{
    if ($type === 'start') {
        return 'Start logging';
    }
    if ($type === 'end') {
        return 'End logging';
    }
    $parsed = gpsSessionParseType($type);
    if ($parsed === null) {
        return $type;
    }
    $job = GPS_SESSION_JOBS[$parsed['job']];
    return [1 => "Go to $job pickup", 2 => "$job pickup", 3 => "$job drop off"][$parsed['step']];
}

/** The session that is being logged now, or null */
function gpsSessionOpen(PDO $pdo): ?array
{
    $row = $pdo->query("SELECT * FROM gps_sessions WHERE ended_at IS NULL ORDER BY id DESC LIMIT 1")->fetch();
    return $row ?: null;
}

/** Every event of a session, in the order they were made */
function gpsSessionEvents(PDO $pdo, int $sessionId): array
{
    $stmt = $pdo->prepare("SELECT * FROM gps_events WHERE session_id = ? ORDER BY id");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

/**
 * The job that is still running after the given events, as ['job' => ..., 'step' => 1 or 2], or null.
 * A job is running from its "go to pickup" or "pickup" event until a drop off, start or end comes after it.
 */
function gpsSessionRunningJob(array $events): ?array
{
    $running = null;
    foreach ($events as $event) {
        $parsed = gpsSessionParseType($event['event_type']);
        if ($parsed === null || $parsed['step'] === 3) {
            $running = null;
        } else {
            $running = $parsed;
        }
    }
    return $running;
}

/**
 * Insert the auto events first and the pressed event last, then link the auto events to the pressed one.
 * Returns the id of the pressed event.
 */
function gpsSessionInsertGroup(PDO $pdo, int $sessionId, array $autoTypes, string $type, string $when): int
{
    $insert = $pdo->prepare(
        "INSERT INTO gps_events (session_id, event_type, occurred_at, source) VALUES (?, ?, ?, ?)"
    );
    $autoIds = [];
    foreach ($autoTypes as $autoType) {
        $insert->execute([$sessionId, $autoType, $when, 'auto']);
        $autoIds[] = (int) $pdo->lastInsertId();
    }
    $insert->execute([$sessionId, $type, $when, 'press']);
    $eventId = (int) $pdo->lastInsertId();

    if ($autoIds) {
        $link = $pdo->prepare("UPDATE gps_events SET auto_for = ? WHERE id = ?");
        foreach ($autoIds as $autoId) {
            $link->execute([$eventId, $autoId]);
        }
    }
    return $eventId;
}

/**
 * A button was pressed. Returns ['ok' => bool, 'message' => string, 'event_id' => ?int].
 * event_id is the pressed event, so the page can attach the phone position to it. It is null when nothing was stored.
 */
function gpsSessionPress(PDO $pdo, string $type, ?int $now = null): array
{
    $now = $now ?? time();
    $when = date('Y-m-d H:i:s', $now);
    $parsed = gpsSessionParseType($type);
    if ($type !== 'start' && $type !== 'end' && $parsed === null) {
        return ['ok' => false, 'message' => 'Unknown button.', 'event_id' => null];
    }

    $pdo->beginTransaction();
    try {
        $session = gpsSessionOpen($pdo);

        if ($type === 'start') {
            if ($session !== null) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Logging is already on.', 'event_id' => null];
            }
            $pdo->prepare("INSERT INTO gps_sessions (started_at) VALUES (?)")->execute([$when]);
            $sessionId = (int) $pdo->lastInsertId();
            $eventId = gpsSessionInsertGroup($pdo, $sessionId, [], 'start', $when);
            $pdo->commit();
            return ['ok' => true, 'message' => 'Logging started.', 'event_id' => $eventId];
        }

        if ($session === null) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Logging is not on. Press Start logging first.', 'event_id' => null];
        }
        $sessionId = (int) $session['id'];
        $events = gpsSessionEvents($pdo, $sessionId);
        $running = gpsSessionRunningJob($events);

        if ($type === 'end') {
            $autos = [];
            if ($running !== null && $running['step'] === 2) {
                $autos[] = 'drop_' . $running['job'];
            }
            $eventId = gpsSessionInsertGroup($pdo, $sessionId, $autos, 'end', $when);
            $pdo->prepare("UPDATE gps_sessions SET ended_at = ? WHERE id = ?")->execute([$when, $sessionId]);
            $pdo->commit();
            return ['ok' => true, 'message' => 'Logging ended.', 'event_id' => $eventId];
        }

        // A job button. Ignore the same button pressed twice within a few seconds.
        $lastPress = null;
        foreach ($events as $event) {
            if ($event['source'] === 'press') {
                $lastPress = $event;
            }
        }
        if ($lastPress !== null && $lastPress['event_type'] === $type
            && $now - strtotime($lastPress['occurred_at']) < GPS_SESSION_DUPLICATE_SECONDS) {
            $pdo->rollBack();
            return ['ok' => true, 'message' => 'Ignored: the same button was pressed a moment ago.', 'event_id' => null];
        }

        $job = $parsed['job'];
        $step = $parsed['step'];
        $autos = [];

        // A job is running and this press belongs to another job, or to an earlier or the same step: close it first.
        if ($running !== null && ($running['job'] !== $job || $step <= $running['step'])) {
            if ($running['step'] === 2) {
                $autos[] = 'drop_' . $running['job'];
            }
            // A job that only reached "go to pickup" was cancelled. It needs no event, the report sees it
            $running = null;
        }

        // Fill the steps of this job that were skipped
        $firstMissing = ($running !== null) ? $running['step'] + 1 : 1;
        for ($i = $firstMissing; $i < $step; $i++) {
            $autos[] = GPS_SESSION_STEPS[$i] . '_' . $job;
        }

        $eventId = gpsSessionInsertGroup($pdo, $sessionId, $autos, $type, $when);
        $pdo->commit();
        $message = gpsSessionLabel($type) . ' saved.';
        if ($autos) {
            $message .= ' Also filled in: ' . implode(', ', array_map('gpsSessionLabel', $autos)) . '.';
        }
        return ['ok' => true, 'message' => $message, 'event_id' => $eventId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Undo the last button pressed, together with the steps that were filled in for it.
 * Undoing End logging opens the session again and undoing Start logging removes the empty session.
 */
function gpsSessionUndo(PDO $pdo): array
{
    $session = gpsSessionOpen($pdo);
    if ($session === null) {
        $session = $pdo->query("SELECT * FROM gps_sessions ORDER BY id DESC LIMIT 1")->fetch() ?: null;
    }
    if ($session === null) {
        return ['ok' => false, 'message' => 'Nothing to undo.'];
    }

    $sessionId = (int) $session['id'];
    $lastPress = null;
    foreach (gpsSessionEvents($pdo, $sessionId) as $event) {
        if ($event['source'] === 'press') {
            $lastPress = $event;
        }
    }
    if ($lastPress === null || ($session['ended_at'] !== null && $lastPress['event_type'] !== 'end')) {
        return ['ok' => false, 'message' => 'Nothing to undo.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM gps_events WHERE auto_for = ?")->execute([$lastPress['id']]);
        $pdo->prepare("DELETE FROM gps_events WHERE id = ?")->execute([$lastPress['id']]);
        if ($lastPress['event_type'] === 'end') {
            $pdo->prepare("UPDATE gps_sessions SET ended_at = NULL WHERE id = ?")->execute([$sessionId]);
        } elseif ($lastPress['event_type'] === 'start') {
            $pdo->prepare("DELETE FROM gps_events WHERE session_id = ?")->execute([$sessionId]);
            $pdo->prepare("DELETE FROM gps_sessions WHERE id = ?")->execute([$sessionId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['ok' => true, 'message' => 'Undid: ' . gpsSessionLabel($lastPress['event_type']) . '.'];
}

/** Read a time typed on the page ("2026-11-26T14:05:09" or "2026-11-26 14:05") into a unix timestamp, or null */
function gpsSessionParseTime(string $input): ?int
{
    foreach (['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $date = DateTime::createFromFormat('!' . $format, trim($input));
        $errors = DateTime::getLastErrors();
        if ($date !== false && (!$errors || ($errors['warning_count'] + $errors['error_count']) === 0)) {
            return $date->getTimestamp();
        }
    }
    return null;
}

/** Fetch an event that was pressed by hand (not filled in), or null */
function gpsSessionPressedEvent(PDO $pdo, int $eventId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM gps_events WHERE id = ? AND source = 'press'");
    $stmt->execute([$eventId]);
    return $stmt->fetch() ?: null;
}

/**
 * Change the time of a pressed event. The steps filled in for it move with it.
 * The time has to stay between the events pressed before and after it, and cannot be in the future.
 */
function gpsSessionEditTime(PDO $pdo, int $eventId, string $input, ?int $now = null): array
{
    $now = $now ?? time();
    $event = gpsSessionPressedEvent($pdo, $eventId);
    if ($event === null) {
        return ['ok' => false, 'message' => 'That event no longer exists.'];
    }
    $time = gpsSessionParseTime($input);
    if ($time === null) {
        return ['ok' => false, 'message' => 'That is not a valid date and time.'];
    }
    if ($time > $now + 60) {
        return ['ok' => false, 'message' => 'The time cannot be in the future.'];
    }

    $previous = $pdo->prepare(
        "SELECT occurred_at FROM gps_events WHERE session_id = ? AND source = 'press' AND id < ? ORDER BY id DESC LIMIT 1"
    );
    $previous->execute([$event['session_id'], $eventId]);
    $before = $previous->fetchColumn();
    if ($before !== false && $time < strtotime($before)) {
        return ['ok' => false, 'message' => 'The time cannot be before the previous button (' . $before . ').'];
    }
    $next = $pdo->prepare(
        "SELECT occurred_at FROM gps_events WHERE session_id = ? AND source = 'press' AND id > ? ORDER BY id LIMIT 1"
    );
    $next->execute([$event['session_id'], $eventId]);
    $after = $next->fetchColumn();
    if ($after !== false && $time > strtotime($after)) {
        return ['ok' => false, 'message' => 'The time cannot be after the next button (' . $after . ').'];
    }

    $when = date('Y-m-d H:i:s', $time);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE gps_events SET occurred_at = ? WHERE id = ? OR auto_for = ?")
            ->execute([$when, $eventId, $eventId]);
        if ($event['event_type'] === 'start') {
            $pdo->prepare("UPDATE gps_sessions SET started_at = ? WHERE id = ?")->execute([$when, $event['session_id']]);
        } elseif ($event['event_type'] === 'end') {
            $pdo->prepare("UPDATE gps_sessions SET ended_at = ? WHERE id = ?")->execute([$when, $event['session_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['ok' => true, 'message' => 'Time changed.'];
}

/** Delete a pressed job event and the steps filled in for it. Start and End are removed with Undo instead. */
function gpsSessionDeleteEvent(PDO $pdo, int $eventId): array
{
    $event = gpsSessionPressedEvent($pdo, $eventId);
    if ($event === null) {
        return ['ok' => false, 'message' => 'That event no longer exists.'];
    }
    if (gpsSessionParseType($event['event_type']) === null) {
        return ['ok' => false, 'message' => 'Start and End logging are removed with Undo last.'];
    }
    $pdo->prepare("DELETE FROM gps_events WHERE id = ? OR auto_for = ?")->execute([$eventId, $eventId]);
    return ['ok' => true, 'message' => 'Deleted: ' . gpsSessionLabel($event['event_type']) . '.'];
}

/** Attach the phone position to a pressed event and to the steps filled in for it */
function gpsSessionSetLocation(PDO $pdo, int $eventId, float $lat, float $lon, ?float $accuracy): array
{
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        return ['ok' => false, 'message' => 'That position is not valid.'];
    }
    if (gpsSessionPressedEvent($pdo, $eventId) === null) {
        return ['ok' => false, 'message' => 'That event no longer exists.'];
    }
    $accuracy = ($accuracy !== null && $accuracy >= 0 && $accuracy < 100000) ? round($accuracy, 1) : null;
    $pdo->prepare("UPDATE gps_events SET latitude = ?, longitude = ?, accuracy_m = ? WHERE id = ? OR auto_for = ?")
        ->execute([round($lat, 6), round($lon, 6), $accuracy, $eventId, $eventId]);
    return ['ok' => true, 'message' => 'Position saved.'];
}

/**
 * Everything the GPS Log page shows, in one array that is sent to the page as JSON.
 * The session shown is the one being logged, or the most recent finished one.
 */
function gpsSessionState(PDO $pdo, ?int $now = null): array
{
    $now = $now ?? time();
    $session = gpsSessionOpen($pdo);
    if ($session === null) {
        $session = $pdo->query("SELECT * FROM gps_sessions ORDER BY id DESC LIMIT 1")->fetch() ?: null;
    }

    $events = [];
    $shown = null;
    $stage = ['code' => 'none', 'text' => 'Not logging', 'since' => null];
    $canUndo = false;
    if ($session !== null) {
        $isOpen = ($session['ended_at'] === null);
        $rows = gpsSessionEvents($pdo, (int) $session['id']);
        $lastPress = null;
        foreach ($rows as $row) {
            if ($row['source'] === 'press') {
                $lastPress = $row;
            }
            $events[] = [
                'id'       => (int) $row['id'],
                'type'     => $row['event_type'],
                'label'    => gpsSessionLabel($row['event_type']),
                'time'     => $row['occurred_at'],
                'auto'     => $row['source'] === 'auto',
                'editable' => $row['source'] === 'press',
                'deletable' => $row['source'] === 'press' && gpsSessionParseType($row['event_type']) !== null,
                'has_location' => $row['latitude'] !== null,
                'accuracy_m'   => $row['accuracy_m'] !== null ? (float) $row['accuracy_m'] : null,
            ];
        }
        $end = $isOpen ? $now : strtotime($session['ended_at']);
        $shown = [
            'id'         => (int) $session['id'],
            'started_at' => $session['started_at'],
            'ended_at'   => $session['ended_at'],
            'open'       => $isOpen,
            'minutes'    => (int) floor(max(0, $end - strtotime($session['started_at'])) / 60),
        ];
        $canUndo = $lastPress !== null && ($isOpen || $lastPress['event_type'] === 'end');

        if ($isOpen) {
            $running = gpsSessionRunningJob($rows);
            $since = $rows ? end($rows)['occurred_at'] : $session['started_at'];
            if ($running === null) {
                $stage = ['code' => 'between', 'text' => 'Between jobs', 'since' => $since];
            } elseif ($running['step'] === 1) {
                $stage = ['code' => 'to_pickup', 'text' => 'Going to ' . GPS_SESSION_JOBS[$running['job']] . ' pickup', 'since' => $since];
            } else {
                $stage = ['code' => 'loaded', 'text' => 'Carrying ' . GPS_SESSION_JOBS[$running['job']] . ' passenger', 'since' => $since];
            }
        }
    }

    $recent = [];
    $rows = $pdo->query(
        "SELECT s.id, s.started_at, s.ended_at,
                SUM(CASE WHEN e.event_type = 'pickup_uber' THEN 1 ELSE 0 END) AS uber_jobs,
                SUM(CASE WHEN e.event_type = 'pickup_apex' THEN 1 ELSE 0 END) AS apex_jobs
         FROM gps_sessions s
         LEFT JOIN gps_events e ON e.session_id = s.id
         GROUP BY s.id, s.started_at, s.ended_at
         ORDER BY s.id DESC
         LIMIT 10"
    )->fetchAll();
    foreach ($rows as $row) {
        $end = $row['ended_at'] !== null ? strtotime($row['ended_at']) : $now;
        $recent[] = [
            'id'         => (int) $row['id'],
            'started_at' => $row['started_at'],
            'ended_at'   => $row['ended_at'],
            'minutes'    => (int) floor(max(0, $end - strtotime($row['started_at'])) / 60),
            'uber_jobs'  => (int) $row['uber_jobs'],
            'apex_jobs'  => (int) $row['apex_jobs'],
        ];
    }

    $lastPoint = null;
    try {
        $value = $pdo->query("SELECT MAX(recorded_at) FROM gps_log")->fetchColumn();
        $lastPoint = $value !== false && $value !== null ? (int) $value : null;
    } catch (PDOException $e) {
        $lastPoint = null;
    }

    return [
        'now'           => $now,
        'session'       => $shown,
        'stage'         => $stage,
        'events'        => $events,
        'can_undo'      => $canUndo,
        'recent'        => $recent,
        'last_point_at' => $lastPoint,
    ];
}

/**
 * Start time of the session being logged, formatted for the banner, or null when none is open or the table is
 * missing. Used on every page, so it never throws and never logs.
 */
function gpsSessionBannerSince(PDO $pdo): ?string
{
    try {
        $startedAt = $pdo->query("SELECT started_at FROM gps_sessions WHERE ended_at IS NULL ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        return null;
    }
    if ($startedAt === false || $startedAt === null) {
        return null;
    }
    $time = strtotime($startedAt);
    return date('Y-m-d', $time) === date('Y-m-d') ? date('H:i', $time) : date('j M H:i', $time);
}
