-- migrations/20261126_1200_gps_sessions.sql
-- GPS log, part 4: logging sessions and the button presses made during them (services/gps_sessions.php).
-- A session runs from "Start logging" to "End logging". Its events are the button presses: start, go to pickup,
-- pickup, drop off (for Uber and for Apex) and end. Times are server times in the site time zone.
-- source is press for a button pressed by hand and auto for a step the page filled in because a button was
-- skipped or a job was overridden. An auto event has auto_for set to the press that caused it, so Undo removes both.
-- latitude, longitude and accuracy_m are the phone position at the moment of the press, when the phone allowed it.
-- Nothing else reads these tables. The GPS points themselves stay in gps_log and are joined by time later.
-- Additive only: two new tables. Safe to run more than once.
-- Run this BEFORE deploying the new GPS Log page, otherwise the page shows an error.
CREATE TABLE IF NOT EXISTS gps_sessions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    ended_at   DATETIME NULL DEFAULT NULL,
    KEY idx_gps_sessions_ended (ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gps_events (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id  INT UNSIGNED NOT NULL,
    event_type  VARCHAR(20) NOT NULL,
    occurred_at DATETIME NOT NULL,
    source      VARCHAR(8) NOT NULL DEFAULT 'press',
    auto_for    INT UNSIGNED NULL DEFAULT NULL,
    latitude    DECIMAL(9,6) NULL DEFAULT NULL,
    longitude   DECIMAL(9,6) NULL DEFAULT NULL,
    accuracy_m  DECIMAL(8,1) NULL DEFAULT NULL,
    KEY idx_gps_events_session (session_id, occurred_at),
    KEY idx_gps_events_auto (auto_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
