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
require_once '../../classes/ReportGenerator.php';

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

        if (isset($_POST[$ratingKey])) {
            $ratingValue = intval($_POST[$ratingKey]);
            $comment = isset($_POST[$commentKey]) ? trim($_POST[$commentKey]) : '';

            // Validate: if rating is 0, comment is required
            if ($ratingValue === 0 && empty($comment)) {
                $success = false;
                $errors[] = "Justification is required for zero rating on parameter: " . ($index + 1);
                continue;
            }

            $ratingData = [
                'employee_id' => $employee_id,
                'parameter_id' => $parameterId,
                'rating' => $ratingValue,
                'rated_by' => $manager_id,
                'rating_week' => $week,
                'rating_year' => $year,
                'comments' => $comment
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

	// Send immediate email report to the employee
	try {
		$reportGenerator = new ReportGenerator();
		$emailSent = $reportGenerator->sendWeeklyReport($employee_id, $week, $year);

		if ($emailSent) {
			$_SESSION['message'] .= " Performance report has been emailed to the employee.";
		} else {
			// Don't show error to avoid confusing the manager - just log it
			error_log("Failed to send performance report email to employee ID: $employee_id");
		}
	} catch (Exception $e) {
		error_log("Exception sending performance report: " . $e->getMessage());
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

            <div class="col-md-6">
                <label for="week" class="form-label">Rating Period</label>
                <select class="form-select" id="week" name="week">
                    <?php echo getWeekOptions($selectedWeek); ?>
                </select>
                <input type="hidden" id="year" name="year" value="<?php echo $selectedYear; ?>">
            </div> 
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Loader Overlay -->
<div id="loader-overlay" class="loader-overlay">
    <div class="loader-container">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-primary">Saving ratings...</p>
    </div>
</div>

<?php if ($employee_id && $employeeInfo): ?>
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
                                $commentValue = ($existingRating && isset($existingRating['comments'])) ? $existingRating['comments'] : '';
                                ?>
                                <div class="list-group-item py-2 mobile-rating-item">
                                    <div class="d-flex justify-content-between align-items-center mobile-rating-row">
                                        <span class="parameter-name"><?php echo htmlspecialchars($param['name']); ?></span>
                                        <input type="hidden" name="parameter_id[]" value="<?php echo $param['id']; ?>">
                                        <div class="rating-group mobile-stars">
                                            <!-- Add zero rating option -->
                                            <div class="form-check form-check-inline m-0 star-item">
                                                <input class="form-check-input visually-hidden rating-input" 
                                                       type="radio"
                                                       name="rating_<?php echo $param['id']; ?>"
                                                       id="rating_<?php echo $param['id']; ?>_0"
                                                       value="0"
                                                       data-parameter-id="<?php echo $param['id']; ?>"
                                                       data-parameter-name="<?php echo htmlspecialchars($param['name']); ?>"
                                                       <?php echo ($ratingValue == 0) ? 'checked' : ''; ?>
                                                       required>
                                                <label class="form-check-label star-label" for="rating_<?php echo $param['id']; ?>_0">
                                                    <i class="bi bi-x-circle text-danger"></i>
                                                </label>
                                            </div>
                                            
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="form-check form-check-inline m-0 star-item">
                                                    <input class="form-check-input visually-hidden rating-input" 
                                                           type="radio"
                                                           name="rating_<?php echo $param['id']; ?>"
                                                           id="rating_<?php echo $param['id']; ?>_<?php echo $i; ?>"
                                                           value="<?php echo $i; ?>"
                                                           data-parameter-id="<?php echo $param['id']; ?>"
                                                           data-parameter-name="<?php echo htmlspecialchars($param['name']); ?>"
                                                           <?php echo ($ratingValue == $i) ? 'checked' : ''; ?>
                                                           required>
                                                    <label class="form-check-label star-label" for="rating_<?php echo $param['id']; ?>_<?php echo $i; ?>">
                                                        <i class="bi <?php echo ($ratingValue >= $i && $ratingValue > 0) ? 'bi-star-fill' : 'bi-star'; ?> text-warning"></i>
                                                    </label>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Comment field (optional for ratings 1-5, required for rating 0) -->
                                    <div class="mt-2 comment-container">
                                        <textarea class="form-control comment-field" 
                                                 name="comment_<?php echo $param['id']; ?>" 
                                                 id="comment_<?php echo $param['id']; ?>"
                                                 rows="2"
                                                 placeholder="Comments (optional)"><?php echo htmlspecialchars($commentValue); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary" id="saveRatingsBtn">Save Ratings</button>

                            <?php if (isset($nextEmployeeId)): ?>
                            <button type="submit" name="next_employee" value="<?php echo $nextEmployeeId; ?>" class="btn btn-success" id="saveNextBtn">
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

<!-- Zero Rating Comment Modal -->
<div class="modal fade" id="zeroRatingModal" tabindex="-1" aria-labelledby="zeroRatingModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zeroRatingModalLabel">Justification Required</h5>
            </div>
            <div class="modal-body" id="zero-rating-parameters-container">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> Zero rating requires justification
                </div>
                <!-- Parameters requiring comments will be added here dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelZeroRating">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveZeroRatingComment">Save</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Loader styles */
.loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loader-container {
    text-align: center;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
}

.loader-overlay.show {
    display: flex;
}

/* Comment field styles */
.comment-container {
    display: block;
    transition: max-height 0.3s ease;
}

.comment-field {
    font-size: 0.9rem;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out;
}

.comment-field:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.comment-field.required {
    border-color: #e74a3b;
}

.comment-field.required:focus {
    box-shadow: 0 0 0 0.2rem rgba(231, 74, 59, 0.25);
}
</style>

<script>
// Ensure year is updated when week changes
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const ratingForm = document.getElementById('ratingForm');
    const loaderOverlay = document.getElementById('loader-overlay');
    
    // Function to show loader
    function showLoader() {
        loaderOverlay.classList.add('show');
    }
    
    // Function to hide loader
    function hideLoader() {
        loaderOverlay.classList.remove('show');
    }
    
    document.getElementById('week').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const year = selectedOption.getAttribute('data-year');
        document.getElementById('year').value = year;
    });

    // Setup form submit handlers for loader
    if (ratingForm) {
        const formButtons = ratingForm.querySelectorAll('button[type="submit"]');
        formButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Store which button was clicked if needed
                if (this.name === 'next_employee') {
                    ratingForm.setAttribute('data-next-employee', this.value);
                }
            });
        });
    }

    // Zero rating handling
    const zeroRatingModal = new bootstrap.Modal(document.getElementById('zeroRatingModal'), {
        backdrop: 'static',
        keyboard: false
    });
    
    // Keep track of which parameters have zero ratings
    let zeroRatedParameters = new Set();
    let submittedFromModal = false;
    let nextEmployeeClicked = null;

    // Function to mark comment fields as required or optional
    function updateCommentRequirements() {
        // First reset all comment fields
        document.querySelectorAll('.comment-field').forEach(field => {
            field.classList.remove('required');
            field.setAttribute('placeholder', 'Comments (optional)');
        });
        
        // Find all parameters with zero ratings and mark their comment fields as required
        document.querySelectorAll('.rating-input:checked').forEach(function(input) {
            if (parseInt(input.value) === 0) {
                const parameterId = input.getAttribute('data-parameter-id');
                const commentField = document.getElementById(`comment_${parameterId}`);
                commentField.classList.add('required');
                commentField.setAttribute('placeholder', 'Justification required for zero rating');
            }
        });
    }

    // Function to open modal for zero ratings
    function checkAndShowZeroRatingModal() {
        zeroRatedParameters.clear();
        
        // Find all parameters with zero ratings
        document.querySelectorAll('.rating-input:checked').forEach(function(input) {
            if (parseInt(input.value) === 0) {
                const parameterId = input.getAttribute('data-parameter-id');
                const commentField = document.getElementById(`comment_${parameterId}`);
                
                // Only add to zero rated parameters if comment is empty
                if (!commentField.value.trim()) {
                    zeroRatedParameters.add(parameterId);
                }
            }
        });
        
        // If there are zero ratings without comments, show the modal
        if (zeroRatedParameters.size > 0) {
            updateZeroRatingModalContent();
            zeroRatingModal.show();
            return true;
        }
        
        return false;
    }
    
    // Update modal content with parameter fields
    function updateZeroRatingModalContent() {
        const container = document.getElementById('zero-rating-parameters-container');
        // Keep the alert div
        const alertDiv = container.querySelector('.alert');
        container.innerHTML = '';
        container.appendChild(alertDiv);
        
        // Add a field for each parameter with zero rating
        zeroRatedParameters.forEach(parameterId => {
            const input = document.querySelector(`#rating_${parameterId}_0`);
            const parameterName = input.getAttribute('data-parameter-name');
            const commentValue = document.getElementById(`comment_${parameterId}`).value;
            
            const paramDiv = document.createElement('div');
            paramDiv.className = 'mb-3 zero-rating-comment-group';
            paramDiv.setAttribute('data-parameter-id', parameterId);
            
            paramDiv.innerHTML = `
                <label class="form-label">Parameter: ${parameterName}</label>
                <textarea class="form-control zero-rating-comment" 
                          data-parameter-id="${parameterId}"
                          rows="3" 
                          placeholder="Please provide justification for zero rating">${commentValue}</textarea>
                <div class="invalid-feedback">
                    Justification is required for zero rating
                </div>
            `;
            
            container.appendChild(paramDiv);
        });
    }
    
    // Handle saving comments from modal
    document.getElementById('saveZeroRatingComment').addEventListener('click', function() {
        let allValid = true;
        
        // Validate all comment fields
        document.querySelectorAll('.zero-rating-comment').forEach(function(textarea) {
            const parameterId = textarea.getAttribute('data-parameter-id');
            const commentField = document.getElementById(`comment_${parameterId}`);
            
            if (textarea.value.trim() === '') {
                textarea.classList.add('is-invalid');
                allValid = false;
            } else {
                textarea.classList.remove('is-invalid');
                // Update the visible comment field
                commentField.value = textarea.value.trim();
            }
        });
        
        if (allValid) {
            submittedFromModal = true;
            zeroRatingModal.hide();
            
            // If this was triggered from the form submission, submit the form now
            if (window.formSubmitPending) {
                window.formSubmitPending = false;
                
                // Handle next employee if it was clicked
                if (nextEmployeeClicked) {
                    // Create a hidden input for next_employee
                    const nextInput = document.createElement('input');
                    nextInput.type = 'hidden';
                    nextInput.name = 'next_employee';
                    nextInput.value = nextEmployeeClicked;
                    document.getElementById('ratingForm').appendChild(nextInput);
                }
                
                // Show loader before submitting
                showLoader();
                
                // Disable form elements to prevent double submission
                const formElements = document.getElementById('ratingForm').elements;
                for (let i = 0; i < formElements.length; i++) {
                    formElements[i].disabled = true;
                }
                
                document.getElementById('ratingForm').submit();
            }
        }
    });
    
    // Cancel button handler
    document.getElementById('cancelZeroRating').addEventListener('click', function() {
        handleCancelZeroRating();
    });
    
    function handleCancelZeroRating() {
        // Uncheck all zero ratings
        zeroRatedParameters.forEach(parameterId => {
            const zeroInput = document.getElementById(`rating_${parameterId}_0`);
            zeroInput.checked = false;
            
            // Find the previous rating or default to 3
            const paramInputs = document.querySelectorAll(`input[name="rating_${parameterId}"]`);
            let previousSelected = null;
            
            // Check for data-previous-value attribute
            paramInputs.forEach(input => {
                if (input.getAttribute('data-previous-value') === 'true') {
                    input.checked = true;
                    previousSelected = input;
                }
            });
            
            // If no previous value found, default to rating 3
            if (!previousSelected) {
                const defaultInput = document.getElementById(`rating_${parameterId}_3`);
                if (defaultInput) {
                    defaultInput.checked = true;
                }
            }
            
            // Update comment field requirements
            updateCommentRequirements();
        });
        
        zeroRatingModal.hide();
        zeroRatedParameters.clear();
        nextEmployeeClicked = null;
    }
    
    // Add event listener to zero rating inputs
    document.querySelectorAll('.rating-input').forEach(function(input) {
        // Set initial previous value markers
        if (input.checked && input.value !== '0') {
            input.setAttribute('data-previous-value', 'true');
        }
        
        // Update when a new rating is selected
        input.addEventListener('click', function() {
            if (this.value !== '0') {
                // Clear previous values for this parameter
                document.querySelectorAll(`input[name="${this.name}"]`).forEach(inp => {
                    inp.removeAttribute('data-previous-value');
                });
                
                // Mark this as the new previous value
                this.setAttribute('data-previous-value', 'true');
            }
            
            // Update comment field requirements based on rating
            updateCommentRequirements();
        });
    });
    
    // Form validation for zero ratings
    if (ratingForm) {
        ratingForm.addEventListener('submit', function(event) {
            // Check if Next button was clicked
            if (event.submitter && event.submitter.name === 'next_employee') {
                nextEmployeeClicked = event.submitter.value;
            } else {
                nextEmployeeClicked = null;
            }
            
            // Show the loader during validation
            showLoader();
            
            // Check if there are zero ratings that need comments
            if (checkAndShowZeroRatingModal()) {
                // Prevent the default form submission
                event.preventDefault();
                
                // Hide the loader during modal input
                hideLoader();
                
                // Flag that we're waiting for modal input
                window.formSubmitPending = true;
            } else {
                // If we don't need to show the modal, disable all form elements to prevent double submission
                const formElements = ratingForm.elements;
                for (let i = 0; i < formElements.length; i++) {
                    formElements[i].disabled = true;
                }
            }
        });
    }
    
    // Add event listener to all textarea fields in the modal
    document.getElementById('zero-rating-parameters-container').addEventListener('input', function(event) {
        if (event.target.classList.contains('zero-rating-comment')) {
            event.target.classList.remove('is-invalid');
        }
    });
    
    // Initialize comment field requirements
    updateCommentRequirements();
    
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
                
                // Update comment field requirements
                updateCommentRequirements();
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
