<?php
/**
 * Manager Dashboard
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Team.php';
require_once '../../classes/User.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Rating.php';

// Ensure user is Manager
requireManager();

// Get manager ID
$manager_id = $_SESSION['user_id'];

// Get teams and employees for this manager
$team = new Team();
$employee = new Employee();
$rating = new Rating();

$teams = $team->getByManagerId($manager_id);
$teamCount = count($teams);

$employees = $employee->getAll($manager_id);
$employeeCount = count($employees);

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get pending ratings
$pendingRatings = $rating->getPendingRatings($manager_id, $currentWeek, $currentYear);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manager Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="ratings.php" class="btn btn-sm btn-outline-secondary">Rate Employees</a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Teams</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $teamCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-diagram-3 fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeCount; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="employees.php" class="text-success">Manage <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Current Week</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatWeekRange($currentWeek, $currentYear); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-week fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="history.php" class="text-warning">Rating History <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Rating Status -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Current Week Rating Status</h6>
    </div>
    <div class="card-body">
        <?php if (empty($pendingRatings)): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> You have no pending ratings for this week.
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> You have pending ratings for the current week. Please complete them by Friday.
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Team</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRatings as $pending): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pending['first_name'] . ' ' . $pending['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($pending['team_name']); ?></td>
                                <td>
                                    <?php 
                                    $completionPercentage = 0;
                                    if ($pending['total_parameters'] > 0) {
                                        $completionPercentage = ($pending['rated_parameters'] / $pending['total_parameters']) * 100;
                                    }
                                    
                                    $badgeClass = 'danger';
                                    if ($completionPercentage == 100) {
                                        $badgeClass = 'success';
                                    } elseif ($completionPercentage > 0) {
                                        $badgeClass = 'warning';
                                    }
                                    ?>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $badgeClass; ?>" role="progressbar" 
                                             style="width: <?php echo $completionPercentage; ?>%" 
                                             aria-valuenow="<?php echo $completionPercentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo round($completionPercentage); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="ratings.php?employee_id=<?php echo $pending['id']; ?>" class="btn btn-sm btn-primary">
                                        <?php if ($completionPercentage == 0): ?>
                                            Rate Now
                                        <?php elseif ($completionPercentage == 100): ?>
                                            Review
                                        <?php else: ?>
                                            Complete
                                        <?php endif; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Teams List -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold">My Teams</h6>
    </div>
    <div class="card-body">
        <?php if (empty($teams)): ?>
            <div class="alert alert-info" role="alert">
                You don't have any teams assigned yet. Please contact the CEO to get assigned to teams.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($teams as $t): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($t['name']); ?></h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Department: <?php echo htmlspecialchars($t['department_name']); ?></p>
                                <p class="card-text">Members: <?php echo $t['member_count']; ?></p>
                                
                                <?php
                                // Get team average rating for current week if any
                                $teamRating = $rating->getAverageByTeam($t['id'], $currentWeek, $currentYear);
                                if ($teamRating && isset($teamRating['average_rating'])):
                                ?>
                                <p class="card-text">
                                    Average Rating: 
                                    <?php echo generateStarRating($teamRating['average_rating']); ?>
                                </p>
                                <?php endif; ?>
                                
                                <a href="employees.php?team_id=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary">
                                    View Members
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
