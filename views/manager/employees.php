<?php
/**
 * Manager Employees Management
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Team.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Rating.php';

// Ensure user is Manager
requireManager();

// Get manager ID
$manager_id = $_SESSION['user_id'];

// Initialize objects
$team = new Team();
$employee = new Employee();
$rating = new Rating();

// Get teams for this manager
$teams = $team->getByManagerId($manager_id);

// Filter by team if specified
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
$current_team = null;

if ($team_id) {
    // Verify this team belongs to the manager
    if (!$team->isOwnedByManager($team_id, $manager_id)) {
        $_SESSION['message'] = 'You do not have permission to view this team';
        $_SESSION['message_type'] = 'danger';
        header('Location: employees.php');
        exit;
    }
    
    $current_team = $team->getById($team_id);
    $employees_list = $employee->getByTeamId($team_id);
} else {
    // Get all employees for this manager
    $employees_list = $employee->getAll($manager_id);
}

// Handle form submission for adding/editing employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle employee actions
        if ($_POST['action'] === 'add_employee') {
            $data = [
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'position' => $_POST['position'],
                'created_by' => $manager_id,
                'team_id' => $_POST['team_id']
            ];
            
            if ($employee->create($data)) {
                $_SESSION['message'] = 'Employee added successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to add employee. Email may already be in use.';
                $_SESSION['message_type'] = 'danger';
            }
            
        } elseif ($_POST['action'] === 'edit_employee') {
            $employee_id = intval($_POST['id']);
            
            // Verify employee belongs to manager
            if (!$employee->belongsToManager($employee_id, $manager_id)) {
                $_SESSION['message'] = 'You do not have permission to edit this employee';
                $_SESSION['message_type'] = 'danger';
                header('Location: employees.php');
                exit;
            }
            
            $data = [
                'id' => $employee_id,
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'position' => $_POST['position'],
                'team_id' => $_POST['team_id']
            ];
            
            if ($employee->update($data)) {
                $_SESSION['message'] = 'Employee updated successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update employee. Email may already be in use.';
                $_SESSION['message_type'] = 'danger';
            }
            
        } elseif ($_POST['action'] === 'delete_employee') {
            $employee_id = intval($_POST['id']);
            
            // Verify employee belongs to manager
            if (!$employee->belongsToManager($employee_id, $manager_id)) {
                $_SESSION['message'] = 'You do not have permission to delete this employee';
                $_SESSION['message_type'] = 'danger';
                header('Location: employees.php');
                exit;
            }
            
            if ($employee->delete($employee_id)) {
                $_SESSION['message'] = 'Employee deleted successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to delete employee.';
                $_SESSION['message_type'] = 'danger';
            }
        }
        
        // Redirect to refresh page and avoid form resubmission
        if ($team_id) {
            header('Location: employees.php?team_id=' . $team_id);
        } else {
            header('Location: employees.php');
        }
        exit;
    }
}

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php if ($current_team): ?>
            Employees - <?php echo htmlspecialchars($current_team['name']); ?>
        <?php else: ?>
            All Employees
        <?php endif; ?>
    </h1>
<!--
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="bi bi-plus"></i> Add Employee
        </button>
    </div>
-->
</div>

<!-- Team Filter -->
<?php if (count($teams) > 1): ?>
<div class="mb-4">
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Filter by Team</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <select id="team_filter" class="form-select" onchange="filterByTeam(this.value)">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($team_id == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?> 
                                (<?php echo $t['member_count']; ?> members)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($employees_list)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No employees found. Please add employees to your team.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Employee List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="employeesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Team</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees_list as $emp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                <td><?php echo isset($emp['team_name']) ? htmlspecialchars($emp['team_name']) : 'N/A'; ?></td>
                                <td>
                                    <a href="ratings.php?employee_id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-star"></i> Rate
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_employee">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="position" class="form-label">Position</label>
                        <input type="text" class="form-control" id="position" name="position" required>
                    </div>
                    <div class="mb-3">
                        <label for="team_id" class="form-label">Team</label>
                        <select class="form-select" id="team_id" name="team_id" required>
                            <option value="">Select Team</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo ($team_id == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_employee">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_position" class="form-label">Position</label>
                        <input type="text" class="form-control" id="edit_position" name="position" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_team_id" class="form-label">Team</label>
                        <select class="form-select" id="edit_team_id" name="team_id" required>
                            <option value="">Select Team</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Employee Modal -->
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1" aria-labelledby="deleteEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_employee">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEmployeeModalLabel">Delete Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the employee: <strong id="delete_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will also delete all ratings associated with this employee.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Filter function
function filterByTeam(teamId) {
    if (teamId) {
        window.location.href = 'employees.php?team_id=' + teamId;
    } else {
        window.location.href = 'employees.php';
    }
}

// Add any other JavaScript for the employees page here
document.addEventListener('DOMContentLoaded', function() {
    // Initialize edit employee buttons
    document.querySelectorAll('.edit-employee').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var firstName = this.getAttribute('data-first-name');
            var lastName = this.getAttribute('data-last-name');
            var email = this.getAttribute('data-email');
            var phone = this.getAttribute('data-phone');
            var position = this.getAttribute('data-position');
            var teamId = this.getAttribute('data-team-id');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_position').value = position;
            
            if (teamId) {
                document.getElementById('edit_team_id').value = teamId;
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
            editModal.show();
        });
    });
    
    // Initialize delete employee buttons
    document.querySelectorAll('.delete-employee').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
            deleteModal.show();
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
