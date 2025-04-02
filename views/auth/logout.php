<?php
/**
 * Logout script
 */

// Include auth file
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Logout user
logoutUser();

// Redirect to login page
$_SESSION['message'] = 'You have been logged out successfully';
$_SESSION['message_type'] = 'success';
header('Location: ' . APP_URL . '/index.php');
exit;
