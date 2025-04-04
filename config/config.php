<?php
/**
 * Application Configuration
 */

// Application name
define('APP_NAME', 'Employee Rating System');

// Application version
define('APP_VERSION', '1.0.0');

// Application URL
define('APP_URL', 'http://172.31.254.19/employee-rating-system');

// Database credentials
// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'eratinguser');
define('DB_PASS', 'choose_a_secure_password');
define('DB_NAME', 'employee_rating_system');

define('MAIL_HOST', 'wifisoft.mail.pairserver.com'); // Change to your actual SMTP server (e.g. smtp.gmail.com)
define('MAIL_PORT', 465);
define('MAIL_USERNAME', 'alert@indionetworks.com'); // Change to your actual email
define('MAIL_PASSWORD', 'wifi123#'); // Change to your actual password
define('MAIL_FROM_ADDRESS', 'alert@indionetworks.com'); // Change to your actual email
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_DEBUG', false); 

// Session configuration
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// Timezone
date_default_timezone_set('Asia/Kolkata'); // Set to your timezone (e.g., Asia/Kolkata for Pune)

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
