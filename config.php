<?php
/**
 * Lost & Found Portal - Configuration File
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application Constants
define('APP_NAME', 'FindIt Portal');
define('APP_TAGLINE', 'Reconnecting people with their lost belongings');
define('APP_URL', '');

// Database Configuration
// 'sqlite' requires ZERO setup - it automatically creates lost_and_found.db in project root.
// 'mysql' can be used with XAMPP/WAMP by setting DB_DRIVER to 'mysql'.
define('DB_DRIVER', 'sqlite');

// MySQL Credentials (used if DB_DRIVER is set to 'mysql')
define('DB_HOST', 'localhost');
define('DB_NAME', 'lost_and_found');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);

// SQLite Database Path
define('SQLITE_FILE', __DIR__ . '/lost_and_found.db');

// Upload directory & file upload limits
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_WEB_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Ensure uploads folder exists
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}

// Start PHP Session safely
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
