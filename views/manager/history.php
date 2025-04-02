<?php
/**
 * Manager Rating History
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Rating.php';

// Ensure user is Manager
requireManager();

// Get manager ID
$manager_id = $_SESSION['user_id'];

// Initialize objects
$employee = new Employee();
$rating = new Rating();

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get selected week and year (default to current)
$selectedWeek = isset($_GET['week']) ? intval($_GET['week']) : $currentWeek;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Get employee if specified
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// Get ratings
if ($employee_id) {
    // Verify employee belongs to manager
    if (!$employee->belongsToManager($employee_id, $manager_id)) {
        $_SESSION['message'] = 'You do not have permission to view this employee';
        $_SESSION['message_type'] = 'danger';
        header('Location: history.php');
        exit;
    }
    
    // Get employee ratings
    $ratings_list = $rating->getByEmployeeId($employee_id);
    $employeeInfo = $employee->getById($employee_id);
} else {
    // Get ratings for the selected week and year
    $ratings_list = $rating->getByWeekYear($selectedWeek, $selectedYear, $manager_id);
}

// Get all employees for this manager for the selection dropdown
$employees = $employee->getAll($manager_id);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php if ($employee_id): ?>
            Rating History - <?php echo htmlspecialchars($employeeInfo['first_name'] . ' ' . $employeeInfo['last_name']); ?>
        <?php else: ?>
            Rating History - <?php echo formatWeekRange($selectedWeek, $selectedYear); ?>
        <?php endif; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="ratings.php" class="btn btn-sm btn-outline-secondary">Rate Employees</a>
        </div>
    </div>
</div>

<!-- Selection Form -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Filter Ratings</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <form method="get" class="row g-3">
                    <div class="col-md-12">
                        <label for="employee_id" class="form-label">View History for Employee</label>
                        <div class="input-group">
                            <select class="form-select" id="employee_id" name="employee_id">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_id == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['team_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">View</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="col-md-6 mb-3">
                <form method="get" class="row g-3">
                    <div class="col-md-5">
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
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (empty($ratings_list)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No ratings found for the selected criteria.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Rating History</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="ratingsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <?php if (!$employee_id): ?>
                            <th>Employee</th>
                            <th>Team</th>
                            <?php else: ?>
                            <th>Week</th>
                            <th>Year</th>
                            <?php endif; ?>
                            <th>Parameter</th>
                            <th>Rating</th>
                            <th>Comments</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ratings_list as $r): ?>
                            <tr>
                                <?php if (!$employee_id): ?>
                                <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($r['team_name']); ?></td>
                                <?php else: ?>
                                <td><?php echo formatWeekRange($r['rating_week'], $r['rating_year']); ?></td>
                                <td><?php echo $r['rating_year']; ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($r['parameter_name']); ?></td>
                                <td><?php echo generateStarRating($r['rating']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($r['comments'])); ?></td>
                                <td><?php echo formatDateTime($r['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($employee_id): ?>
    <!-- Show average ratings by parameter -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Average Ratings by Parameter</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $parameterAverages = $rating->getAverageByParameter($employee_id);
                    if (!empty($parameterAverages)):
                    ?>
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
                    <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        No average ratings available.
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
                    <?php 
                    $ratingTrend = $rating->getAverageByEmployee($employee_id);
                    if (!empty($ratingTrend)):
                    ?>
                    <canvas id="ratingTrendChart"></canvas>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var ctx = document.getElementById('ratingTrendChart').getContext('2d');
                        var chartData = {
                            labels: [
                                <?php 
                                $labels = [];
                                foreach ($ratingTrend as $rt) {
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
                                    foreach ($ratingTrend as $rt) {
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
                    <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        No rating trend data available.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Add any JavaScript for the history page here
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit when employee changes
    document.getElementById('employee_id').addEventListener('change', function() {
        if (this.value) {
            this.closest('form').submit();
        }
    });
    
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
