<?php
/**
 * Admin Management Hierarchy
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/User.php';
require_once '../../classes/Employee.php';

// Ensure user is Admin
requireAdmin();

// Initialize user object
$user = new User();
$employee = new Employee();

// Get all managers
$managers = $user->getAllManagers();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_hierarchy') {
            $manager_employee_id = intval($_POST['manager_employee_id']);
            $reports_to_id = !empty($_POST['reports_to_id']) ? intval($_POST['reports_to_id']) : null;
            $level = intval($_POST['level']);
            
            // Insert or update hierarchy
            $db = Database::getInstance();
            
            // Check if entry exists
            $sql = "SELECT id FROM manager_hierarchy WHERE manager_employee_id = ?";
            $existing = $db->single($sql, [$manager_employee_id]);
            
            if ($existing) {
                // Update
                $sql = "UPDATE manager_hierarchy SET reports_to_id = ?, level = ? WHERE manager_employee_id = ?";
                $db->query($sql, [$reports_to_id, $level, $manager_employee_id]);
            } else {
                // Insert
                $sql = "INSERT INTO manager_hierarchy (manager_employee_id, reports_to_id, level) VALUES (?, ?, ?)";
                $db->query($sql, [$manager_employee_id, $reports_to_id, $level]);
            }
            
            $_SESSION['message'] = 'Management hierarchy updated successfully';
            $_SESSION['message_type'] = 'success';
            
            // Redirect to avoid form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Get current hierarchy data
$db = Database::getInstance();
$sql = "SELECT mh.*, 
        CONCAT(e1.first_name, ' ', e1.last_name) as manager_name,
        CONCAT(e2.first_name, ' ', e2.last_name) as reports_to_name
        FROM manager_hierarchy mh
        JOIN employees e1 ON mh.manager_employee_id = e1.id
        LEFT JOIN employees e2 ON mh.reports_to_id = e2.id
        ORDER BY mh.level, e1.first_name";
$hierarchyData = $db->resultset($sql);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Management Hierarchy</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addHierarchyModal">
            <i class="bi bi-plus"></i> Add New Manager to Hierarchy
        </button>
    </div>
</div>

<!-- Hierarchy Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Management Hierarchy</h6>
    </div>
    <div class="card-body">
        <?php if (empty($hierarchyData)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> No management hierarchy defined yet. Please add relationships using the Update Hierarchy button.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="hierarchyTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Reports To</th>
                            <th>Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hierarchyData as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['manager_name']); ?></td>
                                <td><?php echo $item['reports_to_name'] ? htmlspecialchars($item['reports_to_name']) : '<em>Top Level</em>'; ?></td>
                                <td><?php echo $item['level']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-hierarchy" 
                                            data-manager-id="<?php echo $item['manager_employee_id']; ?>"
                                            data-reports-to="<?php echo $item['reports_to_id']; ?>"
                                            data-level="<?php echo $item['level']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
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

<!-- Add/Edit Hierarchy Modal -->
<div class="modal fade" id="addHierarchyModal" tabindex="-1" aria-labelledby="addHierarchyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="update_hierarchy">
                <input type="hidden" name="edit_mode" id="edit_mode" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="addHierarchyModalLabel">Update Management Hierarchy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="manager_employee_id" class="form-label">Manager</label>
                        <select class="form-select" id="manager_employee_id" name="manager_employee_id" required>
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $mgr):
                                $emp_id = $employee->getEmployeeIdByManagerUserId($mgr['id']);
                                if ($emp_id):
                            ?>
                                <option value="<?php echo $emp_id; ?>">
                                    <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>
                                </option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reports_to_id" class="form-label">Reports To</label>
                        <select class="form-select" id="reports_to_id" name="reports_to_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($managers as $mgr):
                                $emp_id = $employee->getEmployeeIdByManagerUserId($mgr['id']);
                                if ($emp_id):
                            ?>
                                <option value="<?php echo $emp_id; ?>">
                                    <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?>
                                </option>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="level" class="form-label">Hierarchy Level</label>
                        <input type="number" class="form-control" id="level" name="level" min="1" value="1" required>
                        <div class="form-text">1 = Top level, 2 = Second level, and so on.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Hierarchy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get existing hierarchy data
    const existingHierarchyManagers = [
        <?php foreach ($hierarchyData as $item): ?>
            { id: '<?php echo $item['manager_employee_id']; ?>', reportsTo: '<?php echo $item['reports_to_id']; ?>' },
        <?php endforeach; ?>
    ];

    // Current manager being edited
    let currentEditingManagerId = null;

    // Initialize edit hierarchy buttons
    document.querySelectorAll('.edit-hierarchy').forEach(function(button) {
        button.addEventListener('click', function() {
            var managerId = this.getAttribute('data-manager-id');
            var reportsTo = this.getAttribute('data-reports-to');
            var level = this.getAttribute('data-level');

            currentEditingManagerId = managerId;
            document.getElementById('edit_mode').value = '1';

            // Set form values
            document.getElementById('manager_employee_id').value = managerId;
            document.getElementById('reports_to_id').value = reportsTo || '';
            document.getElementById('level').value = level;

            // Enable the selected manager in the dropdown (for edit mode)
            const managerSelect = document.getElementById('manager_employee_id');
            for (let i = 0; i < managerSelect.options.length; i++) {
                managerSelect.options[i].disabled = false;
            }

            // Prevent circular references in "reports to" dropdown
            updateReportsToOptions(managerId);

            var editModal = new bootstrap.Modal(document.getElementById('addHierarchyModal'));
            editModal.show();
        });
    });

    // Handle "Add New" button
    document.querySelector('[data-bs-target="#addHierarchyModal"]').addEventListener('click', function() {
        document.getElementById('edit_mode').value = '0';
        currentEditingManagerId = null;

        // Reset form
        document.getElementById('manager_employee_id').value = '';
        document.getElementById('reports_to_id').value = '';
        document.getElementById('level').value = '1';

        // In add mode, disable managers already in the hierarchy
        const managerSelect = document.getElementById('manager_employee_id');
        for (let i = 0; i < managerSelect.options.length; i++) {
            const optionValue = managerSelect.options[i].value;

            // Skip the empty option
            if (!optionValue) continue;

            // Check if this manager is already in the hierarchy
            const isInHierarchy = existingHierarchyManagers.some(manager => manager.id === optionValue);
            managerSelect.options[i].disabled = isInHierarchy;
        }

        // Enable all options in "reports to" dropdown
        const reportsToSelect = document.getElementById('reports_to_id');
        for (let i = 0; i < reportsToSelect.options.length; i++) {
            reportsToSelect.options[i].disabled = false;
        }
    });

    // Handle manager selection to prevent circular references
    document.getElementById('manager_employee_id').addEventListener('change', function() {
        const selectedManagerId = this.value;
        updateReportsToOptions(selectedManagerId);
    });

    // Function to update "reports to" options
    function updateReportsToOptions(managerId) {
        const reportsToSelect = document.getElementById('reports_to_id');

        // Disable the selected manager in the "reports to" dropdown
        for (let i = 0; i < reportsToSelect.options.length; i++) {
            // Disable the current manager to prevent self-reporting
            reportsToSelect.options[i].disabled = (reportsToSelect.options[i].value === managerId);
        }
    }

    // Prevent circular references by validating form submission
    document.querySelector('#addHierarchyModal form').addEventListener('submit', function(e) {
        const managerId = document.getElementById('manager_employee_id').value;
        const reportsToId = document.getElementById('reports_to_id').value;

        if (managerId === reportsToId && reportsToId !== '') {
            e.preventDefault();
            alert('Error: A manager cannot report to themselves.');
            return false;
        }

        // Additional validation could be added here if needed
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
