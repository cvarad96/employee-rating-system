<?php
/**
 * Admin Department Management
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Department.php';

// Ensure user is Admin
requireAdmin();

// Initialize department object
$department = new Department();

// Handle form submission for adding/editing department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle department actions
        if ($_POST['action'] === 'add_department') {
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'created_by' => $_SESSION['user_id'] // Add creator ID for audit
            ];
            
            // Check if department with this name already exists
            if ($department->nameExists($data['name'])) {
                $_SESSION['message'] = 'A department with this name already exists';
                $_SESSION['message_type'] = 'danger';
            } else {
                if ($department->create($data)) {
                    $_SESSION['message'] = 'Department added successfully';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to add department. Name may already exist.';
                    $_SESSION['message_type'] = 'danger';
                }
            }
            
        } elseif ($_POST['action'] === 'edit_department') {
            $data = [
                'id' => $_POST['id'],
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'updated_by' => $_SESSION['user_id'] // Add updater ID for audit
            ];
            
            // Check if department with this name already exists (excluding this department ID)
            if ($department->nameExists($data['name'], $data['id'])) {
                $_SESSION['message'] = 'A department with this name already exists';
                $_SESSION['message_type'] = 'danger';
            } else {
                if ($department->update($data)) {
                    $_SESSION['message'] = 'Department updated successfully';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update department. Name may already exist.';
                    $_SESSION['message_type'] = 'danger';
                }
            }
            
        } elseif ($_POST['action'] === 'delete_department') {
            $id = $_POST['id'];
            
            if ($department->delete($id, $_SESSION['user_id'])) { // Add deleter ID for audit
                $_SESSION['message'] = 'Department deleted successfully';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to delete department. It may still have teams or parameters assigned.';
                $_SESSION['message_type'] = 'danger';
            }
        }
        
        // Rest of the code...
    }
}

// Get all departments (only active ones)
$departments = $department->getAll(); // This now only returns active departments

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Department Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="bi bi-plus"></i> Add Department
        </button>
    </div>
</div>

<?php if (empty($departments)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No departments found. Please add a department to get started.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($departments as $dept): ?>
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold"><?php echo htmlspecialchars($dept['name']); ?></h6>
                        <div class="dropdown no-arrow">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink_<?php echo $dept['id']; ?>"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical text-gray-400"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                                aria-labelledby="dropdownMenuLink_<?php echo $dept['id']; ?>">
                                <div class="dropdown-header">Department Actions:</div>
                                <a class="dropdown-item edit-department" href="#" data-id="<?php echo $dept['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($dept['name']); ?>"
                                   data-description="<?php echo htmlspecialchars($dept['description']); ?>">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                                <a class="dropdown-item text-danger delete-department" href="#" data-id="<?php echo $dept['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($dept['name']); ?>">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($dept['description'])); ?></p>
                        
                        <div class="mt-3">
                            <h6>Rating Parameters</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary mb-3 add-parameter" 
                                    data-department-id="<?php echo $dept['id']; ?>"
                                    data-department-name="<?php echo htmlspecialchars($dept['name']); ?>">
                                <i class="bi bi-plus"></i> Add Parameter
                            </button>
                            
                            <?php 
                            $parameters = $department->getParameters($dept['id']);
                            if (empty($parameters)):
                            ?>
                                <div class="alert alert-warning" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i> No rating parameters defined for this department.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Parameter</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parameters as $param): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($param['name']); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($param['description'])); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-parameter" 
                                                                data-id="<?php echo $param['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($param['name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($param['description']); ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-parameter" 
                                                                data-id="<?php echo $param['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($param['name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_department">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Department Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_department">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDepartmentModalLabel">Delete Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the department: <strong id="delete_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will also delete all rating parameters associated with this department. Teams and employees will remain but will need to be reassigned to another department.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Parameter Modal -->
<div class="modal fade" id="addParameterModal" tabindex="-1" aria-labelledby="addParameterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_parameter">
                <input type="hidden" name="department_id" id="param_department_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="addParameterModalLabel">Add Rating Parameter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Department: <strong id="param_department_name"></strong></p>
                    <div class="mb-3">
                        <label for="param_name" class="form-label">Parameter Name</label>
                        <input type="text" class="form-control" id="param_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="param_description" class="form-label">Description</label>
                        <textarea class="form-control" id="param_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Parameter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Parameter Modal -->
<div class="modal fade" id="editParameterModal" tabindex="-1" aria-labelledby="editParameterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit_parameter">
                <input type="hidden" name="id" id="edit_param_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editParameterModalLabel">Edit Rating Parameter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_param_name" class="form-label">Parameter Name</label>
                        <input type="text" class="form-control" id="edit_param_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_param_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_param_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Parameter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Parameter Modal -->
<div class="modal fade" id="deleteParameterModal" tabindex="-1" aria-labelledby="deleteParameterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="delete_parameter">
                <input type="hidden" name="id" id="delete_param_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteParameterModalLabel">Delete Rating Parameter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the parameter: <strong id="delete_param_name"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will also delete all ratings associated with this parameter.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Parameter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add any JavaScript for the departments page here
document.addEventListener('DOMContentLoaded', function() {
    // Edit Department
    document.querySelectorAll('.edit-department').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var description = this.getAttribute('data-description');
            
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            
            var editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
            editModal.show();
        });
    });
    
    // Delete Department
    document.querySelectorAll('.delete-department').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
            deleteModal.show();
        });
    });
    
    // Add Parameter
    document.querySelectorAll('.add-parameter').forEach(function(button) {
        button.addEventListener('click', function() {
            var departmentId = this.getAttribute('data-department-id');
            var departmentName = this.getAttribute('data-department-name');
            
            document.getElementById('param_department_id').value = departmentId;
            document.getElementById('param_department_name').textContent = departmentName;
            
            var addParameterModal = new bootstrap.Modal(document.getElementById('addParameterModal'));
            addParameterModal.show();
        });
    });
    
    // Edit Parameter
    document.querySelectorAll('.edit-parameter').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var description = this.getAttribute('data-description');
            
            document.getElementById('edit_param_id').value = id;
            document.getElementById('edit_param_name').value = name;
            document.getElementById('edit_param_description').value = description;
            
            var editParameterModal = new bootstrap.Modal(document.getElementById('editParameterModal'));
            editParameterModal.show();
        });
    });
    
    // Delete Parameter
    document.querySelectorAll('.delete-parameter').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            
            document.getElementById('delete_param_id').value = id;
            document.getElementById('delete_param_name').textContent = name;
            
            var deleteParameterModal = new bootstrap.Modal(document.getElementById('deleteParameterModal'));
            deleteParameterModal.show();
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
