<?php
/**
 * Forgot Password Page
 */

// Include necessary files
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/PasswordReset.php';

// Initialize variables
$error = '';
$success = '';

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $user = new User();
        $userId = $user->getUserIdByEmail($email);
        
        if ($userId) {
            $passwordReset = new PasswordReset();
            $passwordReset->cleanupExpiredTokens(); // Clean up old tokens
            
            $userData = $passwordReset->createToken($userId);
            
            if ($userData && $passwordReset->sendResetEmail($userData)) {
                $success = 'A password reset link has been sent to your email. Please check your inbox.';
            } else {
                $error = 'Error sending password reset email. Please try again later.';
            }
        } else {
            // To prevent email enumeration, always show success message
            $success = 'If the email address exists in our system, a password reset link will be sent.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }
        .form-forgot-password {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        .form-forgot-password .form-floating:focus-within {
            z-index: 2;
        }
        .form-forgot-password input[type="email"] {
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="text-center">
    <main class="form-forgot-password">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <h1 class="h3 mb-3 fw-normal">Forgot Password</h1>
            <p class="mb-3">Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required autofocus>
                <label for="email">Email address</label>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">Send Reset Link</button>
            
            <p class="mt-3">
                <a href="index.php">Back to Login</a>
            </p>
            
            <p class="mt-5 mb-3 text-muted">&copy; <?php echo date('Y'); ?></p>
        </form>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
