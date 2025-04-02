<?php
/**
 * Change Password Page
 */

// Include necessary files
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'classes/User.php';

// Ensure user is logged in
requireLogin();

// Initialize variables
$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Process change password form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        $user = new User();
        $result = $user->changePassword($user_id, $current_password, $new_password);
        
        if ($result === true) {
            $success = 'Your password has been changed successfully';
        } else {
            $error = $result; // Error message from changePassword method
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo $success; ?>
                        </div>
                    <?php else: ?>
                        <form method="post" id="changePasswordForm">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="progress mb-3" style="height: 5px;">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: 0%" id="passwordStrength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                                <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                        
                        <script>
                            // Password strength checker
                            document.getElementById('new_password').addEventListener('input', function() {
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
                            
                            // Form validation
                            document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                                var newPassword = document.getElementById('new_password').value;
                                var confirmPassword = document.getElementById('confirm_password').value;
                                
                                if (newPassword !== confirmPassword) {
                                    e.preventDefault();
                                    alert('New passwords do not match');
                                }
                            });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
