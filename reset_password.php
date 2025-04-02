<?php
/**
 * Reset Password Page
 */

// Include necessary files
require_once 'config/config.php';
require_once 'classes/User.php';
require_once 'classes/PasswordReset.php';

// Initialize variables
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$userData = null;

// Validate token
if (empty($token)) {
    $error = 'Invalid or missing token. Please request a new password reset link.';
} else {
    $passwordReset = new PasswordReset();
    $userData = $passwordReset->validateToken($token);
    
    if (!$userData) {
        $error = 'Invalid or expired token. Please request a new password reset link.';
    }
}

// Process reset password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Please enter a password';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Update password
        $user = new User();
        if ($user->updatePassword($userData['user_id'], $password)) {
            // Delete used token
            $passwordReset->deleteToken($token);
            
            $success = 'Your password has been reset successfully. You can now login with your new password.';
        } else {
            $error = 'Error resetting password. Please try again later.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
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
        .form-reset-password {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        .form-reset-password .form-floating:focus-within {
            z-index: 2;
        }
        .form-reset-password input[type="password"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        .form-reset-password input[type="password"] + input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        .password-strength {
            height: 5px;
            margin-top: 10px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="text-center">
    <main class="form-reset-password">
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
            <p>
                <a href="index.php" class="btn btn-primary">Go to Login</a>
            </p>
        <?php elseif ($error && !$userData): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <p>
                <a href="forgot_password.php" class="btn btn-primary">Request New Reset Link</a>
            </p>
        <?php else: ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?token=' . htmlspecialchars($token); ?>" id="resetPasswordForm">
                <h1 class="h3 mb-3 fw-normal">Reset Password</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required autofocus>
                    <label for="password">New Password</label>
                </div>
                
                <div class="progress password-strength">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 0%" id="passwordStrength"></div>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    <label for="confirm_password">Confirm Password</label>
                </div>
                
                <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">Reset Password</button>
                
                <p class="mt-5 mb-3 text-muted">&copy; <?php echo date('Y'); ?></p>
            </form>
            
            <script>
                // Password strength checker
                document.getElementById('password').addEventListener('input', function() {
                    var password = this.value;
                    var strength = 0;
                    
                    if (password.length >= 8) strength += 25;
                    if (password.match(/[a-z]+/)) strength += 25;
                    if (password.match(/[A-Z]+/)) strength += 25;
                    if (password.match(/[0-9]+/) || password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength += 25;
                    
                    var strengthBar = document.getElementById('passwordStrength');
                    strengthBar.style.width = strength + '%';
                    
                    if (strength < 25) {
                        strengthBar.className = 'progress-bar bg-danger';
                    } else if (strength < 50) {
                        strengthBar.className = 'progress-bar bg-warning';
                    } else if (strength < 75) {
                        strengthBar.className = 'progress-bar bg-info';
                    } else {
                        strengthBar.className = 'progress-bar bg-success';
                    }
                });
                
                // Password confirmation validation
                document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
                    var password = document.getElementById('password').value;
                    var confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match');
                    }
                });
            </script>
        <?php endif; ?>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
