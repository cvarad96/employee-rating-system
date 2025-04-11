<?php
/**
 * Admin Manager Management
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Team.php';

// Ensure user is Admin
requireAdmin();

// Initialize user object
$user = new User();
$team = new Team();

// Update the action handling section to add reset_password action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle manager actions
        if ($_POST['action'] === 'add_manager') {
            $data = [
                'username' => $_POST['username'],
                'password' => $_POST['password'],
                'email' => $_POST['email'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'role' => 'manager',
                'created_by' => $_SESSION['user_id'] // Add the creator ID for audit
            ];

            if ($user->create($data)) {
                $_SESSION['message'] = 'Manager added successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to add manager. Username or email may already exist.';
                $_SESSION['message_type'] = 'danger';
            }

        } elseif ($_POST['action'] === 'edit_manager') {
            $data = [
                'id' => $_POST['id'],
                'username' => $_POST['username'],
                'email' => $_POST['email'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'role' => 'manager',
                'updated_by' => $_SESSION['user_id'] // Add the updater ID for audit
            ];

            // Add password only if provided
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            if ($user->update($data)) {
                $_SESSION['message'] = 'Manager updated successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update manager. Username or email may already exist.';
                $_SESSION['message_type'] = 'danger';
            }

        } elseif ($_POST['action'] === 'delete_manager') {
            $id = $_POST['id'];

            if ($user->delete($id, $_SESSION['user_id'])) { // Add the deleter ID for audit
                $_SESSION['message'] = 'Manager deleted successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to delete manager. They may still be assigned to teams.';
                $_SESSION['message_type'] = 'danger';
            }
        } elseif ($_POST['action'] === 'reset_password') {
            // Handle password reset
            $user_id = $_POST['id'];
            $new_password = $_POST['new_password'];

            if (empty($new_password) || strlen($new_password) < 8) {
                $_SESSION['message'] = 'Password must be at least 8 characters long';
                $_SESSION['message_type'] = 'danger';
            } else {
                if ($user->adminResetPassword($user_id, $new_password, $_SESSION['user_id'])) {
                    $_SESSION['message'] = 'User password has been reset successfully';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to reset user password';
                    $_SESSION['message_type'] = 'danger';
                }
            }
        }

        // Redirect to refresh page and avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all managers
$managers = $user->getAllManagers();

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manager Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addManagerModal">
            <i class="bi bi-plus"></i> Add Manager
        </button>
    </div>
</div>

<?php if (empty($managers)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No managers found. Please add a manager to get started.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Manager List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="managersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Teams</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managers as $mgr): ?>
                            <?php 
                            $managerTeams = $team->getByManagerId($mgr['id']);
                            $teamCount = count($managerTeams);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($mgr['username']); ?></td>
                                <td><?php echo htmlspecialchars($mgr['email']); ?></td>
                                <td>
                                    <?php if ($teamCount > 0): ?>
                                        <span class="badge bg-primary"><?php echo $teamCount; ?> team(s)</span>
                                        <button type="button" class="btn btn-sm btn-link view-teams" 
                                                data-id="<?php echo $mgr['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No teams</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($mgr['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-manager" 
                                            data-id="<?php echo $mgr['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($mgr['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($mgr['email']); ?>"
                                            data-first-name="<?php echo htmlspecialchars($mgr['first_name']); ?>"
                                            data-last-name="<?php echo htmlspecialchars($mgr['last_name']); ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning reset-password" 
                                            data-id="<?php echo $mgr['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>">
                                        <i class="bi bi-key"></i> Reset Password
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-manager" 
                                            data-id="<?php echo $mgr['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Manager Modal -->
<div class="modal fade" id="addManagerModal" tabindex="-1" aria-labelledby="addManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_manager">
                <div class="modal-header">
                    <h5 class="modal-title" id="addManagerModalLabel">Add Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Manager Modal -->
<div class="modal fade" id="editManagerModal" tabindex="-1" aria-labelledby="editManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_manager">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editManagerModalLabel">Edit Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Admin Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_password_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to reset the password for: <strong id="reset_password_name"></strong></p>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will override the user's current password. They will need to use the new password to login.
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="progress mb-3" style="height: 5px;">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 0%" id="passwordStrength"></div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="resetPasswordSubmit">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Manager Modal -->
<div class="modal fade" id="deleteManagerModal" tabindex="-1" aria-labelledby="deleteManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_manager">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteManagerModalLabel">Delete Manager</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the manager: <strong id="delete_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> You must reassign or delete all teams assigned to this manager before deleting them.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Teams Modal -->
<div class="modal fade" id="viewTeamsModal" tabindex="-1" aria-labelledby="viewTeamsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTeamsModalLabel">Teams for <span id="manager_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="teams_list">
                    <!-- Teams will be loaded here via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="teams.php" class="btn btn-primary">Manage Teams</a>
            </div>
        </div>
    </div>
</div>

<script>
// Combined JavaScript for the managers page
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded - initializing manager page JS');
    
    try {
        // Edit Manager
        document.querySelectorAll('.edit-manager').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var username = this.getAttribute('data-username');
                var email = this.getAttribute('data-email');
                var firstName = this.getAttribute('data-first-name');
                var lastName = this.getAttribute('data-last-name');
                
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_first_name').value = firstName;
                document.getElementById('edit_last_name').value = lastName;
                document.getElementById('edit_password').value = '';
                
                var editModal = new bootstrap.Modal(document.getElementById('editManagerModal'));
                editModal.show();
            });
        });
        
        // Delete Manager
        document.querySelectorAll('.delete-manager').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');
                
                document.getElementById('delete_id').value = id;
                document.getElementById('delete_name').textContent = name;
                
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteManagerModal'));
                deleteModal.show();
            });
        });
        
        // View Teams
        document.querySelectorAll('.view-teams').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');
                
                document.getElementById('manager_name').textContent = name;
                
                // Teams loading logic remains the same
                var teamsHTML = '<div class="list-group">';
                // This part will be generated by PHP in the actual file
                teamsHTML += '</div>';
                
                document.getElementById('teams_list').innerHTML = teamsHTML;
                
                var viewTeamsModal = new bootstrap.Modal(document.getElementById('viewTeamsModal'));
                viewTeamsModal.show();
            });
        });
        
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });

        // Password strength checker
        const newPasswordField = document.getElementById('new_password');
        if (newPasswordField) {
            newPasswordField.addEventListener('input', function() {
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
        }

        // Reset Password Form Validation
        const resetPasswordBtn = document.getElementById('resetPasswordSubmit');
        if (resetPasswordBtn) {
            resetPasswordBtn.addEventListener('click', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match');
                }
            });
        }

        // Reset Password button click handler
        document.querySelectorAll('.reset-password').forEach(function(button) {
            button.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var name = this.getAttribute('data-name');

                document.getElementById('reset_password_id').value = id;
                document.getElementById('reset_password_name').textContent = name;

                // Clear password fields
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';

                // Reset strength meter
                var strengthBar = document.getElementById('passwordStrength');
                strengthBar.style.width = '0%';
                strengthBar.className = 'progress-bar bg-danger';

                var resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
                resetPasswordModal.show();
            });
        });
        
        console.log('Manager page JS initialized successfully');
    } catch (error) {
        console.error('Error initializing manager page JS:', error);
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
