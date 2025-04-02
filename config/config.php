<?php
/**
 * Application Configuration Template
 * 
 * Rename this file to config.php and update with your settings
 */

// Application name
define('APP_NAME', 'Employee Rating System');

// Application version
define('APP_VERSION', '1.0.0');

// Application URL (Set this to your server URL)
define('APP_URL', 'http://localhost/employee-rating-system');

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'eratinguser');
define('DB_PASS', 'choose_a_secure_password');
define('DB_NAME', 'employee_rating_system');

// Mail configuration
define('MAIL_HOST', 'your-smtp-server.com'); 
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'system@example.com');
define('MAIL_PASSWORD', 'your-email-password');
define('MAIL_FROM_ADDRESS', 'system@example.com');
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_DEBUG', false); 

// Session configuration
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// Timezone
date_default_timezone_set('Asia/Kolkata'); // Set to your timezone

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
