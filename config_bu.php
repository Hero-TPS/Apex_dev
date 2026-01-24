<?php
// Temporary debug
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * config.php - Secure Configuration & Database Connection
 */
   
// Configuration constants
//const CUSTOM_CALENDAR_ID = '38399107501035d90360ce305138c1cbf298745bff697f1de1478049a38b8eb0@group.calendar.google.com';
//const CALENDAR_LINK='https://calendar.google.com/calendar/u/0/r?cid=cXVlbnRpbmNhbUBnbWFpbC5jb20&mode=AGENDA';
const CUSTOM_CALENDAR_ID = '765e1ec046eaa848aaf6a20611a8d815109da563222f638ccf15e585a008fec7@group.calendar.google.com';
const CALENDAR_LINK='https://calendar.google.com/calendar/u/0/r?cid=cXVlbnRpbmNhbUBnbWFpbC5jb20&mode=AGENDA';
const WEB_APP_URL = 'https://localhost/HPTS-XAMPP/';

// Business and WhatsApp settings
define('BUSINESS_NAME', 'Apex Transit Local');
define('BUSINESS_OWNER', 'Quentin Campbell');
define('WHATSAPP_COUNTRY_CODE', '27');
define('TIME_ZONE', 'Africa/Johannesburg');
define('DEFAULT_DURATION', 1); // hours
define('BUSINESS_ADDRESS', '7 Lily Str, Heldervue, Somerset West, 7130');
define('BUSINESS_PHONE', '082 634 0312');
define('ROOT_DIR', dirname(__DIR__));

// Set default timezone
date_default_timezone_set(TIME_ZONE);

// new PD0 con
$pdo_host = '127.0.0.1';
$dbname = 'xampp_hpts';  
$user = 'root';       
$pass = '';  

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO("mysql:host=$pdo_host;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Service unavailable.');
}

if (!defined('BASE_URL')) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $projectDir = basename(__DIR__); // 'HPTS-XAMPP'
    define('BASE_URL', '/' . $projectDir);
}