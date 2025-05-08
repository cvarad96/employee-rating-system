<?php
/**
 * Admin Management Hierarchy - Revised to use user IDs
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

// Get all managers and admin users
$managers = $user->getAllManagers();
$admins = $user->getAllAdmins();

// Combine all users who can be in the hierarchy
$allUsers = array_merge($managers, $admins);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_hierarchy') {
            $manager_user_id = intval($_POST['manager_user_id']);
            $reports_to_user_id = !empty($_POST['reports_to_user_id']) ? intval($_POST['reports_to_user_id']) : null;
            $level = intval($_POST['level']);
            
            // Insert or update hierarchy
            $db = Database::getInstance();
            
            // Check if entry exists
            $sql = "SELECT id FROM manager_hierarchy WHERE manager_user_id = ?";
            $existing = $db->single($sql, [$manager_user_id]);
            
            if ($existing) {
                // Update
                $sql = "UPDATE manager_hierarchy SET reports_to_user_id = ?, level = ? WHERE manager_user_id = ?";
                $db->query($sql, [$reports_to_user_id, $level, $manager_user_id]);
            } else {
                // Insert
                $sql = "INSERT INTO manager_hierarchy (manager_user_id, reports_to_user_id, level) VALUES (?, ?, ?)";
                $db->query($sql, [$manager_user_id, $reports_to_user_id, $level]);
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
        CONCAT(u1.first_name, ' ', u1.last_name) as manager_name,
        u1.role as manager_role,
        CONCAT(u2.first_name, ' ', u2.last_name) as reports_to_name,
        u2.role as reports_to_role
        FROM manager_hierarchy mh
        JOIN users u1 ON mh.manager_user_id = u1.id
        LEFT JOIN users u2 ON mh.reports_to_user_id = u2.id
        ORDER BY mh.level, u1.first_name";
$hierarchyData = $db->resultset($sql);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Management Hierarchy</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addHierarchyModal">
            <i class="bi bi-plus"></i> Add User to Hierarchy
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
                <i class="bi bi-info-circle me-2"></i> No management hierarchy defined yet. Please add relationships using the Add User button.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="hierarchyTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Reports To</th>
                            <th>Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hierarchyData as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['manager_name']); ?></td>
                                <td><span class="badge bg-<?php echo $item['manager_role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                    <?php echo ucfirst($item['manager_role']); ?>
                                </span></td>
                                <td>
                                    <?php if ($item['reports_to_name']): ?>
                                        <?php echo htmlspecialchars($item['reports_to_name']); ?>
                                        <span class="badge bg-<?php echo $item['reports_to_role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($item['reports_to_role']); ?>
                                        </span>
                                    <?php else: ?>
                                        <em>Top Level</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['level']; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-hierarchy" 
                                            data-manager-id="<?php echo $item['manager_user_id']; ?>"
                                            data-reports-to="<?php echo $item['reports_to_user_id']; ?>"
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
                        <label for="manager_user_id" class="form-label">User</label>
                        <select class="form-select" id="manager_user_id" name="manager_user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($allUsers as $usr): ?>
                                <option value="<?php echo $usr['id']; ?>" data-role="<?php echo $usr['role']; ?>">
                                    <?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']); ?>
                                    (<?php echo ucfirst($usr['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reports_to_user_id" class="form-label">Reports To</label>
                        <select class="form-select" id="reports_to_user_id" name="reports_to_user_id">
                            <option value="">None (Top Level)</option>
                            <?php foreach ($allUsers as $usr): ?>
                                <option value="<?php echo $usr['id']; ?>" data-role="<?php echo $usr['role']; ?>">
                                    <?php echo htmlspecialchars($usr['first_name'] . ' ' . $usr['last_name']); ?>
                                    (<?php echo ucfirst($usr['role']); ?>)
                                </option>
                            <?php endforeach; ?>
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
    const existingHierarchyUsers = [
        <?php foreach ($hierarchyData as $item): ?>
            { id: '<?php echo $item['manager_user_id']; ?>', reportsTo: '<?php echo $item['reports_to_user_id']; ?>' },
        <?php endforeach; ?>
    ];

    // Current user being edited
    let currentEditingUserId = null;

    // Initialize edit hierarchy buttons
    document.querySelectorAll('.edit-hierarchy').forEach(function(button) {
        button.addEventListener('click', function() {
            var managerId = this.getAttribute('data-manager-id');
            var reportsTo = this.getAttribute('data-reports-to');
            var level = this.getAttribute('data-level');

            currentEditingUserId = managerId;
            document.getElementById('edit_mode').value = '1';

            // Set form values
            document.getElementById('manager_user_id').value = managerId;
            document.getElementById('reports_to_user_id').value = reportsTo || '';
            document.getElementById('level').value = level;

            // Enable the selected user in the dropdown (for edit mode)
            const managerSelect = document.getElementById('manager_user_id');
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
        currentEditingUserId = null;

        // Reset form
        document.getElementById('manager_user_id').value = '';
        document.getElementById('reports_to_user_id').value = '';
        document.getElementById('level').value = '1';

        // In add mode, disable users already in the hierarchy
        const managerSelect = document.getElementById('manager_user_id');
        for (let i = 0; i < managerSelect.options.length; i++) {
            const optionValue = managerSelect.options[i].value;

            // Skip the empty option
            if (!optionValue) continue;

            // Check if this user is already in the hierarchy
            const isInHierarchy = existingHierarchyUsers.some(user => user.id === optionValue);
            managerSelect.options[i].disabled = isInHierarchy;
        }

        // Enable all options in "reports to" dropdown
        const reportsToSelect = document.getElementById('reports_to_user_id');
        for (let i = 0; i < reportsToSelect.options.length; i++) {
            reportsToSelect.options[i].disabled = false;
        }
    });

    // Handle user selection to prevent circular references
    document.getElementById('manager_user_id').addEventListener('change', function() {
        const selectedUserId = this.value;
        updateReportsToOptions(selectedUserId);
    });

    // Function to update "reports to" options
    function updateReportsToOptions(userId) {
        const reportsToSelect = document.getElementById('reports_to_user_id');

        // Disable the selected user in the "reports to" dropdown
        for (let i = 0; i < reportsToSelect.options.length; i++) {
            // Disable the current user to prevent self-reporting
            reportsToSelect.options[i].disabled = (reportsToSelect.options[i].value === userId);
        }
    }

    // Prevent circular references by validating form submission
    document.querySelector('#addHierarchyModal form').addEventListener('submit', function(e) {
        const userId = document.getElementById('manager_user_id').value;
        const reportsToId = document.getElementById('reports_to_user_id').value;

        if (userId === reportsToId && reportsToId !== '') {
            e.preventDefault();
            alert('Error: A user cannot report to themselves.');
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
