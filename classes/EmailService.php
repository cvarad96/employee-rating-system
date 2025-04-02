<?php
/**
 * Email Service class optimized for SSL connections
 */

class EmailService {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $useSMTP;
    private $debugMode;
    
    /**
     * Constructor - loads email settings from config
     */
    public function __construct() {
        $this->host = defined('MAIL_HOST') ? MAIL_HOST : '';
        $this->port = defined('MAIL_PORT') ? MAIL_PORT : 465;
        $this->username = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
        $this->password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
        $this->fromEmail = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '';
        $this->fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME;
        $this->useSMTP = defined('MAIL_HOST') && MAIL_HOST !== 'smtp.example.com';
        $this->debugMode = FALSE;
        
        // Log configuration for debugging
        if ($this->debugMode) {
            $this->logDebug("Email configuration: " . json_encode([
                'host' => $this->host,
                'port' => $this->port,
                'username' => $this->username,
                'fromEmail' => $this->fromEmail,
                'fromName' => $this->fromName,
                'useSMTP' => $this->useSMTP
            ]));
        }
    }
    
    /**
     * Send an email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email HTML content
     * @param array $attachments Optional array of attachments
     * @return bool True if email was sent, false otherwise
     */
    public function sendEmail($to, $subject, $message, $attachments = []) {
        // Check if in debug mode - just log and return success
        if ($this->debugMode) {
            $this->logDebug("DEBUG MODE: Would send email to $to with subject: $subject");
            $this->logDebug("Email Content: " . substr($message, 1500, 2000) . "\n...");
            
            // Save email to file for testing
            $this->saveEmailToFile($to, $subject, $message);
            
            return true;
        }
        
        // Check if email settings are configured
        if (!$this->useSMTP) {
            if($this->debugMode){
                $this->logDebug("Email settings not configured. Email not sent to: $to");
            }
            return false;
        }
        
        // Check recipient
        if (empty($to)) {
            if($this->debugMode){
                $this->logDebug("No recipient specified for email");
            }
            return false;
        }
        
        try {
            // Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                if($this->debugMode){
                    $this->logDebug("PHPMailer class not found, checking for autoload file");
                }
                if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                    if($this->debugMode){
                        $this->logDebug("Loading PHPMailer via Composer autoload");
                    }
                    require_once __DIR__ . '/../vendor/autoload.php';
                } else {
                    if($this->debugMode){
                        $this->logDebug("PHPMailer not available, falling back to mail()");
                    }
                    return $this->sendBasicEmail($to, $subject, $message);
                }
            }
            
            // Use PHPMailer
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->SMTPDebug = $this->debugMode ? 3 : 0; // Increased debug level
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            
            // Important: Use SSL for port 465 instead of STARTTLS
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // Use 'ssl' for port 465
            $mail->Port = $this->port;
            
            // Set a longer timeout
            $mail->Timeout = 30; // 30 seconds
            
            // SSL Options for certificate verification issues
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $message));
            
            // Add attachments if any
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $mail->addAttachment(
                            $attachment['path'],
                            isset($attachment['name']) ? $attachment['name'] : ''
                        );
                    }
                }
            }
            
            // Send the email
            $result = $mail->send();
            if($this->debugMode){
                $this->logDebug("Email sent to $to: " . ($result ? "SUCCESS" : "FAILED"));
            }
            return $result;
            
        } catch (Exception $e) {
            if($this->debugMode){
                $this->logDebug("Error sending email: " . $e->getMessage());
            }
            if($this->debugMode){
            // Try with basic mail() as fallback
                $this->logDebug("Trying with basic mail() function as fallback");
            }
            return $this->sendBasicEmail($to, $subject, $message);
        }
    }
    
    /**
     * Fallback to basic mail() function if PHPMailer has issues
     */
    private function sendBasicEmail($to, $subject, $message) {
        if($this->debugMode){
            $this->logDebug("Attempting to send email using PHP mail() function");
        }
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $result = mail($to, $subject, $message, implode("\r\n", $headers));
        if($this->debugMode){
            $this->logDebug("Basic mail() result: " . ($result ? "SUCCESS" : "FAILED"));
        }
        
        return $result;
    }
    
    /**
     * Save email to file for testing purposes
     */
    private function saveEmailToFile($to, $subject, $message) {
        $emailDir = __DIR__ . '/../logs/emails';
        if (!is_dir($emailDir)) {
            if (!mkdir($emailDir, 0755, true)) {
                if($this->debugMode){
                    $this->logDebug("Failed to create email directory: $emailDir");
                }
                return;
            }
        }
        
        $filename = $emailDir . '/' . date('Y-m-d_H-i-s') . '_' . md5($to . $subject) . '.html';
        
        $emailContent = "To: $to\n";
        $emailContent .= "Subject: $subject\n";
        $emailContent .= "Date: " . date('r') . "\n\n";
        $emailContent .= $message;
        
        file_put_contents($filename, $emailContent);
        if($this->debugMode){
            $this->logDebug("Email saved to file: $filename");
        }
    }
    
    /**
     * Log debug message
     */
    private function logDebug($message) {
        $logFile = __DIR__ . '/../logs/email_debug.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log("Unable to create logs directory: $logDir");
                return;
            }
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Format an email body with a standard template
     */
    public function formatEmailTemplate($content, $title = '') {
        if (empty($title)) {
            $title = APP_NAME;
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
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
                .content {
                    margin: 20px 0;
                }
                .footer {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                table, th, td {
                    border: 1px solid #ddd;
                }
                th, td {
                    padding: 10px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .rating-5 { color: #1cc88a; font-weight: bold; }
                .rating-4 { color: #36b9cc; font-weight: bold; }
                .rating-3 { color: #f6c23e; font-weight: bold; }
                .rating-2 { color: #e74a3b; font-weight: bold; }
                .rating-1 { color: #e74a3b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . htmlspecialchars($title) . '</h2>
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    <p>This is an automated message from ' . APP_NAME . '. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
