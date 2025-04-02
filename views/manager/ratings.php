<?php
/**
 * Manager Employee Ratings - Fixed Version
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../classes/Employee.php';
require_once '../../classes/Department.php';
require_once '../../classes/Team.php';
require_once '../../classes/Rating.php';
require_once '../../classes/Notification.php';

// Ensure user is Manager
requireManager();

// Get manager ID
$manager_id = $_SESSION['user_id'];

// Initialize objects
$employee = new Employee();
$department = new Department();
$team = new Team();
$rating = new Rating();
$notification = new Notification();

// Get current week and year
$currentWeekYear = $rating->getCurrentWeekYear();
$currentWeek = $currentWeekYear['week'];
$currentYear = $currentWeekYear['year'];

// Get selected week and year (default to current)
$selectedWeek = isset($_GET['week']) ? intval($_GET['week']) : $currentWeek;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// Check for incomplete previous weeks if trying to rate for current week
$incompleteWeeks = null;
if ($selectedWeek == $currentWeek && $selectedYear == $currentYear) {
    $incompleteWeeks = $rating->hasIncompletePreviousWeeks($manager_id, $currentWeek, $currentYear);
}

// Set disable flag for form if needed
$disableForm = false;
if ($incompleteWeeks && $selectedWeek == $currentWeek && $selectedYear == $currentYear) {
    $disableForm = true;
}

// Get employee if specified
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
$employeeInfo = null;
$teamInfo = null;
$departmentInfo = null;
$parameters = [];
$ratingErrors = [];

if ($employee_id) {
    // Verify employee belongs to manager
    if (!$employee->belongsToManager($employee_id, $manager_id)) {
        $_SESSION['message'] = 'You do not have permission to rate this employee';
        $_SESSION['message_type'] = 'danger';
        header('Location: ratings.php');
        exit;
    }
    
    // Get employee information
    $employeeInfo = $employee->getById($employee_id);
    
    // Get team and department info
    $teamInfo = $employee->getTeam($employee_id);
    if ($teamInfo) {
        $departmentInfo = $department->getById($teamInfo['department_id']);
        if ($departmentInfo) {
            $parameters = $department->getParameters($departmentInfo['id']);
        } else {
            $ratingErrors[] = "Department not found for this team";
        }
    } else {
        $ratingErrors[] = "Employee is not assigned to any team";
    }
}

// Find next employee for sequential rating
$nextEmployeeId = null;
$nextEmployeeName = null;

if ($employee_id && $employeeInfo) {
    $teamInfo = $employee->getTeam($employee_id);
    if ($teamInfo) {
        $teamMembers = $employee->getByTeamId($teamInfo['id']);
        
        // Find current employee position in the team
        $currentIndex = -1;
        foreach ($teamMembers as $index => $member) {
            if ($member['id'] == $employee_id) {
                $currentIndex = $index;
                break;
            }
        }
        
        // Determine next employee
        if ($currentIndex !== -1 && $currentIndex < count($teamMembers) - 1) {
            $nextEmployeeId = $teamMembers[$currentIndex + 1]['id'];
            $nextEmployeeName = $teamMembers[$currentIndex + 1]['first_name'] . ' ' . $teamMembers[$currentIndex + 1]['last_name'];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ratings') {
    $employee_id = intval($_POST['employee_id']);
    $week = intval($_POST['week']);
    $year = intval($_POST['year']);
    $parameterIds = $_POST['parameter_id'] ?? [];
    
    // Verify employee belongs to manager
    if (!$employee->belongsToManager($employee_id, $manager_id)) {
        $_SESSION['message'] = 'You do not have permission to rate this employee';
        $_SESSION['message_type'] = 'danger';
        header('Location: ratings.php');
        exit;
    }
    
    $success = true;
    $total = count($parameterIds);
    $saved = 0;
    $errors = [];
    
    // Save each rating
    foreach ($parameterIds as $index => $parameterId) {
        $ratingKey = "rating_" . $parameterId;
        $commentKey = "comment_" . $parameterId;
        
        if (isset($_POST[$ratingKey]) && !empty($_POST[$ratingKey])) {
            $ratingData = [
                'employee_id' => $employee_id,
                'parameter_id' => $parameterId,
                'rating' => intval($_POST[$ratingKey]),
                'rated_by' => $manager_id,
                'rating_week' => $week,
                'rating_year' => $year,
                'comments' => isset($_POST[$commentKey]) ? $_POST[$commentKey] : ''
            ];
            
            $result = $rating->saveRating($ratingData);
            
            if ($result['success']) {
                $saved++;
            } else {
                $success = false;
                if (isset($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $errors[] = "Parameter #" . ($index + 1) . ": " . $error;
                    }
                } else {
                    $errors[] = "Failed to save rating for parameter #" . ($index + 1);
                }
            }
        }
    }
    
    if ($success && $saved > 0) {
        // Create notification for CEO
        $admins = $rating->getAdmins();
        
        $employeeName = $employeeInfo['first_name'] . ' ' . $employeeInfo['last_name'];
        $message = "Manager has submitted ratings for $employeeName for Week $week, $year";

        foreach ($admins as $admin) {
            $notification->create($admin['id'], $message);
        }
        
        // Check if we should go to the next employee
        if (isset($_POST['next_employee']) && !empty($_POST['next_employee'])) {
            $nextEmployeeId = intval($_POST['next_employee']);
            
            $_SESSION['message'] = "Ratings saved successfully for current employee.";
            $_SESSION['message_type'] = 'success';
            
            header('Location: ratings.php?employee_id=' . $nextEmployeeId . '&week=' . $week . '&year=' . $year);
            exit;
        }
        
        // Regular success message and redirect otherwise
        $_SESSION['message'] = "Ratings saved successfully ($saved/$total parameters)";
        $_SESSION['message_type'] = 'success';
    } elseif ($saved > 0) {
        $_SESSION['message'] = "Some ratings saved ($saved/$total), but there were errors";
        $_SESSION['message_type'] = 'warning';
        $ratingErrors = $errors;
    } else {
        $_SESSION['message'] = 'Failed to save ratings. Please check the errors below.';
        $_SESSION['message_type'] = 'danger';
        $ratingErrors = $errors;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ratings.php?employee_id=' . $employee_id . '&week=' . $week . '&year=' . $year);
    exit;
}

// Get all employees for this manager for the selection dropdown
$employees = $employee->getAll($manager_id);

// Include header
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Employee Ratings</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="history.php" class="btn btn-sm btn-outline-secondary">View History</a>
        </div>
    </div>
</div>

<?php if (!empty($ratingErrors)): ?>
<div class="alert alert-danger" role="alert">
    <h5 class="alert-heading">Errors occurred while saving ratings:</h5>
    <ul>
        <?php foreach($ratingErrors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Selection Form -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold">Select Employee and Rating Period</h6>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="employee_id" class="form-label">Employee</label>
                <select class="form-select" id="employee_id" name="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo ($employee_id == $emp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['team_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="week" class="form-label">Week</label>
                <select class="form-select" id="week" name="week">
                    <?php echo getWeekOptions($selectedWeek); ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" id="year" name="year">
                    <?php echo getYearOptions($selectedYear); ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Select</button>
            </div>
        </form>
    </div>
</div>

<?php if ($employee_id && $employeeInfo): ?>
    <!-- Employee Information -->
    <!--div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">Employee Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($employeeInfo['first_name'] . ' ' . $employeeInfo['last_name']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($employeeInfo['position']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($employeeInfo['email']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Team:</strong> <?php echo $teamInfo ? htmlspecialchars($teamInfo['name']) : 'N/A'; ?></p>
                    <p><strong>Department:</strong> <?php echo $departmentInfo ? htmlspecialchars($departmentInfo['name']) : 'N/A'; ?></p>
                    <p><strong>Rating Period:</strong> <?php echo formatWeekRange($selectedWeek, $selectedYear); ?></p>
                </div>
            </div>
        </div>
    </div--!>
    
    <?php if (empty($parameters)): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i> No rating parameters found for this employee's department. Please contact the CEO to set up rating parameters.
        </div>
    <?php else: ?>

        <?php 
            // Show warning and disable form if there are incomplete previous weeks
            if ($incompleteWeeks && $selectedWeek == $currentWeek && $selectedYear == $currentYear): 
            ?>
                <div class="alert alert-warning alert-dismissible fade show persistent-alert" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <strong>Cannot rate for current week!</strong> You have incomplete ratings from previous weeks:
                    <ul>
                        <?php foreach ($incompleteWeeks as $week): ?>
                            <li>
                                <strong><a href="ratings.php?week=<?php echo $week['week']; ?>&year=<?php echo $week['year']; ?>">Week <?php echo $week['week']; ?> (<?php echo $week['formatted']; ?>)</a></strong>
                                <ul>
                                <?php foreach ($week['employees'] as $emp): ?>
                                    <li>
                                        <a href="ratings.php?employee_id=<?php echo $emp['id']; ?>&week=<?php echo $week['week']; ?>&year=<?php echo $week['year']; ?>">
                                            <?php echo htmlspecialchars($emp['name']); ?>
                                        </a> - <?php echo $emp['rated']; ?>/<?php echo $emp['total']; ?> ratings completed
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?> 

        <?php if (!$disableForm): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Rate Employee</h6>
                </div>
                <div class="card-body">
                    <form method="post" id="ratingForm">
                        <input type="hidden" name="action" value="save_ratings">
                        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                        <input type="hidden" name="week" value="<?php echo $selectedWeek; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">

                        <div class="list-group">
                            <?php foreach ($parameters as $param): ?>
                                <?php
                                // Check if there's an existing rating
                                $existingRating = $rating->getSpecificRating(
                                    $employee_id,
                                    $param['id'],
                                    $selectedWeek,
                                    $selectedYear
                                );
                                $ratingValue = ($existingRating && isset($existingRating['rating'])) ? $existingRating['rating'] : 0;
                                ?>
                                <div class="list-group-item py-2 mobile-rating-item">
                                    <div class="d-flex justify-content-between align-items-center mobile-rating-row">
                                        <span class="parameter-name"><?php echo htmlspecialchars($param['name']); ?></span>
                                        <input type="hidden" name="parameter_id[]" value="<?php echo $param['id']; ?>">
                                        <div class="rating-group mobile-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="form-check form-check-inline m-0 star-item">
                                                    <input class="form-check-input visually-hidden" type="radio"
                                                           name="rating_<?php echo $param['id']; ?>"
                                                           id="rating_<?php echo $param['id']; ?>_<?php echo $i; ?>"
                                                           value="<?php echo $i; ?>"
                                                           <?php echo ($ratingValue == $i) ? 'checked' : ''; ?>
                                                           required>
                                                    <label class="form-check-label star-label" for="rating_<?php echo $param['id']; ?>_<?php echo $i; ?>">
                                                        <i class="bi <?php echo ($ratingValue >= $i) ? 'bi-star-fill' : 'bi-star'; ?> text-warning"></i>
                                                    </label>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Save Ratings</button>

                            <?php if (isset($nextEmployeeId)): ?>
                            <button type="submit" name="next_employee" value="<?php echo $nextEmployeeId; ?>" class="btn btn-success">
                                Save & Next: <?php echo htmlspecialchars($nextEmployeeName); ?> <i class="bi bi-arrow-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Please complete previous weeks' ratings before rating for the current week.</div>
        <?php endif; ?>
    <?php endif; ?>
<?php elseif ($employee_id && !$employeeInfo): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i> Employee not found or you do not have permission to rate this employee.
    </div>
<?php else: ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> Please select an employee to rate.
    </div>
<?php endif; ?>

<script>
// Add this at the end of your file before </body>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const ratingForm = document.getElementById('ratingForm');
    if (ratingForm) {
        ratingForm.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Check if at least one rating is selected for each parameter
            const ratingGroups = document.querySelectorAll('.rating-group');
            ratingGroups.forEach(function(group) {
                const radioName = group.querySelector('input[type="radio"]').name;
                const checkedInputs = document.querySelectorAll('input[name="' + radioName + '"]:checked');
                
                if (checkedInputs.length === 0) {
                    isValid = false;
                    // Add error message
                    if (!group.nextElementSibling || !group.nextElementSibling.classList.contains('text-danger')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'text-danger mt-1';
                        errorMsg.textContent = 'Please select a rating';
                        group.after(errorMsg);
                    }
                } else {
                    // Remove error message if exists
                    if (group.nextElementSibling && group.nextElementSibling.classList.contains('text-danger')) {
                        group.nextElementSibling.remove();
                    }
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                alert('Please complete all ratings before submitting.');
            }
        });
    }
    
    // Enhance star rating system
    const ratingGroups = document.querySelectorAll('.rating-group');
    ratingGroups.forEach(function(group) {
        const stars = group.querySelectorAll('i.bi');
        const inputs = group.querySelectorAll('input[type="radio"]');
        
        // Add hover effect for stars
        stars.forEach(function(star, index) {
            star.parentElement.addEventListener('mouseenter', function() {
                // Fill stars up to current
                for (let i = 0; i <= index; i++) {
                    stars[i].classList.remove('bi-star');
                    stars[i].classList.add('bi-star-fill');
                }
                
                // Unfill stars after current
                for (let i = index + 1; i < stars.length; i++) {
                    stars[i].classList.remove('bi-star-fill');
                    stars[i].classList.add('bi-star');
                }
            });
            
            // Add click handler
            star.parentElement.addEventListener('click', function() {
                inputs[index].checked = true;
                
                // Update visual state
                for (let i = 0; i <= index; i++) {
                    stars[i].classList.remove('bi-star');
                    stars[i].classList.add('bi-star-fill');
                }
                
                for (let i = index + 1; i < stars.length; i++) {
                    stars[i].classList.remove('bi-star-fill');
                    stars[i].classList.add('bi-star');
                }
            });
        });
        
        // Reset on mouse leave
        group.addEventListener('mouseleave', function() {
            // Find the selected rating
            let selectedIndex = -1;
            inputs.forEach(function(input, i) {
                if (input.checked) {
                    selectedIndex = i;
                }
            });
            
            // Reset stars based on selection
            stars.forEach(function(star, i) {
                if (i <= selectedIndex) {
                    star.classList.remove('bi-star');
                    star.classList.add('bi-star-fill');
                } else {
                    star.classList.remove('bi-star-fill');
                    star.classList.add('bi-star');
                }
            });
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>
