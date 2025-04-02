<?php
/**
 * Password Reset class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/EmailService.php';

class PasswordReset {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a password reset token
     * 
     * @param int $user_id The user ID
     * @return array|bool Array with token and user info on success, false on failure
     */
    public function createToken($user_id) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiry to 24 hours from now
        $expires_at = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
        
        // Delete any existing tokens for this user
        $this->deleteTokens($user_id);
        
        // Insert the new token
        $sql = "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)";
        
        try {
            $this->db->query($sql, [$user_id, $token, $expires_at]);
            
            // Get user details for email
            $sql = "SELECT email, first_name, last_name FROM users WHERE id = ?";
            $user = $this->db->single($sql, [$user_id]);
            
            if ($user) {
                return [
                    'token' => $token,
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ];
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error creating password reset token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate a password reset token
     * 
     * @param string $token The token to validate
     * @return array|bool User ID on success, false if token is invalid or expired
     */
    public function validateToken($token) {
        $sql = "SELECT r.user_id, u.username, u.email, u.first_name, u.last_name 
                FROM password_resets r
                JOIN users u ON r.user_id = u.id
                WHERE r.token = ? AND r.expires_at > NOW()";
                
        $result = $this->db->single($sql, [$token]);
        
        if ($result) {
            return $result;
        }
        
        return false;
    }
    
    /**
     * Delete all tokens for a user
     * 
     * @param int $user_id The user ID
     * @return bool True on success, false on failure
     */
    public function deleteTokens($user_id) {
        $sql = "DELETE FROM password_resets WHERE user_id = ?";
        
        try {
            $this->db->query($sql, [$user_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting password reset tokens: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a specific token
     * 
     * @param string $token The token to delete
     * @return bool True on success, false on failure
     */
    public function deleteToken($token) {
        $sql = "DELETE FROM password_resets WHERE token = ?";
        
        try {
            $this->db->query($sql, [$token]);
            return true;
        } catch (PDOException $e) {
            error_log("Error deleting password reset token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up expired tokens
     * 
     * @return bool True on success, false on failure
     */
    public function cleanupExpiredTokens() {
        $sql = "DELETE FROM password_resets WHERE expires_at < NOW()";
        
        try {
            $this->db->query($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Error cleaning up expired tokens: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a password reset email
     * 
     * @param array $userData User data (token, email, first_name, last_name)
     * @return bool True if email was sent, false otherwise
     */
    public function sendResetEmail($userData) {
        // Create reset link
        $resetLink = APP_URL . '/reset_password.php?token=' . $userData['token'];
        
        // Set up email service
        $emailService = new EmailService();
        
        // Set email details
        $to = $userData['email'];
        $subject = APP_NAME . ' - Password Reset Request';
        
        // Generate HTML message with reset link
        $message = $this->getResetEmailTemplate($userData['first_name'], $resetLink);
        
        // Send the email
        return $emailService->sendEmail($to, $subject, $message);
    }
    
    /**
     * Get the HTML template for the reset email
     * 
     * @param string $firstName User's first name
     * @param string $resetLink The password reset link
     * @return string HTML email template
     */
    private function getResetEmailTemplate($firstName, $resetLink) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Password Reset</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                }
                .container {
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    padding: 20px;
                    margin-top: 20px;
                }
                .header {
                    background-color: #4e73df;
                    color: white;
                    padding: 10px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                    margin: -20px -20px 20px;
                }
                .button {
                    display: inline-block;
                    background-color: #4e73df;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .footer {
                    margin-top: 20px;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . APP_NAME . '</h2>
                </div>
                <p>Hello ' . htmlspecialchars($firstName) . ',</p>
                <p>We received a request to reset your password. If you did not make this request, you can ignore this email.</p>
                <p>To reset your password, click the button below:</p>
                <p style="text-align: center;">
                    <a href="' . $resetLink . '" class="button">Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p>' . $resetLink . '</p>
                <p>This link will expire in 24 hours.</p>
                <div class="footer">
                    <p>This is an automated message from ' . APP_NAME . '. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
