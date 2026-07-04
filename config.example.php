<?php
// =============================================================================
// config.example.php — DEVELOPMENT (XAMPP)
// =============================================================================
// INSTRUCTIONS:
//   1. Copy this file to config.php in the same directory
//   2. Fill in all placeholder values below
//   3. config.php is in .gitignore and must NEVER be committed
// =============================================================================

// -----------------------------------------------------------------------------
// ENVIRONMENT
// -----------------------------------------------------------------------------
define('APP_ENV', 'development'); // 'development' or 'production'

// -----------------------------------------------------------------------------
// URL & PATHS
// -----------------------------------------------------------------------------
define('BASE_URL', 'http://localhost/HPTS-XAMPP'); // No trailing slash. e.g. http://localhost/HPTS-XAMPP
define('ROOT_DIR',  __DIR__);                      // Automatically resolves to the directory of this file

// -----------------------------------------------------------------------------
// DATABASE
// -----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_dev_database_name');
define('DB_USER', 'root');
define('DB_PASS', '');

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
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check config.php.');
}

// -----------------------------------------------------------------------------
// AUTHENTICATION
// -----------------------------------------------------------------------------
// Option A — Pre-generated hash (recommended for production, faster):
//   Step 1: Run once:  echo password_hash('your_password', PASSWORD_DEFAULT);
//   Step 2: Paste the output below as a string
define('ADMIN_USERNAME', 'your_admin_username');
define('ADMIN_PASSWORD_HASH', 'your_bcrypt_hash_here');

// Option B — Inline hash (simpler, fine for dev or short-term):
// define('ADMIN_PASSWORD_HASH', password_hash('your_actual_password', PASSWORD_DEFAULT));

// -----------------------------------------------------------------------------
// BUSINESS DETAILS
// -----------------------------------------------------------------------------
define('BUSINESS_NAME',  'Your Business Name');
define('BUSINESS_OWNER', 'Owner Full Name');
define('BUSINESS_PHONE', '+27821234567');       // Display format, with country code
define('BUSINESS_WHATSAPP', '27821234567');     // Digits only — no +, spaces, or dashes
define('BUSINESS_EMAIL', 'you@example.com');

// -----------------------------------------------------------------------------
// LOCALISATION
// -----------------------------------------------------------------------------
define('TIME_ZONE',             'Africa/Johannesburg');
define('WHATSAPP_COUNTRY_CODE', '27'); // South Africa
date_default_timezone_set(TIME_ZONE); // Set PHP global timezone — must be after TIME_ZONE define

// -----------------------------------------------------------------------------
// GOOGLE CALENDAR
// -----------------------------------------------------------------------------
// The Calendar ID can be found in Google Calendar > Settings > [Calendar name] > Calendar ID
// The service account key file must be placed at: /service-account-key.json (repo root)
// and is listed in .gitignore — NEVER commit it.
define('CUSTOM_CALENDAR_ID', 'your_google_calendar_id@group.calendar.google.com');

// -----------------------------------------------------------------------------
// GOOGLE DISTANCE MATRIX API
// -----------------------------------------------------------------------------
// Used by Trip Distance Calculator module
// Obtain an API key from: https://console.cloud.google.com/
// Enable the Distance Matrix API for your project
define('GOOGLE_API_KEY', 'YOUR_GOOGLE_API_KEY_HERE');
define('GOOGLE_DISTANCE_MATRIX_URL', 'https://maps.googleapis.com/maps/api/distancematrix/json');

// =============================================================================
// END OF CONFIG
// =============================================================================
