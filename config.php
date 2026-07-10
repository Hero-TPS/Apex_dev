<?php

// ENVIRONMENT
//
define('APP_ENV', 'development'); // 'development' or 'production'

// URL & PATHS
// 
define('BASE_URL', 'https://apex-dev.infinityfree.me');

define('ROOT_DIR', __DIR__); 

require_once ROOT_DIR . '/includes/logger.php';

// DATABASE
//
define('DB_HOST', 'sql203.infinityfree.com');
define('DB_NAME', 'if0_39619118_apex_dev');
define('DB_USER', 'if0_39619118');
define('DB_PASS', 'aTn6Ja8NpF6nUf');

// PDO connection — do not modify below this line
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+02:00'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check config.php.');
}

// AUTHENTICATION
//
define('ADMIN_USERNAME', 'Admin');
define('ADMIN_PASSWORD_HASH', password_hash('H3adBang3r', PASSWORD_DEFAULT));

// BUSINESS DETAILS
//
define('BUSINESS_NAME',  'Apex Transit Dev');
define('BUSINESS_OWNER', 'Quentin Campbell');
define('BUSINESS_PHONE', '+2782 634 0312');       // Display format, with country code
define('BUSINESS_WHATSAPP', '27826340312');     // Digits only — no +, spaces, or dashes
define('BUSINESS_EMAIL', 'quentincam@gmail.com');

// LOCALISATION
// 
define('TIME_ZONE',             'Africa/Johannesburg');
define('WHATSAPP_COUNTRY_CODE', '27'); // South Africa
date_default_timezone_set(TIME_ZONE);

// GOOGLE CALENDAR
//
define('CUSTOM_CALENDAR_ID', 
'765e1ec046eaa848aaf6a20611a8d815109da563222f638ccf15e585a008fec7@group.calendar.google.com');

// Trip Distance Calculator
define('GOOGLE_API_KEY', 'AIzaSyAUwqRUcYu7eRJj-WE6Nv8GlBSP5-uDJfU');
define('GOOGLE_DISTANCE_MATRIX_URL', 'https://maps.googleapis.com/maps/api/distancematrix/json');
