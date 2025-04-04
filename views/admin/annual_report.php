<?php
/**
 * Admin Annual Cumulative Report
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Department.php';
require_once '../../classes/Team.php';
require_once '../../classes/Rating.php';
require_once '../../includes/functions.php'; // Add this line


// Ensure user is Admin
requireAdmin();

// Initialize objects
$employee = new Employee();
$department = new Department();
$team = new Team();
$rating = new Rating();

// Get selected year (default to current)
$currentYear = getCurrentYear();
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Get selected department and team if any
$departmentId = isset($_GET['department_id']) && !empty($_GET['department_id']) ? intval($_GET['department_id']) : null;
$teamId = isset($_GET['team_id']) && !empty($_GET['team_id']) ? intval($_GET['team_id']) : null;

// Get all departments and teams for filtering
$departments = $department->getAll();
$allTeams = $team->getAll();

// Get filtered teams if department is selected
$filteredTeams = $departmentId ? $department->getTeams($departmentId) : [];

// Function to get annual data for the year
function getAnnualData($rating, $selectedYear, $departmentId = null, $teamId = null) {
    $db = $rating->getDb();
    
    $sql = "SELECT 
                e.id as employee_id,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                t.name as team_name,
                d.name as department_name,
                AVG(er.rating) as average_rating,
                COUNT(DISTINCT er.rating_week) as weeks_rated
            FROM employee_ratings er
            JOIN employees e ON er.employee_id = e.id
            JOIN team_members tm ON e.id = tm.employee_id
            JOIN teams t ON tm.team_id = t.id
            JOIN departments d ON t.department_id = d.id
            WHERE er.rating_year = ?";
    
    $params = [$selectedYear];
    
    if ($departmentId) {
        $sql .= " AND d.id = ?";
        $params[] = $departmentId;
    }
    
    if ($teamId) {
        $sql .= " AND t.id = ?";
        $params[] = $teamId;
    }
    
    $sql .= " GROUP BY e.id
              ORDER BY average_rating DESC";
    
    return $db->resultset($sql, $params);
}

// Function to get parameter averages
function getParameterAverages($rating, $selectedYear, $departmentId = null, $teamId = null) {
    $db = $rating->getDb();
    
    $sql = "SELECT 
                rp.id as parameter_id,
                rp.name as parameter_name,
                AVG(er.rating) as average_rating,
                COUNT(er.id) as total_ratings
            FROM employee_ratings er
            JOIN rating_parameters rp ON er.parameter_id = rp.id
            JOIN employees e ON er.employee_id = e.id
            JOIN team_members tm ON e.id = tm.employee_id
            JOIN teams t ON tm.team_id = t.id
            JOIN departments d ON t.department_id = d.id
            WHERE er.rating_year = ?";
    
    $params = [$selectedYear];
    
    if ($departmentId) {
        $sql .= " AND d.id = ?";
        $params[] = $departmentId;
    }
    
    if ($teamId) {
        $sql .= " AND t.id = ?";
        $params[] = $teamId;
    }
    
    $sql .= " GROUP BY rp.id
              ORDER BY average_rating DESC";
    
    return $db->resultset($sql, $params);
}

// Function to get monthly averages
function getMonthlyAverages($rating, $selectedYear, $departmentId = null, $teamId = null) {
    $db = $rating->getDb();
    
    // This query determines month from week numbers
    $sql = "SELECT 
                FLOOR((er.rating_week - 1) / 4) + 1 as month_number,
                CASE 
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 1 THEN 'January'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 2 THEN 'February'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 3 THEN 'March'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 4 THEN 'April'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 5 THEN 'May'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 6 THEN 'June'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 7 THEN 'July'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 8 THEN 'August'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 9 THEN 'September'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 10 THEN 'October'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 11 THEN 'November'
                    WHEN FLOOR((er.rating_week - 1) / 4) + 1 = 12 THEN 'December'
                    ELSE 'Unknown'
                END as month_name,
                AVG(er.rating) as average_rating,
                COUNT(er.id) as total_ratings
            FROM employee_ratings er
            JOIN employees e ON er.employee_id = e.id
            JOIN team_members tm ON e.id = tm.employee_id
            JOIN teams t ON tm.team_id = t.id
            JOIN departments d ON t.department_id = d.id
            WHERE er.rating_year = ?";
    
    $params = [$selectedYear];
    
    if ($departmentId) {
        $sql .= " AND d.id = ?";
        $params[] = $departmentId;
    }
    
    if ($teamId) {
        $sql .= " AND t.id = ?";
        $params[] = $teamId;
    }
    
    $sql .= " GROUP BY month_number
              ORDER BY month_number";
    
    return $db->resultset($sql, $params);
}

// Get data based on filters
$annualData = getAnnualData($rating, $selectedYear, $departmentId, $teamId);
$parameterAverages = getParameterAverages($rating, $selectedYear, $departmentId, $teamId);
$monthlyAverages = getMonthlyAverages($rating, $selectedYear, $departmentId, $teamId);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Annual Performance Report - <?php echo $selectedYear; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
            <a href="reports.php" class="btn btn-sm btn-outline-secondary">Weekly Reports</a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Filter Annual Report</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php 
                    $currentYear = date('Y');
                    for ($year = $currentYear; $year >= $currentYear - 3; $year--) {
                        $selected = $selectedYear == $year ? 'selected' : '';
                        echo "<option value=\"$year\" $selected>$year</option>";
                    }
                    ?>
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

<!-- Monthly Performance Chart -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Monthly Performance Trends - <?php echo $selectedYear; ?></h6>
            </div>
            <div class="card-body">
                <canvas id="monthlyPerformanceChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Performance Summary Cards -->
<div class="row mb-4">
    <?php
    $totalRatings = 0;
    $overallAverage = 0;
    
    foreach ($monthlyAverages as $month) {
        $totalRatings += $month['total_ratings'];
        $overallAverage += $month['average_rating'] * $month['total_ratings'];
    }
    
    if ($totalRatings > 0) {
        $overallAverage = $overallAverage / $totalRatings;
    }
    
    // Find best and worst parameter
    $bestParameter = null;
    $worstParameter = null;
    
    if (!empty($parameterAverages)) {
        $bestParameter = $parameterAverages[0]; // Already sorted desc
        $worstParameter = end($parameterAverages);
    }
    ?>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Overall Rating
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($overallAverage, 2); ?> / 5
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-star-fill fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Ratings
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $totalRatings; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clipboard-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Strongest Area
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php if ($bestParameter): ?>
                                <?php echo htmlspecialchars($bestParameter['parameter_name']); ?>
                                <div class="small text-success">
                                    <?php echo number_format($bestParameter['average_rating'], 2); ?> / 5
                                </div>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-arrow-up-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Area for Improvement
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php if ($worstParameter): ?>
                                <?php echo htmlspecialchars($worstParameter['parameter_name']); ?>
                                <div class="small text-danger">
                                    <?php echo number_format($worstParameter['average_rating'], 2); ?> / 5
                                </div>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-arrow-down-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Top Performers</h6>
            </div>
            <div class="card-body">
                <?php if (empty($annualData)): ?>
                    <div class="alert alert-info">No rating data available for the selected filters.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Team</th>
                                    <th>Department</th>
                                    <th>Average Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Display top 10 performers
                                $count = 0;
                                foreach ($annualData as $data) {
                                    if ($count >= 10) break;
                                    
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($data['employee_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($data['team_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($data['department_name']) . '</td>';
                                    echo '<td>' . generateStarRating($data['average_rating']) . '</td>';
                                    echo '</tr>';
                                    
                                    $count++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Parameter Performance</h6>
            </div>
            <div class="card-body">
                <?php if (empty($parameterAverages)): ?>
                    <div class="alert alert-info">No parameter data available for the selected filters.</div>
                <?php else: ?>
                    <canvas id="parameterPerformanceChart" height="250"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- All Employee Annual Data -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Complete Annual Employee Ratings</h6>
    </div>
    <div class="card-body">
        <?php if (empty($annualData)): ?>
            <div class="alert alert-info">No rating data available for the selected filters.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="annualDataTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Team</th>
                            <th>Average Rating</th>
                            <th>Weeks Rated</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($annualData as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($data['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($data['team_name']); ?></td>
                                <td><?php echo generateStarRating($data['average_rating']); ?></td>
                                <td><?php echo $data['weeks_rated']; ?></td>
                                <td>
                                    <a href="reports.php?employee_id=<?php echo $data['employee_id']; ?>&year=<?php echo $selectedYear; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-search"></i> View Details
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
    // Filter related JS
    const departmentSelect = document.getElementById('department_id');
    const teamSelect = document.getElementById('team_id');
    
    departmentSelect.addEventListener('change', function() {
        let departmentId = this.value;
        
        // Clear team options
        teamSelect.innerHTML = '<option value="">All Teams</option>';
        
        if (departmentId) {
            // Get teams for this department
            <?php foreach ($departments as $dept): ?>
            if (departmentId == <?php echo $dept['id']; ?>) {
                <?php 
                $deptTeams = $department->getTeams($dept['id']);
                foreach ($deptTeams as $t):
                ?>
                let option = document.createElement('option');
                option.value = <?php echo $t['id']; ?>;
                option.textContent = "<?php echo htmlspecialchars($t['name']); ?>";
                teamSelect.appendChild(option);
                <?php endforeach; ?>
            }
            <?php endforeach; ?>
        } else {
            // Add all teams if no department is selected
            <?php foreach ($allTeams as $t): ?>
            let option = document.createElement('option');
            option.value = <?php echo $t['id']; ?>;
            option.textContent = "<?php echo htmlspecialchars($t['name']); ?>";
            teamSelect.appendChild(option);
            <?php endforeach; ?>
        }
    });
    
    // Charts
    // Monthly Performance Chart
    var monthlyCtx = document.getElementById('monthlyPerformanceChart').getContext('2d');
    var monthlyChart = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                $monthNames = [];
                foreach ($monthlyAverages as $month) {
                    $monthNames[] = "'" . $month['month_name'] . "'";
                }
                echo implode(', ', $monthNames);
                ?>
            ],
            datasets: [{
                label: 'Average Rating',
                data: [
                    <?php 
                    $monthlyData = [];
                    foreach ($monthlyAverages as $month) {
                        $monthlyData[] = round($month['average_rating'], 2);
                    }
                    echo implode(', ', $monthlyData);
                    ?>
                ],
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointRadius: 3,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: 'rgba(78, 115, 223, 1)',
                pointHoverRadius: 5,
                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                pointHitRadius: 10,
                pointBorderWidth: 2,
                tension: 0.3
            }]
        },
        options: {
            maintainAspectRatio: false,
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
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Average: ' + context.parsed.y + ' / 5';
                        }
                    }
                }
            }
        }
    });
    
    // Parameter Performance Chart
    <?php if (!empty($parameterAverages)): ?>
    var paramCtx = document.getElementById('parameterPerformanceChart').getContext('2d');
    var paramChart = new Chart(paramCtx, {
        type: 'bar', // Change from 'horizontalBar' to 'bar'
        data: {
            labels: [
                <?php 
                $paramNames = [];
                foreach ($parameterAverages as $param) {
                    $paramNames[] = "'" . addslashes($param['parameter_name']) . "'";
                }
                echo implode(', ', $paramNames);
                ?>
            ],
            datasets: [{
                label: 'Average Rating',
                data: [
                    <?php 
                    $paramData = [];
                    foreach ($parameterAverages as $param) {
                        $paramData[] = round($param['average_rating'], 2);
                    }
                    echo implode(', ', $paramData);
                    ?>
                ],
                backgroundColor: [
                    <?php 
                    $colors = [];
                    foreach ($parameterAverages as $param) {
                        if ($param['average_rating'] >= 4) {
                            $colors[] = "'rgba(28, 200, 138, 0.8)'"; // Success
                        } elseif ($param['average_rating'] >= 3) {
                            $colors[] = "'rgba(54, 185, 204, 0.8)'"; // Info
                        } elseif ($param['average_rating'] >= 2) {
                            $colors[] = "'rgba(246, 194, 62, 0.8)'"; // Warning
                        } else {
                            $colors[] = "'rgba(231, 74, 59, 0.8)'"; // Danger
                        }
                    }
                    echo implode(', ', $colors);
                    ?>
                ],
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y', // This makes it horizontal
            scales: {
                x: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>    
    // Initialize the annual data table with search and pagination
    $('#annualDataTable').DataTable({
        order: [[3, 'desc']], // Sort by average rating descending
        pageLength: 25 // Show 25 entries per page
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
