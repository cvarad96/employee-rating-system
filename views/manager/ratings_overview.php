<?php
/**
 * Manager Ratings Overview (Combined History and Hierarchy)
 * Updated to work with user IDs in hierarchy
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

// Get employee if specified for employee history
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
$employeeInfo = null;

// View mode: 'hierarchy' (default) or 'employee' (for detailed view)
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'hierarchy';

// The rest of the file remains largely the same, just update the hierarchy access parts
// to use the new methods that work with user IDs

// Handle employee detail view
if ($employee_id && $viewMode == 'employee') {
    // Verify employee belongs to manager's hierarchy
    $hierarchyEmployees = $employee->getAllHierarchyEmployees($manager_id);
    $employeeIds = array_column($hierarchyEmployees, 'id');
    
    if (!in_array($employee_id, $employeeIds)) {
        $_SESSION['message'] = 'You do not have permission to view this employee';
        $_SESSION['message_type'] = 'danger';
        header('Location: ratings_overview.php');
        exit;
    }
    
    // Get employee details
    $employeeInfo = $employee->getById($employee_id);
    if (!$employeeInfo) {
        $_SESSION['message'] = 'Employee not found';
        $_SESSION['message_type'] = 'danger';
        header('Location: ratings_overview.php');
        exit;
    }
    
    // Get team info
    $teamInfo = $employee->getTeam($employee_id);
    
    // Get ratings for this employee
    $employeeRatings = $rating->getByEmployeeId($employee_id);
    
    // Filter ratings for selected week/year
    $weekRatings = array_filter($employeeRatings, function($r) use ($selectedWeek, $selectedYear) {
        return $r['rating_week'] == $selectedWeek && $r['rating_year'] == $selectedYear;
    });
    
    // Get historical average ratings
    $historicalRatings = $rating->getAverageByEmployee($employee_id);
    
    // Get ratings by parameter
    $parameterAverages = $rating->getAverageByParameter($employee_id);
    
    // Check if employee belongs directly to this manager (can rate) or indirectly (view only)
    $canRate = $employee->belongsToManager($employee_id, $manager_id);
} else {
    // Hierarchy view
    $hierarchyEmployees = $employee->getAllHierarchyEmployees($manager_id);

    // Group employees by direct/indirect reporting relationship
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
}

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php if ($viewMode == 'employee' && $employeeInfo): ?>
            Rating Details - <?php echo htmlspecialchars($employeeInfo['first_name'] . ' ' . $employeeInfo['last_name']); ?>
        <?php else: ?>
            Ratings Overview - Week <?php echo $selectedWeek; ?>, <?php echo $selectedYear; ?>
        <?php endif; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <?php if ($viewMode == 'employee'): ?>
                <a href="ratings_overview.php?week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Overview
                </a>
                <?php if ($canRate): ?>
                <a href="ratings.php?employee_id=<?php echo $employee_id; ?>&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil"></i> Rate Employee
                </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="ratings.php" class="btn btn-sm btn-outline-secondary">Rate Employees</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Week Selection -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Select Rating Period</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <?php if ($viewMode == 'employee'): ?>
            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
            <input type="hidden" name="view" value="employee">
            <?php endif; ?>

            <div class="col-md-4">
                <label for="week" class="form-label">Week</label>
                <select class="form-select" id="week" name="week">
                    <?php echo getAllWeekOptions($selectedWeek, $selectedYear); ?>
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

<?php if ($viewMode == 'employee' && $employeeInfo): ?>
    <!-- Employee Detail View -->
    
    <!-- Employee Info -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Employee Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($employeeInfo['first_name'] . ' ' . $employeeInfo['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($employeeInfo['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($employeeInfo['phone'] ?: 'N/A'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($employeeInfo['position']); ?></p>
                    <p><strong>Team:</strong> <?php echo htmlspecialchars($teamInfo ? $teamInfo['name'] : 'N/A'); ?></p>
                    <p><strong>Access Level:</strong> <?php echo $canRate ? '<span class="badge bg-success">Direct Report</span>' : '<span class="badge bg-info">Indirect Report</span>'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Ratings for Selected Week -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Ratings for Week <?php echo $selectedWeek; ?>, <?php echo $selectedYear; ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($weekRatings)): ?>
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i> No ratings found for this employee in the selected week.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Rated By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weekRatings as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['parameter_name']); ?></td>
                                    <td><?php echo generateStarRating($r['rating']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($r['comments'] ?: 'No comments')); ?></td>
                                    <td><?php echo htmlspecialchars($r['rated_by_name']); ?></td>
                                    <td><?php echo formatDateTime($r['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Parameter Averages and Historical Chart -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Average Ratings by Parameter</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($parameterAverages)): ?>
                        <div class="alert alert-info">No average ratings available yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Average Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parameterAverages as $pa): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pa['parameter_name']); ?></td>
                                        <td><?php echo generateStarRating($pa['average_rating']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Rating Trend</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($historicalRatings)): ?>
                        <div class="alert alert-info">No historical data available yet.</div>
                    <?php else: ?>
                        <canvas id="ratingTrendChart" height="250"></canvas>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var ctx = document.getElementById('ratingTrendChart').getContext('2d');
                            var chartData = {
                                labels: [
                                    <?php 
                                    $labels = [];
                                    foreach ($historicalRatings as $rt) {
                                        $labels[] = "\"Week " . $rt['rating_week'] . ", " . $rt['rating_year'] . "\"";
                                    }
                                    echo implode(',', array_reverse($labels));
                                    ?>
                                ],
                                datasets: [{
                                    label: 'Average Rating',
                                    data: [
                                        <?php 
                                        $data = [];
                                        foreach ($historicalRatings as $rt) {
                                            $data[] = round($rt['average_rating'], 2);
                                        }
                                        echo implode(',', array_reverse($data));
                                        ?>
                                    ],
                                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 2,
                                    tension: 0.1
                                }]
                            };
                            
                            var myChart = new Chart(ctx, {
                                type: 'line',
                                data: chartData,
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 5,
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Hierarchy Overview -->
    
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
                                        <a href="?employee_id=<?php echo $emp['id']; ?>&view=employee&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-primary">
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
                                        <a href="?employee_id=<?php echo $emp['id']; ?>&view=employee&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-primary">
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
<?php endif; ?>

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
