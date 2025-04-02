<?php
/**
 * Push Notifications API Endpoint
 */

// Set content type
header('Content-Type: application/json');

// Include necessary files
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../classes/Notification.php';

// Ensure user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification = new Notification();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request body
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Handle subscription
if (isset($data['action'])) {
    switch ($data['action']) {
        case 'subscribe':
            if (!isset($data['subscription']) || !is_array($data['subscription'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing subscription data']);
                exit;
            }
            
            $result = $notification->saveSubscription($user_id, $data['subscription']);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Subscription saved']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save subscription']);
            }
            break;
            
        case 'unsubscribe':
            $result = $notification->deleteSubscription($user_id);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Subscription removed']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to remove subscription']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action parameter']);
}
