<?php
/**
 * Weekly reminder script to be run by cron job every Friday
 * 
 * Setup cron job (on Friday at 9am):
 * 0 9 * * 5 php /path/to/employee-rating-system/cron/weekly_reminders.php
 */

// Set path to the root directory
$root_dir = dirname(__DIR__);

// Include necessary files
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/config/database.php';
require_once $root_dir . '/classes/Notification.php';
require_once $root_dir . '/classes/User.php';

// Check if today is Friday
$dayOfWeek = date('w');
if ($dayOfWeek != 5 && !isset($argv[1])) {
    echo "This script should run on Fridays only.\n";
    echo "Use --force to run regardless of day.\n";
    exit(1);
}

// Get current week and year
$week = date('W');
$year = date('Y');

// Create notification for all managers
$notification = new Notification();
$notificationCount = 0;

// Get all managers
$user = new User();
$managers = $user->getAllManagers();

foreach ($managers as $manager) {
    $message = "Reminder: Please complete your employee ratings for Week $week, $year by end of day today.";
    if ($notification->create($manager['id'], $message)) {
        $notificationCount++;
    }
}

echo "Sent $notificationCount reminder notifications to managers.\n";

// Send email notifications if configured
// Note: You would need a proper email sending library like PHPMailer
// This is a placeholder for the email functionality
if (defined('MAIL_HOST') && MAIL_HOST !== 'smtp.example.com') {
    echo "Would send email notifications if properly configured.\n";
    
    // Placeholder for email sending code
    // foreach ($managers as $manager) {
    //     // Send email to $manager['email']
    // }
}

echo "Weekly reminder process completed.\n";
