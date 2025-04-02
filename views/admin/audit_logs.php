<?php
/**
 * Admin Audit Logs
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/AuditLog.php';
require_once '../../classes/User.php';

// Ensure user is Admin
requireAdmin();

// Initialize audit log object
$auditLog = new AuditLog();

// Get filters
$filters = [];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $filters['user_id'] = intval($_GET['user_id']);
}

if (isset($_GET['action']) && !empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}

if (isset($_GET['entity_type']) && !empty($_GET['entity_type'])) {
    $filters['entity_type'] = $_GET['entity_type'];
}

if (isset($_GET['entity_id']) && !empty($_GET['entity_id'])) {
    $filters['entity_id'] = intval($_GET['entity_id']);
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'] . ' 00:00:00';
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'] . ' 23:59:59';
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$totalRecords = $auditLog->getCount($filters);
$totalPages = ceil($totalRecords / $limit);

// Get audit logs
$logs = $auditLog->getAll($filters, $limit, $offset);

// Get users for filter dropdown
$user = new User();
$users = $user->getAll();

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Audit Logs</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Filter Audit Logs</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="user_id" class="form-label">User</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo (isset($filters['user_id']) && $filters['user_id'] == $u['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['username'] . ' (' . $u['first_name'] . ' ' . $u['last_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All Actions</option>
                    <option value="create" <?php echo (isset($filters['action']) && $filters['action'] == 'create') ? 'selected' : ''; ?>>Create</option>
                    <option value="update" <?php echo (isset($filters['action']) && $filters['action'] == 'update') ? 'selected' : ''; ?>>Update</option>
                    <option value="delete" <?php echo (isset($filters['action']) && $filters['action'] == 'delete') ? 'selected' : ''; ?>>Delete</option>
                    <option value="rate" <?php echo (isset($filters['action']) && $filters['action'] == 'rate') ? 'selected' : ''; ?>>Rate</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="entity_type" class="form-label">Entity Type</label>
                <select class="form-select" id="entity_type" name="entity_type">
                    <option value="">All Types</option>
                    <option value="department" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] == 'department') ? 'selected' : ''; ?>>Department</option>
                    <option value="team" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] == 'team') ? 'selected' : ''; ?>>Team</option>
                    <option value="employee" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] == 'employee') ? 'selected' : ''; ?>>Employee</option>
                    <option value="parameter" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] == 'parameter') ? 'selected' : ''; ?>>Parameter</option>
                    <option value="rating" <?php echo (isset($filters['entity_type']) && $filters['entity_type'] == 'rating') ? 'selected' : ''; ?>>Rating</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
            </div>
            
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Audit Logs Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">System Audit Logs</h6>
        <span class="badge bg-primary"><?php echo $totalRecords; ?> records found</span>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> No audit logs found for the selected filters.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                <td>
                                    <?php 
                                    $badgeClass = 'secondary';
                                    switch($log['action']) {
                                        case 'create': $badgeClass = 'success'; break;
                                        case 'update': $badgeClass = 'primary'; break;
                                        case 'delete': $badgeClass = 'danger'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst($log['entity_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['description'], 0, 100) . (strlen($log['description']) > 100 ? '...' : '')); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-log-details" data-bs-toggle="modal" data-bs-target="#logDetailsModal" data-id="<?php echo $log['id']; ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Audit logs pagination">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php
                    // Show a limited number of page links
                    $startPage = max(1, $page - 2);
                    $endPage = min($startPage + 4, $totalPages);
                    
                    // Adjust start page if we're near the end
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logDetailsModalLabel">Audit Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
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
document.addEventListener('DOMContentLoaded', function() {
    // View log details
    const logDetailsModal = document.getElementById('logDetailsModal');
    const logDetailsContent = document.getElementById('logDetailsContent');
    
    logDetailsModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const logId = button.getAttribute('data-id');
        
        // Reset content
        logDetailsContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Simulated AJAX call
        // In a real app, you would do an AJAX call to get the log details
        setTimeout(function() {
            // Find the log data from the page
            const detailsHTML = getLogDetailsHTML(logId);
            logDetailsContent.innerHTML = detailsHTML;
        }, 300);
    });
    
    function getLogDetailsHTML(logId) {
        <?php foreach ($logs as $index => $log): ?>
        if (<?php echo $log['id']; ?> == logId) {
            return `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Date/Time:</strong> <?php echo formatDateTime($log['created_at']); ?></p>
                        <p><strong>User:</strong> <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['username'] . ')'); ?></p>
                        <p><strong>IP Address:</strong> <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Action:</strong> <?php echo ucfirst($log['action']); ?></p>
                        <p><strong>Entity Type:</strong> <?php echo ucfirst($log['entity_type']); ?></p>
                        <p><strong>Entity ID:</strong> <?php echo $log['entity_id'] ?? 'N/A'; ?></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <p><strong>Description:</strong></p>
                        <div class="p-3 bg-light rounded mb-3"><?php echo nl2br(htmlspecialchars($log['description'])); ?></div>
                    </div>
                </div>
                <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                <div class="row">
                    <?php if (!empty($log['old_values'])): ?>
                    <div class="col-md-6">
                        <p><strong>Old Values:</strong></p>
                        <div class="p-3 bg-light rounded" style="overflow: auto; max-height: 300px;">
                            <pre class="mb-0"><?php 
                                // Try to format JSON
                                $oldValues = $log['old_values'];
                                if ($oldValues) {
                                    try {
                                        $jsonData = json_decode($oldValues, true);
                                        if ($jsonData) {
                                            echo htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT));
                                        } else {
                                            echo htmlspecialchars($oldValues);
                                        }
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($oldValues);
                                    }
                                } else {
                                    echo "N/A";
                                }
                            ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($log['new_values'])): ?>
                    <div class="col-md-6">
                        <p><strong>New Values:</strong></p>
                        <div class="p-3 bg-light rounded" style="overflow: auto; max-height: 300px;">
                            <pre class="mb-0"><?php 
                                // Try to format JSON
                                $newValues = $log['new_values'];
                                if ($newValues) {
                                    try {
                                        $jsonData = json_decode($newValues, true);
                                        if ($jsonData) {
                                            echo htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT));
                                        } else {
                                            echo htmlspecialchars($newValues);
                                        }
                                    } catch (Exception $e) {
                                        echo htmlspecialchars($newValues);
                                    }
                                } else {
                                    echo "N/A";
                                }
                            ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            `;
        }
        <?php endforeach; ?>
        
        return '<div class="alert alert-danger">Log details not found</div>';
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
