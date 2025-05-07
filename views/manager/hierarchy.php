<?php
/**
 * Manager Hierarchy View
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Department.php';
require_once '../../classes/Team.php';
require_once '../../classes/Rating.php';
require_once '../../classes/User.php';

// Ensure user is Manager
requireManager();

// Get manager ID
$manager_id = $_SESSION['user_id'];

// Initialize objects
$employee = new Employee();
$department = new Department();
$team = new Team();
$rating = new Rating();
$user = new User();

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get selected week and year (default to current)
$selectedWeek = isset($_GET['week']) ? intval($_GET['week']) : $currentWeek;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Get all employees in the hierarchy
$hierarchyEmployees = $employee->getAllHierarchyEmployees($manager_id);

// Group employees by direct/indirect and by team
$directEmployees = [];
$indirectEmployees = [];

foreach ($hierarchyEmployees as $emp) {
    if (isset($emp['is_indirect']) && $emp['is_indirect']) {
        $indirectEmployees[] = $emp;
    } else {
        $directEmployees[] = $emp;
    }
}

// Get ratings for the selected week/year
$hierarchyRatings = $rating->getHierarchyRatings($manager_id, $selectedWeek, $selectedYear);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Hierarchy View - Week <?php echo $selectedWeek; ?>, <?php echo $selectedYear; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="ratings.php" class="btn btn-sm btn-outline-secondary">Rate Employees</a>
            <a href="history.php" class="btn btn-sm btn-outline-secondary">Rating History</a>
        </div>
    </div>
</div>

<!-- Week Selection -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Select Week</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="week" class="form-label">Week</label>
                <select class="form-select" id="week" name="week">
                    <?php echo getWeekOptions($selectedWeek); ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php echo getYearOptions($selectedYear); ?>
                </select>
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">View Ratings</button>
            </div>
        </form>
    </div>
</div>

<!-- Direct Reports -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Direct Reports (<?php echo count($directEmployees); ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (empty($directEmployees)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> No direct reports found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Position</th>
                            <th>Team</th>
                            <th>Average Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($directEmployees as $emp): 
                            // Calculate average rating for this employee
                            $empRatings = array_filter($hierarchyRatings, function($r) use ($emp) {
                                return $r['employee_id'] == $emp['id'];
                            });
                            
                            $avgRating = 0;
                            $ratingCount = count($empRatings);
                            
                            if ($ratingCount > 0) {
                                $totalRating = array_reduce($empRatings, function($carry, $r) {
                                    return $carry + $r['rating'];
                                }, 0);
                                $avgRating = $totalRating / $ratingCount;
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo htmlspecialchars($emp['team_name']); ?></td>
                                <td>
                                    <?php if ($ratingCount > 0): ?>
                                        <?php echo generateStarRating($avgRating); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No ratings</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="ratings.php?employee_id=<?php echo $emp['id']; ?>&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-pencil"></i> Rate
                                    </a>
                                    <a href="employee_detail.php?employee_id=<?php echo $emp['id']; ?>&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View Details
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

<!-- Indirect Reports -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Indirect Reports (<?php echo count($indirectEmployees); ?>)</h6>
    </div>
    <div class="card-body">
        <?php if (empty($indirectEmployees)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> No indirect reports found.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Position</th>
                            <th>Team</th>
                            <th>Direct Manager</th>
                            <th>Average Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indirectEmployees as $emp): 
                            // Calculate average rating for this employee
                            $empRatings = array_filter($hierarchyRatings, function($r) use ($emp) {
                                return $r['employee_id'] == $emp['id'];
                            });
                            
                            $avgRating = 0;
                            $ratingCount = count($empRatings);
                            
                            if ($ratingCount > 0) {
                                $totalRating = array_reduce($empRatings, function($carry, $r) {
                                    return $carry + $r['rating'];
                                }, 0);
                                $avgRating = $totalRating / $ratingCount;
                            }
                            
                            // Get direct manager name
                            $directManager = "Unknown";
                            if (isset($emp['reporting_manager_id'])) {
                                $mgr = $user->getById($emp['reporting_manager_id']);
                                if ($mgr) {
                                    $directManager = $mgr['first_name'] . ' ' . $mgr['last_name'];
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                <td><?php echo htmlspecialchars($emp['team_name']); ?></td>
                                <td><?php echo htmlspecialchars($directManager); ?></td>
                                <td>
                                    <?php if ($ratingCount > 0): ?>
                                        <?php echo generateStarRating($avgRating); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No ratings</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="employee_detail.php?employee_id=<?php echo $emp['id']; ?>&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View Details
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit when week or year changes
    document.getElementById('week').addEventListener('change', function() {
        document.getElementById('year').closest('form').submit();
    });
    
    document.getElementById('year').addEventListener('change', function() {
        this.closest('form').submit();
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
