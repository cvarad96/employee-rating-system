<?php
/**
 * Admin Team Management
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Team.php';
require_once '../../classes/Department.php';
require_once '../../classes/User.php';
require_once '../../classes/Employee.php';

// Ensure user is Admin
requireAdmin();

// Initialize team object
$team = new Team();
$department = new Department();
$user = new User();
$employee = new Employee();

// Handle form submission for adding/editing team
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle team actions
        if ($_POST['action'] === 'add_team') {
            $data = [
                'name' => $_POST['name'],
                'department_id' => $_POST['department_id'],
                'manager_id' => $_POST['manager_id'],
                'created_by' => $_SESSION['user_id'] // Add creator ID for audit
            ];
            
            // Check if team with this name already exists
            if ($team->nameExists($data['name'], $data['department_id'])) {
                $_SESSION['message'] = 'A team with this name already exists in this department';
                $_SESSION['message_type'] = 'danger';
            } else {
                if ($team->create($data)) {
                    $_SESSION['message'] = 'Team added successfully';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to add team';
                    $_SESSION['message_type'] = 'danger';
                }
            }
            
        } elseif ($_POST['action'] === 'edit_team') {
            $data = [
                'id' => $_POST['id'],
                'name' => $_POST['name'],
                'department_id' => $_POST['department_id'],
                'manager_id' => $_POST['manager_id'],
                'updated_by' => $_SESSION['user_id'] // Add updater ID for audit
            ];
            
            // Check if team with this name already exists (excluding this team ID)
            if ($team->nameExists($data['name'], $data['department_id'], $data['id'])) {
                $_SESSION['message'] = 'A team with this name already exists in this department';
                $_SESSION['message_type'] = 'danger';
            } else {
                if ($team->update($data)) {
                    $_SESSION['message'] = 'Team updated successfully';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update team';
                    $_SESSION['message_type'] = 'danger';
                }
            }
            
        } elseif ($_POST['action'] === 'delete_team') {
            $id = $_POST['id'];
            
            if ($team->delete($id, $_SESSION['user_id'])) { // Add deleter ID for audit
                $_SESSION['message'] = 'Team deleted successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to delete team';
                $_SESSION['message_type'] = 'danger';
            }
        }
        
        // Redirect to refresh page and avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get all teams, departments, and managers (only include active ones)
$teams = $team->getAll(); // Now only gets active teams by default
$departments = $department->getAll(); // Now only gets active departments by default
$managers = $user->getAllManagers();

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Team Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamModal">
            <i class="bi bi-plus"></i> Add Team
        </button>
    </div>
</div>

<?php if (empty($teams)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No teams found. Please add a team to get started.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Team List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="teamsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Team Name</th>
                            <th>Department</th>
                            <th>Manager</th>
                            <th>Members</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['name']); ?></td>
                                <td><?php echo htmlspecialchars($t['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($t['manager_name']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $t['member_count']; ?></span>
                                    <?php if ($t['member_count'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-link view-members" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($t['name']); ?>">
                                        View
                                    </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-team" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($t['name']); ?>"
                                            data-department-id="<?php echo $t['department_id']; ?>"
                                            data-manager-id="<?php echo $t['manager_id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-team" 
                                            data-id="<?php echo $t['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($t['name']); ?>">
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

<!-- Add Team Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1" aria-labelledby="addTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_team">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeamModalLabel">Add Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="manager_id" class="form-label">Manager</label>
                        <select class="form-select" id="manager_id" name="manager_id" required>
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $mgr): ?>
                                <option value="<?php echo $mgr['id']; ?>">
                                    <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Team Modal -->
<div class="modal fade" id="editTeamModal" tabindex="-1" aria-labelledby="editTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_team">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTeamModalLabel">Edit Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Team Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">Department</label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_manager_id" class="form-label">Manager</label>
                        <select class="form-select" id="edit_manager_id" name="manager_id" required>
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $mgr): ?>
                                <option value="<?php echo $mgr['id']; ?>">
                                    <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Team Modal -->
<div class="modal fade" id="deleteTeamModal" tabindex="-1" aria-labelledby="deleteTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_team">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteTeamModalLabel">Delete Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the team: <strong id="delete_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will also remove all employees from this team. Employees who are not assigned to another team will not be visible in the system.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Team Members Modal -->
<div class="modal fade" id="viewMembersModal" tabindex="-1" aria-labelledby="viewMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMembersModalLabel">Members of <span id="team_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="members_list">
                    <!-- Members will be loaded here via AJAX -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Add any JavaScript for the teams page here
document.addEventListener('DOMContentLoaded', function() {
    // Edit Team
    document.querySelectorAll('.edit-team').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var departmentId = this.getAttribute('data-department-id');
            var managerId = this.getAttribute('data-manager-id');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_department_id').value = departmentId;
            document.getElementById('edit_manager_id').value = managerId;
            
            var editModal = new bootstrap.Modal(document.getElementById('editTeamModal'));
            editModal.show();
        });
    });
    
    // Delete Team
    document.querySelectorAll('.delete-team').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteTeamModal'));
            deleteModal.show();
        });
    });
    
    // View Team Members
    document.querySelectorAll('.view-members').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            
            document.getElementById('team_name').textContent = name;
            
            // For simplicity, instead of AJAX, we'll just show a placeholder
            // In a real application, you'd use fetch() or XMLHttpRequest to load the members
            
            var membersHTML = '<div class="list-group">';
            <?php foreach ($teams as $t): ?>
                <?php $teamMembers = $employee->getByTeamId($t['id']); ?>
                if (id == <?php echo $t['id']; ?>) {
                    <?php if (empty($teamMembers)): ?>
                    membersHTML = '<div class="alert alert-info">No members in this team.</div>';
                    <?php else: ?>
                    <?php foreach ($teamMembers as $mem): ?>
                    membersHTML += '<div class="list-group-item">' +
                                '<div class="d-flex w-100 justify-content-between">' +
                                '<h5 class="mb-1"><?php echo htmlspecialchars($mem['first_name'] . ' ' . $mem['last_name']); ?></h5>' +
                                '</div>' +
                                '<p class="mb-1">Position: <?php echo htmlspecialchars($mem['position']); ?></p>' +
                                '<small>Email: <?php echo htmlspecialchars($mem['email']); ?></small>' +
                                '</div>';
                    <?php endforeach; ?>
                    <?php endif; ?>
                }
            <?php endforeach; ?>
            membersHTML += '</div>';
            
            document.getElementById('members_list').innerHTML = membersHTML;
            
            var viewMembersModal = new bootstrap.Modal(document.getElementById('viewMembersModal'));
            viewMembersModal.show();
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
