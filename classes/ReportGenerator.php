<?php
/**
 * Report Generator class for creating employee performance reports
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/EmailService.php';

class ReportGenerator {
    private $db;
    private $emailService;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
    }
    
    /**
     * Generate and send weekly performance report to an employee
     * 
     * @param int $employee_id The employee ID
     * @param int $week Optional specific week (defaults to previous week)
     * @param int $year Optional specific year (defaults to current year)
     * @return bool True if report was generated and sent, false otherwise
     */
    public function sendWeeklyReport($employee_id, $week = null, $year = null) {
        // Get employee details
        $employee = $this->getEmployee($employee_id);
        if (!$employee || empty($employee['email'])) {
            error_log("Employee not found or no email: $employee_id");
            return false;
        }
        
        // Determine week and year if not specified
        if ($week === null) {
            // Use previous week
            $date = new DateTime();
            $date->modify('-1 week');
            $week = $date->format('W');
            $year = $date->format('Y');
        }
        
        // Get weekly ratings
        $weeklyRatings = $this->getWeeklyRatings($employee_id, $week, $year);
        if (empty($weeklyRatings)) {
            error_log("No ratings found for employee $employee_id in week $week, $year");
            return false;
        }
        
        // Get historical ratings (3 months)
        $historicalRatings = $this->getHistoricalRatings($employee_id, 12); // approx 3 months of weekly ratings
        
        // Generate report content
        $reportContent = $this->generateReportContent($employee, $weeklyRatings, $historicalRatings, $week, $year);
        
        // Send email
        $subject = APP_NAME . ' - Your Performance Report for Week ' . $week . ', ' . $year;
        $message = $this->emailService->formatEmailTemplate($reportContent, 'Weekly Performance Report');
        
        return $this->emailService->sendEmail($employee['email'], $subject, $message);
    }
    
    /**
     * Send weekly reports to all employees who have ratings in the specified week
     * 
     * @param int $week Optional specific week (defaults to previous week)
     * @param int $year Optional specific year (defaults to current year)
     * @return array Array with success and failure counts
     */
    public function sendAllWeeklyReports($week = null, $year = null) {
        // Determine week and year if not specified
        if ($week === null) {
            // Use previous week
            $date = new DateTime();
            $date->modify('-1 week');
            $week = $date->format('W');
            $year = $date->format('Y');
        }
        
        // Get all employees who have ratings in this week
        $sql = "SELECT DISTINCT er.employee_id 
                FROM employee_ratings er
                WHERE er.rating_week = ? AND er.rating_year = ?";
        
        $employees = $this->db->resultset($sql, [$week, $year]);
        
        $success = 0;
        $failure = 0;
        
        foreach ($employees as $employee) {
            if ($this->sendWeeklyReport($employee['employee_id'], $week, $year)) {
                $success++;
            } else {
                $failure++;
            }
        }
        
        return [
            'success' => $success,
            'failure' => $failure,
            'total' => count($employees)
        ];
    }
    
    /**
     * Get employee details
     * 
     * @param int $employee_id The employee ID
     * @return array|bool Employee data or false if not found
     */
    private function getEmployee($employee_id) {
        $sql = "SELECT e.*, 
                t.name as team_name, 
                d.name as department_name, 
                CONCAT(u.first_name, ' ', u.last_name) as manager_name
                FROM employees e
                LEFT JOIN team_members tm ON e.id = tm.employee_id
                LEFT JOIN teams t ON tm.team_id = t.id
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN users u ON t.manager_id = u.id
                WHERE e.id = ?";
        
        return $this->db->single($sql, [$employee_id]);
    }
    
    /**
     * Get weekly ratings for an employee
     * 
     * @param int $employee_id The employee ID
     * @param int $week The week number
     * @param int $year The year
     * @return array Array of ratings
     */
    private function getWeeklyRatings($employee_id, $week, $year) {
        $sql = "SELECT er.*, 
                rp.name as parameter_name, 
                rp.description as parameter_description,
                CONCAT(u.first_name, ' ', u.last_name) as rated_by_name
                FROM employee_ratings er
                JOIN rating_parameters rp ON er.parameter_id = rp.id
                JOIN users u ON er.rated_by = u.id
                WHERE er.employee_id = ? AND er.rating_week = ? AND er.rating_year = ?
                ORDER BY rp.name";
        
        return $this->db->resultset($sql, [$employee_id, $week, $year]);
    }
    
    /**
     * Get historical ratings for an employee
     * 
     * @param int $employee_id The employee ID
     * @param int $weeks Number of weeks to go back
     * @return array Array of average ratings by week
     */
    private function getHistoricalRatings($employee_id, $weeks = 12) {
        $sql = "SELECT 
                    er.rating_week, 
                    er.rating_year, 
                    AVG(er.rating) as average_rating,
                    COUNT(er.id) as rating_count
                FROM employee_ratings er
                WHERE er.employee_id = ?
                GROUP BY er.rating_week, er.rating_year
                ORDER BY er.rating_year DESC, er.rating_week DESC
                LIMIT ?";
        
        return $this->db->resultset($sql, [$employee_id, $weeks]);
    }

    /**
     * Generate the HTML content for the report email
     * 
     * @param array $employee Employee details
     * @param array $weeklyRatings Weekly rating details
     * @param array $historicalRatings Historical rating summary
     * @param int $week The week number
     * @param int $year The year
     * @return string HTML content for the email
     */
    private function generateReportContent($employee, $weeklyRatings, $historicalRatings, $week, $year) {
	    // Format date range for the week
	    $weekStart = new DateTime();
	    $weekStart->setISODate($year, $week, 1); // Monday
	    $weekEnd = clone $weekStart;
	    $weekEnd->modify('+6 days'); // Sunday

	    $weekRange = $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y');

	    // Calculate average rating for this week
	    $totalRating = 0;
	    $ratingCount = count($weeklyRatings);
	    foreach ($weeklyRatings as $rating) {
		    $totalRating += $rating['rating'];
	    }
	    $averageRating = $ratingCount > 0 ? round($totalRating / $ratingCount, 1) : 0;

	    // Create a function to generate star HTML that's compatible with all email clients
	    /**
	     * ASCII-only version as a last resort
	     */
	    $generateStarsASCII = function($rating) {
		    $fullStars = floor($rating);
		    $halfStar = ($rating - $fullStars) >= 0.5;
		    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

		    $starsHtml = '<span style="font-family: monospace; font-size: 18px; letter-spacing: 3px;">';

		    // Full stars
		    for ($i = 0; $i < $fullStars; $i++) {
			    $starsHtml .= '<span style="color: #FFD700;">&#9632;</span>'; // Solid block: ■
		    }

		    // Half star
		    if ($halfStar) {
			    $starsHtml .= '<span style="color: #FFD700;">&#9632;</span>'; // Also use solid block
		    }

		    // Empty stars
		    for ($i = 0; $i < $emptyStars; $i++) {
			    $starsHtml .= '<span style="color: #D3D3D3;">&#9633;</span>'; // Hollow square: □
		    }

		    $starsHtml .= '</span>';

		    return $starsHtml . ' <span style="color: #666; font-size: 14px;">(' . number_format($rating, 1) . ')</span>';
	    };

	    // Get color class based on rating
	    $getRatingColorClass = function($rating) {
		    if ($rating >= 4.5) return '#2ecc71'; // Success green
		    if ($rating >= 3.5) return '#3498db'; // Info blue
		    if ($rating >= 2.5) return '#f39c12'; // Warning yellow
		    return '#e74c3c'; // Danger red
	    };

	    // Start building the content with improved styling
	    $content = '
    <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
	<div style="background-color: #4e73df; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;">
	    <h1 style="margin: 0; font-size: 24px;">Weekly Performance Report</h1>
	    <p style="margin: 10px 0 0 0;">Week ' . $week . ' (' . $weekRange . ')</p>
	</div>

	<div style="padding: 20px; background-color: #f8f9fc; border-left: 1px solid #e3e6f0; border-right: 1px solid #e3e6f0;">
	    <p style="font-size: 16px;">Hello ' . htmlspecialchars($employee['first_name']) . ',</p>

	    <p style="font-size: 16px;">Here is your performance report for Week ' . $week . ' (' . $weekRange . ').</p>

	    <div style="background-color: white; border-radius: 5px; padding: 20px; margin: 20px 0; box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);">
		<h2 style="color: #4e73df; margin-top: 0; border-bottom: 1px solid #e3e6f0; padding-bottom: 10px; font-size: 20px;">Weekly Summary</h2>

		<table style="width: 100%; border-collapse: collapse;">
		    <tr>
			<td style="padding: 8px; width: 150px; vertical-align: top;"><strong>Team:</strong></td>
			<td style="padding: 8px;">' . htmlspecialchars($employee['team_name'] ?? 'N/A') . '</td>
		    </tr>
		    <tr>
			<td style="padding: 8px; width: 150px; vertical-align: top;"><strong>Department:</strong></td>
			<td style="padding: 8px;">' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '</td>
		    </tr>
		    <tr>
			<td style="padding: 8px; width: 150px; vertical-align: top;"><strong>Manager:</strong></td>
			<td style="padding: 8px;">' . htmlspecialchars($employee['manager_name'] ?? 'N/A') . '</td>
		    </tr>
		    <tr>
			<td style="padding: 8px; width: 150px; vertical-align: top;"><strong>Overall Rating:</strong></td>
			<td style="padding: 8px;">
			    <div>
				' . $generateStars($averageRating) . '
			    </div>
			    <div style="margin-top: 10px; height: 10px; background-color: #e9ecef; border-radius: 5px;">
				<div style="height: 100%; width: ' . ($averageRating * 20) . '%; background-color: ' . $getRatingColorClass($averageRating) . '; border-radius: 5px;"></div>
			    </div>
			</td>
		    </tr>
		</table>
	    </div>

	    <div style="background-color: white; border-radius: 5px; padding: 20px; margin: 20px 0; box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);">
		<h2 style="color: #4e73df; margin-top: 0; border-bottom: 1px solid #e3e6f0; padding-bottom: 10px; font-size: 20px;">Detailed Ratings</h2>

		<table style="width: 100%; border-collapse: collapse; border: 1px solid #e3e6f0;">
		    <thead>
			<tr style="background-color: #f8f9fc;">
			    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e3e6f0;">Parameter</th>
			    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e3e6f0;">Rating</th>
			    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e3e6f0;">Comments</th>
			</tr>
		    </thead>
		    <tbody>';

	    foreach ($weeklyRatings as $rating) {
		    $content .= '
			<tr>
			    <td style="padding: 12px; border-bottom: 1px solid #e3e6f0;">' . htmlspecialchars($rating['parameter_name']) . '</td>
			    <td style="padding: 12px; border-bottom: 1px solid #e3e6f0;">' . $generateStars($rating['rating']) . '</td>
			    <td style="padding: 12px; border-bottom: 1px solid #e3e6f0;">' . (empty($rating['comments']) ? '<span style="color: #6c757d;">No comments</span>' : htmlspecialchars($rating['comments'])) . '</td>
			</tr>';
	    }

	    $content .= '
		    </tbody>
		</table>
	    </div>

	    <div style="background-color: white; border-radius: 5px; padding: 20px; margin: 20px 0; box-shadow: 0 .15rem 1.75rem 0 rgba(58,59,69,.15);">
		<h2 style="color: #4e73df; margin-top: 0; border-bottom: 1px solid #e3e6f0; padding-bottom: 10px; font-size: 20px;">Historical Performance</h2>';

	    if (empty($historicalRatings)) {
		    $content .= '<p style="color: #6c757d;">No historical data available yet.</p>';
	    } else {
		    $content .= '
		<table style="width: 100%; border-collapse: collapse; border: 1px solid #e3e6f0;">
		    <thead>
			<tr style="background-color: #f8f9fc;">
			    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e3e6f0;">Week</th>
			    <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e3e6f0;">Average Rating</th>
			</tr>
		    </thead>
		    <tbody>';

		    foreach ($historicalRatings as $history) {
			    // Format week range
			    $historyStart = new DateTime();
			    $historyStart->setISODate($history['rating_year'], $history['rating_week'], 1);
			    $historyEnd = clone $historyStart;
			    $historyEnd->modify('+6 days');
			    $historyRange = $historyStart->format('M j') . ' - ' . $historyEnd->format('M j, Y');

			    $avgRating = round($history['average_rating'], 1);

			    $content .= '
			<tr>
			    <td style="padding: 12px; border-bottom: 1px solid #e3e6f0;">Week ' . $history['rating_week'] . ' (' . $historyRange . ')</td>
			    <td style="padding: 12px; border-bottom: 1px solid #e3e6f0;">
				' . $generateStars($avgRating) . '
			    </td>
			</tr>';
		    }

		    $content .= '
		    </tbody>
		</table>';

		    // Add trend chart using bar representation
		    $content .= '
		<div style="margin-top: 20px;">
		    <h3 style="color: #4e73df; font-size: 18px;">Performance Trend</h3>
		    <div style="display: flex; align-items: flex-end; height: 160px; margin-top: 15px; border-bottom: 2px solid #e3e6f0; padding-bottom: 10px;">';

		    $reversedHistory = array_reverse($historicalRatings);
		    $maxItems = min(count($reversedHistory), 12);
		    $barWidth = 100 / max($maxItems, 1);

		    for ($i = 0; $i < $maxItems; $i++) {
			    $history = $reversedHistory[$i];
			    $avgRating = round($history['average_rating'], 1);
			    $height = max(5, ($avgRating / 5) * 100);
			    $color = $getRatingColorClass($avgRating);

			    $content .= '
			<div style="flex: 1; text-align: center; padding: 0 5px; max-width: ' . $barWidth . '%;">
			    <div style="height: ' . $height . '%; background-color: ' . $color . '; margin: 0 auto; width: 80%; min-height: 5px; border-radius: 3px 3px 0 0;"></div>
			    <div style="font-size: 10px; margin-top: 5px; transform: rotate(-45deg); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; height: 40px;">Week ' . $history['rating_week'] . '</div>
			</div>';
		    }

		    $content .= '
		    </div>
		    <div style="display: flex; justify-content: space-between; margin-top: 20px;">
			<span style="font-size: 12px; color: #6c757d;">Past</span>
			<span style="font-size: 12px; color: #6c757d;">Current</span>
		    </div>
		</div>';
	    }

	    $content .= '
	    </div>
	</div>

	<div style="padding: 20px; background-color: #f8f9fc; border-left: 1px solid #e3e6f0; border-right: 1px solid #e3e6f0; border-bottom: 1px solid #e3e6f0; border-radius: 0 0 5px 5px;">
	    <p style="font-size: 16px;">Keep up the good work and reach out to your manager if you have any questions about your ratings.</p>

	    <p style="font-size: 16px;">Best regards,<br>' . APP_NAME . ' Team</p>
	</div>

	<div style="text-align: center; color: #6c757d; padding: 20px; font-size: 12px;">
	    <p>This is an automated message from ' . APP_NAME . '. Please do not reply to this email.</p>
	    <p>© ' . date('Y') . ' ' . APP_NAME . '</p>
	</div>
    </div>';

	    return $content;
    } 
}
