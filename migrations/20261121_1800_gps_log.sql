-- migrations/20261121_1800_gps_log.sql
-- GPS log, part 1 (test framework): a table that receives location points from the Traccar Client app
-- on the phone, through services/api/gps_log.php. Nothing reads it yet.
-- recorded_at is when the phone took the point and received_at is when the server got it. Both are unix
-- timestamps, like the other Uber and fuel timestamps. recorded_at is unique, so a point the app sends
-- twice (it re-sends when a request fails) is stored once.
-- speed_kmh is already converted to km/h. is_moving and battery_pct are only filled when the app sends them.
-- Also gives this database its own random token for the endpoint (Services -> System Variables,
-- "GPS Log Token"). Enter that token as the Device identifier in Traccar Client.
-- Logging itself stays off until "GPS Log" is set to On in System Variables, which needs no database change.
-- Additive only: one new table and one new variable row. Safe to run more than once.
CREATE TABLE IF NOT EXISTS gps_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recorded_at  INT UNSIGNED NOT NULL,
    received_at  INT UNSIGNED NOT NULL,
    latitude     DECIMAL(9,6) NOT NULL,
    longitude    DECIMAL(9,6) NOT NULL,
    speed_kmh    DECIMAL(6,2) NULL DEFAULT NULL,
    heading      SMALLINT UNSIGNED NULL DEFAULT NULL,
    altitude_m   DECIMAL(7,1) NULL DEFAULT NULL,
    accuracy_m   DECIMAL(7,1) NULL DEFAULT NULL,
    battery_pct  TINYINT UNSIGNED NULL DEFAULT NULL,
    is_moving    TINYINT(1) NULL DEFAULT NULL,
    UNIQUE KEY uq_gps_recorded (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_variables (name, value)
VALUES ('gps_log_token', SHA2(CONCAT(UUID(), RAND()), 256));
