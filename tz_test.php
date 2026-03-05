<?php
require_once 'config.php';

echo "<h2>PHP Timezone</h2>";
echo "TIME_ZONE constant: " . TIME_ZONE . "<br>";
echo "date_default_timezone_get(): " . date_default_timezone_get() . "<br>";
echo "Current PHP time (date()): " . date('Y-m-d H:i:s') . "<br>";
echo "Current PHP time (DateTime): " . (new DateTime())->format('Y-m-d H:i:s') . "<br>";

echo "<h2>MySQL Timezone</h2>";
$result = $pdo->query("SELECT @@global.time_zone AS global_tz, @@session.time_zone AS session_tz, NOW() AS now, UTC_TIMESTAMP() AS utc_now")->fetch();
echo "Global timezone: " . $result['global_tz'] . "<br>";
echo "Session timezone: " . $result['session_tz'] . "<br>";
echo "MySQL NOW(): " . $result['now'] . "<br>";
echo "MySQL UTC_TIMESTAMP(): " . $result['utc_now'] . "<br>";

echo "<h2>Summary</h2>";
$phpOk = date_default_timezone_get() === TIME_ZONE;
$mysqlOk = ($result['session_tz'] === TIME_ZONE || $result['session_tz'] === '+02:00');
echo "PHP timezone correct (SAST): " . ($phpOk ? "✅ YES" : "❌ NO — got " . date_default_timezone_get()) . "<br>";
echo "MySQL session timezone correct: " . ($mysqlOk ? "✅ YES" : "❌ NO — got " . $result['session_tz']) . "<br>";
?>