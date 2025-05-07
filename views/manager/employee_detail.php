<?php
/**
 * Employee Rating Details View
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Rating.php';
require_once '../../classes/User.php';

// Ensure user is Manager
requireManager();

// Get manager ID and employee ID
$manager_id = $_SESSION['user_id'];
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// Initialize objects
$employee = new Employee();
$rating = new Rating();
$user = new User();

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get selected week and year (default to current)
$selectedWeek = isset($_GET['week']) ? intval($_GET['week']) : $currentWeek;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Check if employee is in manager's hierarchy
$hierarchyEmployees = $employee->getAllHierarchyEmployees($manager_id);
$employeeIds = array_column($hierarchyEmployees, 'id');

if (!in_array($employee_id, $employeeIds)) {
    $_SESSION['message'] = 'You do not have permission to view this employee';
    $_SESSION['message_type'] = 'danger';
    header('Location: hierarchy.php');
    exit;
}

// Get employee details
$employeeDetails = $employee->getById($employee_id);
if (!$employeeDetails) {
    $_SESSION['message'] = 'Employee not found';
    $_SESSION['message_type'] = 'danger';
    header('Location: hierarchy.php');
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

// Check if employee belongs directly to this manager (can rate) or indirectly (view only)
$canRate = $employee->belongsToManager($employee_id, $manager_id);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Employee Rating Details</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="hierarchy.php" class="btn btn-sm btn-outline-secondary">Back to Hierarchy</a>
            <?php if ($canRate): ?>
            <a href="ratings.php?employee_id=<?php echo $employee_id; ?>&week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-outline-primary">
                Rate Employee
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Employee Info -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Employee Information</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($employeeDetails['first_name'] . ' ' . $employeeDetails['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($employeeDetails['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($employeeDetails['phone'] ?: 'N/A'); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Position:</strong> <?php echo htmlspecialchars($employeeDetails['position']); ?></p>
                <p><strong>Team:</strong> <?php echo htmlspecialchars($teamInfo ? $teamInfo['name'] : 'N/A'); ?></p>
                <p><strong>Access Level:</strong> <?php echo $canRate ? '<span class="badge bg-success">Direct Report</span>' : '<span class="badge bg-info">Indirect Report</span>'; ?></p>
            </div>
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
            <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
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

<!-- Historical Performance Chart -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Historical Performance</h6>
    </div>
    <div class="card-body">
        <?php if (empty($historicalRatings)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> No historical data available for this employee.
            </div>
        <?php else: ?>
            <canvas id="performanceChart" height="200"></canvas>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('performanceChart').getContext('2d');
                var chartData = {
                    labels: [
                        <?php 
                        $labels = [];
                        foreach ($historicalRatings as $hr) {
                            $labels[] = "'Week " . $hr['rating_week'] . ", " . $hr['rating_year'] . "'";
                        }
                        echo implode(',', array_reverse($labels));
                        ?>
                    ],
                    datasets: [{
                        label: 'Average Rating',
                        data: [
                            <?php 
                            $data = [];
                            foreach ($historicalRatings as $hr) {
                                $data[] = round($hr['average_rating'], 2);
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
