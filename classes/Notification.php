<?php
/**
 * Enhanced Notification class with push notification support
 */

require_once __DIR__ . '/../config/database.php';

class Notification {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create notification
     */
    public function create($user_id, $message) {
        $sql = "INSERT INTO notification_logs (user_id, message) VALUES (?, ?)";
        
        try {
            $this->db->query($sql, [$user_id, $message]);
            $notification_id = $this->db->lastInsertId();
            
            // Try to send push notification if enabled
            $this->sendPushNotification($user_id, $message);
            
            return $notification_id;
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for user
     */
    public function getForUser($user_id, $limit = 10) {
        $sql = "SELECT * FROM notification_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return $this->db->resultset($sql, [$user_id, $limit]);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($id) {
        $sql = "UPDATE notification_logs SET is_read = 1 WHERE id = ?";
        
        try {
            $this->db->query($sql, [$id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($user_id) {
        $sql = "UPDATE notification_logs SET is_read = 1 WHERE user_id = ?";
        
        try {
            $this->db->query($sql, [$user_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notification
     */
    public function delete($id) {
        $sql = "DELETE FROM notification_logs WHERE id = ?";
        
        try {
            $this->db->query($sql, [$id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread count for user
     */
    public function getUnreadCount($user_id) {
        $sql = "SELECT COUNT(*) as count FROM notification_logs 
                WHERE user_id = ? AND is_read = 0";
        
        $result = $this->db->single($sql, [$user_id]);
        
        if ($result) {
            return $result['count'];
        }
        
        return 0;
    }
    
    /**
     * Send weekly reminder to all managers
     */
    public function sendWeeklyReminders() {
        // Get all managers
        $sql = "SELECT id FROM users WHERE role = 'manager'";
        $managers = $this->db->resultset($sql);
        
        $week = date('W');
        $year = date('Y');
        $message = "Please complete your employee ratings for Week $week, $year by Friday.";
        
        $notificationCount = 0;
        foreach ($managers as $manager) {
            if ($this->create($manager['id'], $message)) {
                $notificationCount++;
            }
        }
        
        return $notificationCount;
    }
    
    /**
     * Get push subscription for a user
     */
    public function getSubscription($user_id) {
        $sql = "SELECT subscription_data FROM user_push_subscriptions WHERE user_id = ?";
        $result = $this->db->single($sql, [$user_id]);
        
        if ($result) {
            return json_decode($result['subscription_data'], true);
        }
        
        return null;
    }
    
    /**
     * Save push subscription for a user
     */
    public function saveSubscription($user_id, $subscription_data) {
        // Check if subscription already exists
        $existing = $this->getSubscription($user_id);
        
        try {
            if ($existing) {
                $sql = "UPDATE user_push_subscriptions SET subscription_data = ? WHERE user_id = ?";
                $this->db->query($sql, [json_encode($subscription_data), $user_id]);
            } else {
                $sql = "INSERT INTO user_push_subscriptions (user_id, subscription_data) VALUES (?, ?)";
                $this->db->query($sql, [$user_id, json_encode($subscription_data)]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error saving subscription: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete push subscription for a user
     */
    public function deleteSubscription($user_id) {
        $sql = "DELETE FROM user_push_subscriptions WHERE user_id = ?";
        
        try {
            $this->db->query($sql, [$user_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting subscription: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send push notification to a user
     * 
     * Note: This is a simplified implementation. In a production environment,
     * you should use a library like web-push-php/web-push to send actual push notifications.
     */
    public function sendPushNotification($user_id, $message) {
        $subscription = $this->getSubscription($user_id);
        
        if (!$subscription) {
            return false; // No subscription found
        }
        
        // In a real implementation, you would use a library like web-push-php/web-push
        // Example code (disabled):
        /*
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:admin@employees.test.com',
                'publicKey' => 'YOUR_PUBLIC_KEY',
                'privateKey' => 'YOUR_PRIVATE_KEY',
            ],
        ];
        
        $webPush = new WebPush($auth);
        
        $webPush->sendNotification(
            Subscription::create($subscription),
            json_encode([
                'title' => 'Employee Rating System',
                'message' => $message,
                'url' => '/notifications.php'
            ])
        );
        */
        
        // For now, just log that we would send a notification
        error_log("Would send push notification to user $user_id: $message");
        
        return true;
    }
}
