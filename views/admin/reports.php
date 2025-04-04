<?php
/**
 * Admin Reports
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Department.php';
require_once '../../includes/functions.php'; // Add this line
require_once '../../classes/Team.php';
require_once '../../classes/Rating.php';

// Ensure user is Admin
requireAdmin();

// Initialize objects
$employee = new Employee();
$department = new Department();
$team = new Team();
$rating = new Rating();

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get selected week and year (default to current)
$selectedWeek = isset($_GET['week']) ? intval($_GET['week']) : $currentWeek;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Get selected department and team if any
$departmentId = isset($_GET['department_id']) && !empty($_GET['department_id']) ? intval($_GET['department_id']) : null;
$teamId = isset($_GET['team_id']) && !empty($_GET['team_id']) ? intval($_GET['team_id']) : null;
$employeeId = isset($_GET['employee_id']) && !empty($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

// Get all departments and teams for filtering
$departments = $department->getAll();
$allTeams = $team->getAll();

// Get filtered teams if department is selected
$filteredTeams = $departmentId ? $department->getTeams($departmentId) : [];

// Get employee if selected
$selectedEmployee = $employeeId ? $employee->getById($employeeId) : null;

// Get all ratings based on filters
$ratingsData = $rating->getByWeekYear($selectedWeek, $selectedYear);

// Filter ratings by department if selected
if ($departmentId) {
    $filteredRatings = [];
    foreach ($ratingsData as $r) {
        if (isset($r['department_id']) && $r['department_id'] == $departmentId) {
            $filteredRatings[] = $r;
        }
    }
    $ratingsData = $filteredRatings;
}

// Filter ratings by team if selected
if ($teamId) {
    $filteredRatings = [];
    foreach ($ratingsData as $r) {
        if (isset($r['team_id']) && $r['team_id'] == $teamId) {
            $filteredRatings[] = $r;
        }
    }
    $ratingsData = $filteredRatings;
}

// Filter ratings by employee if selected
if ($employeeId) {
    $filteredRatings = [];
    foreach ($ratingsData as $r) {
        if (isset($r['employee_id']) && $r['employee_id'] == $employeeId) {
            $filteredRatings[] = $r;
        }
    }
    $ratingsData = $filteredRatings;
}

// Get unique employees and parameters
$uniqueEmployees = [];
$uniqueParameters = [];
$employeeAverages = [];

foreach ($ratingsData as $r) {
    if (!isset($uniqueEmployees[$r['employee_id']])) {
        $uniqueEmployees[$r['employee_id']] = [
            'id' => $r['employee_id'],
            'name' => isset($r['employee_name']) ? $r['employee_name'] : 'Unknown',
            'team_name' => isset($r['team_name']) ? $r['team_name'] : 'Unknown',
            'department_name' => isset($r['department_name']) ? $r['department_name'] : 'Unknown'
        ];
    }
    
    if (!isset($uniqueParameters[$r['parameter_id']])) {
        $uniqueParameters[$r['parameter_id']] = isset($r['parameter_name']) ? $r['parameter_name'] : 'Unknown';
    }
    
    // Calculate averages
    if (!isset($employeeAverages[$r['employee_id']])) {
        $employeeAverages[$r['employee_id']] = [
            'total' => $r['rating'],
            'count' => 1
        ];
    } else {
        $employeeAverages[$r['employee_id']]['total'] += $r['rating'];
        $employeeAverages[$r['employee_id']]['count']++;
    }
}

// Calculate final averages
foreach ($employeeAverages as $id => $avg) {
    $employeeAverages[$id]['average'] = $avg['total'] / $avg['count'];
}

// Sort employees by average rating (descending)
if (!empty($uniqueEmployees)) {
    uasort($uniqueEmployees, function($a, $b) use ($employeeAverages) {
        $avgA = isset($employeeAverages[$a['id']]['average']) ? $employeeAverages[$a['id']]['average'] : 0;
        $avgB = isset($employeeAverages[$b['id']]['average']) ? $employeeAverages[$b['id']]['average'] : 0;
        return $avgB <=> $avgA;
    });
}

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Rating Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
            <a href="annual_report.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-calendar-range"></i> Annual Report
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Filter Reports</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label for="week" class="form-label">Week</label>
                <select class="form-select" id="week" name="week">
                    <?php echo getAdminWeekOptions($selectedWeek); ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php echo getYearOptions($selectedYear); ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="department_id" class="form-label">Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo ($departmentId == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="team_id" class="form-label">Team</label>
                <select class="form-select" id="team_id" name="team_id">
                    <option value="">All Teams</option>
                    <?php if ($departmentId && !empty($filteredTeams)): ?>
                        <?php foreach ($filteredTeams as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($teamId == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($allTeams as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($teamId == $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($ratingsData)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> No ratings found for the selected filters.
    </div>
<?php else: ?>
    <!-- Rating Summary -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Employee Ratings Overview</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Team</th>
                                    <th>Department</th>
                                    <th>Average Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uniqueEmployees as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['team_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                        <td>
                                            <?php 
                                            $avgRating = isset($employeeAverages[$emp['id']]['average']) ? $employeeAverages[$emp['id']]['average'] : 0;
                                            echo generateStarRating($avgRating);
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>&employee_id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-search"></i> Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Rating Distribution</h6>
                </div>
                <div class="card-body">
                    <canvas id="ratingDistributionChart"></canvas>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var ctx = document.getElementById('ratingDistributionChart').getContext('2d');
                        
                        // Count ratings by value (1-5)
                        var ratingCounts = [0, 0, 0, 0, 0];
                        <?php foreach ($ratingsData as $r): ?>
                            <?php if (isset($r['rating']) && $r['rating'] >= 1 && $r['rating'] <= 5): ?>
                                ratingCounts[<?php echo $r['rating']-1; ?>]++;
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        var myChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                                datasets: [{
                                    label: 'Number of Ratings',
                                    data: ratingCounts,
                                    backgroundColor: [
                                        'rgba(255, 99, 132, 0.2)',
                                        'rgba(255, 159, 64, 0.2)',
                                        'rgba(255, 205, 86, 0.2)',
                                        'rgba(75, 192, 192, 0.2)',
                                        'rgba(54, 162, 235, 0.2)'
                                    ],
                                    borderColor: [
                                        'rgb(255, 99, 132)',
                                        'rgb(255, 159, 64)',
                                        'rgb(255, 205, 86)',
                                        'rgb(75, 192, 192)',
                                        'rgb(54, 162, 235)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($employeeId && $selectedEmployee): ?>
    <!-- Employee Detail -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">Rating Details for <?php echo htmlspecialchars($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']); ?></h6>
            <a href="?week=<?php echo $selectedWeek; ?>&year=<?php echo $selectedYear; ?>&department_id=<?php echo $departmentId; ?>&team_id=<?php echo $teamId; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Summary
            </a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0">Employee Information</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']); ?></p>
                            <p><strong>Position:</strong> <?php echo htmlspecialchars($selectedEmployee['position']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedEmployee['email']); ?></p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($selectedEmployee['phone'] ? $selectedEmployee['phone'] : 'N/A'); ?></p>
                            
                            <?php 
                            $employeeTeam = $employee->getTeam($employeeId);
                            if ($employeeTeam):
                            ?>
                            <p><strong>Team:</strong> <?php echo htmlspecialchars($employeeTeam['name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0">Rating Summary</h6>
                        </div>
                        <div class="card-body">
                            <h5>Week <?php echo $selectedWeek; ?>, <?php echo $selectedYear; ?></h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $employeeRatings = [];
                                        foreach ($ratingsData as $r) {
                                            if (isset($r['employee_id']) && $r['employee_id'] == $employeeId) {
                                                $employeeRatings[] = $r;
                                            }
                                        }
                                        
                                        foreach ($employeeRatings as $er):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($er['parameter_name']); ?></td>
                                            <td><?php echo generateStarRating($er['rating']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php 
                            // Get historical ratings
                            $ratingHistory = $rating->getAverageByEmployee($employeeId);
                            if (count($ratingHistory) > 1):
                            ?>
                            <h5 class="mt-4">Historical Rating Trend</h5>
                            <canvas id="employeeRatingTrendChart"></canvas>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var ctx = document.getElementById('employeeRatingTrendChart').getContext('2d');
                                var chartData = {
                                    labels: [
                                        <?php 
                                        $labels = [];
                                        foreach ($ratingHistory as $rt) {
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
                                            foreach ($ratingHistory as $rt) {
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
            
            <?php if (!empty($employeeRatings)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0">Rating Comments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
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
                                <?php foreach ($employeeRatings as $er): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($er['parameter_name']); ?></td>
                                    <td><?php echo generateStarRating($er['rating']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($er['comments'] ? $er['comments'] : '')); ?></td>
                                    <td><?php echo htmlspecialchars(isset($er['rated_by_name']) ? $er['rated_by_name'] : 'Unknown'); ?></td>
                                    <td><?php echo formatDateTime($er['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Add any JavaScript for the reports page here
document.addEventListener('DOMContentLoaded', function() {
    // Department filter changes - update team options
    document.getElementById('department_id').addEventListener('change', function() {
        var departmentId = this.value;
        var teamSelect = document.getElementById('team_id');
        
        // Clear team selection
        teamSelect.innerHTML = '<option value="">All Teams</option>';
        
        if (departmentId) {
            // If a department is selected, only show teams for that department
            <?php foreach ($departments as $dept): ?>
                if (departmentId == <?php echo $dept['id']; ?>) {
                    <?php 
                    $deptTeams = $department->getTeams($dept['id']);
                    foreach ($deptTeams as $t):
                    ?>
                    var option = document.createElement('option');
                    option.value = <?php echo $t['id']; ?>;
                    option.textContent = '<?php echo htmlspecialchars($t['name']); ?>';
                    teamSelect.appendChild(option);
                    <?php endforeach; ?>
                }
            <?php endforeach; ?>
        } else {
            // If no department selected, show all teams
            <?php foreach ($allTeams as $t): ?>
            var option = document.createElement('option');
            option.value = <?php echo $t['id']; ?>;
            option.textContent = '<?php echo htmlspecialchars($t['name']); ?>';
            teamSelect.appendChild(option);
            <?php endforeach; ?>
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
