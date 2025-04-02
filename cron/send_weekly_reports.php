<?php
/**
 * Weekly report sender script to be run by cron job every weekend
 * 
 * Setup cron job (on Saturday at 9am):
 * 0 9 * * 6 php /path/to/employee-rating-system/cron/send_weekly_reports.php
 */

// Set path to the root directory
$root_dir = dirname(__DIR__);

// Include necessary files
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/config/database.php';
require_once $root_dir . '/classes/ReportGenerator.php';

// Check if today is Saturday
$dayOfWeek = date('w');
if ($dayOfWeek != 6 && !isset($argv[1])) {
    echo "This script should run on Saturdays only.\n";
    echo "Use --force to run regardless of day.\n";
    exit(1);
}

// Get previous week's number and year
$date = new DateTime();
$date->modify('-1 week');
$week = $date->format('W');
$year = $date->format('Y');

echo "Generating and sending performance reports for Week $week, $year\n";

// Initialize report generator
$reportGenerator = new ReportGenerator();

// Send reports to all employees with ratings in the previous week
$result = $reportGenerator->sendAllWeeklyReports($week, $year);

echo "Reports sent: {$result['success']} successful, {$result['failure']} failed, {$result['total']} total.\n";

echo "Weekly report process completed.\n";
