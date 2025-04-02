<?php
/**
 * Test Email Sending Script
 * 
 * Place this file in the root directory of your application
 * and access it via browser to test email functionality
 */

// Include necessary files
require_once 'config/config.php';
require_once 'classes/EmailService.php';

// Initialize email service
$emailService = new EmailService();

// Create some test content
$content = '
<h3>This is a test email</h3>
<p>This email was sent to test the email sending functionality of the application.</p>
<p>If you received this email, the email sending functionality is working correctly.</p>
<p>Current time: ' . date('Y-m-d H:i:s') . '</p>
';

// Format the content using the email template
$formattedContent = $emailService->formatEmailTemplate($content, 'Email Test');

echo '<h1>Testing Email Functionality</h1>';

// If we're submitting the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $to = $_POST['email'];
    
    echo '<div style="background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px;">';
    echo '<h2>Sending Test Email</h2>';
    echo '<p>Sending to: ' . htmlspecialchars($to) . '</p>';
    
    // Try to send the email
    $result = $emailService->sendEmail($to, 'Test Email from ' . APP_NAME, $formattedContent);
    
    if ($result) {
        echo '<p style="color: green; font-weight: bold;">Email sent successfully!</p>';
    } else {
        echo '<p style="color: red; font-weight: bold;">Failed to send email.</p>';
        echo '<p>Check the logs/email_debug.log file for details.</p>';
    }
    
    echo '</div>';
}

// Display the form
?>

<form method="post" style="background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <h2>Send Test Email</h2>
    <div>
        <label for="email">Email Address:</label>
        <input type="email" id="email" name="email" required style="width: 100%; padding: 8px; margin: 10px 0;">
    </div>
    <button type="submit" style="background-color: #4e73df; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Send Test Email</button>
</form>

<div style="background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px;">
    <h2>Email Configuration</h2>
    <pre>
MAIL_HOST: <?php echo defined('MAIL_HOST') ? MAIL_HOST : 'Not defined'; ?>

MAIL_PORT: <?php echo defined('MAIL_PORT') ? MAIL_PORT : 'Not defined'; ?>

MAIL_USERNAME: <?php echo defined('MAIL_USERNAME') ? MAIL_USERNAME : 'Not defined'; ?>

MAIL_PASSWORD: <?php echo defined('MAIL_PASSWORD') ? (MAIL_PASSWORD ? '[Set]' : '[Empty]') : 'Not defined'; ?>

MAIL_FROM_ADDRESS: <?php echo defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'Not defined'; ?>

MAIL_FROM_NAME: <?php echo defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Not defined'; ?>

MAIL_DEBUG: <?php echo defined('MAIL_DEBUG') ? (MAIL_DEBUG ? 'True' : 'False') : 'Not defined'; ?>

PHPMailer Available: <?php echo class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'Yes' : 'No'; ?>

PHP mail() Function: <?php echo function_exists('mail') ? 'Available' : 'Not available'; ?>
    </pre>
    
    <h3>Log File Contents (if any)</h3>
    <div style="background-color: #333; color: #fff; padding: 10px; max-height: 300px; overflow: auto; font-family: monospace;">
    <?php
    $logFile = __DIR__ . '/logs/email_debug.log';
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        echo nl2br(htmlspecialchars($logs));
    } else {
        echo 'No log file found at: ' . $logFile;
    }
    ?>
    </div>
</div>
