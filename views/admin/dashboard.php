<?php
/**
 * Admin Dashboard
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Department.php';
require_once '../../classes/Team.php';
require_once '../../classes/User.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Rating.php';

requireAdmin();

// Get counts for dashboard
$department = new Department();
$team = new Team();
$user = new User();
$employee = new Employee();
$rating = new Rating();

$departments = $department->getAll();
$departmentCount = count($departments);

$teams = $team->getAll();
$teamCount = count($teams);

$managers = $user->getAllManagers();
$managerCount = count($managers);

$employees = $employee->getAll();
$employeeCount = count($employees);

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Admin Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="reports.php" class="btn btn-sm btn-outline-secondary">View Reports</a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card h-100 border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Departments</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $departmentCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="departments.php" class="text-primary">Manage <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card h-100 border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Teams</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $teamCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-diagram-3 fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="teams.php" class="text-success">Manage <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card h-100 border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Managers</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $managerCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="managers.php" class="text-info">Manage <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card h-100 border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="reports.php" class="text-warning">View Reports <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold">Departments</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Teams</th>
                                <th>Parameters</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                <td><?php echo $dept['team_count']; ?></td>
                                <td><?php echo $dept['parameter_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold">Managers</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Manager</th>
                                <th>Email</th>
                                <th>Teams</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($managers as $mgr): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($mgr['email']); ?></td>
                                <td>
                                    <?php 
                                    $managerTeams = $team->getByManagerId($mgr['id']);
                                    echo count($managerTeams);
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add any JavaScript for the dashboard here
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any scripts needed
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
