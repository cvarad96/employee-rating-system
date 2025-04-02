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
        
        // Start building the content
        $content = '
        <p>Hello ' . htmlspecialchars($employee['first_name']) . ',</p>
        
        <p>Here is your performance report for Week ' . $week . ' (' . $weekRange . ').</p>
        
        <h3>Weekly Summary</h3>
        <p>
            <strong>Team:</strong> ' . htmlspecialchars($employee['team_name'] ?? 'N/A') . '<br>
            <strong>Department:</strong> ' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '<br>
            <strong>Manager:</strong> ' . htmlspecialchars($employee['manager_name'] ?? 'N/A') . '<br>
            <strong>Overall Rating:</strong> <span class="rating-' . floor($averageRating) . '">' . $averageRating . ' / 5</span>
        </p>
        
        <h3>Detailed Ratings</h3>
        <table>
            <thead>
                <tr>
                    <th>Parameter</th>
                    <th>Rating</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($weeklyRatings as $rating) {
            $content .= '
                <tr>
                    <td>' . htmlspecialchars($rating['parameter_name']) . '</td>
                    <td class="rating-' . $rating['rating'] . '">' . $rating['rating'] . ' / 5</td>
                    <td>' . (empty($rating['comments']) ? 'No comments' : htmlspecialchars($rating['comments'])) . '</td>
                </tr>';
        }
        
        $content .= '
            </tbody>
        </table>
        
        <h3>Historical Performance (Past 3 Months)</h3>';
        
        if (empty($historicalRatings)) {
            $content .= '<p>No historical data available yet.</p>';
        } else {
            $content .= '
            <table>
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Average Rating</th>
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
                
                $content .= '
                    <tr>
                        <td>Week ' . $history['rating_week'] . ' (' . $historyRange . ')</td>
                        <td class="rating-' . floor($history['average_rating']) . '">' . 
                            round($history['average_rating'], 1) . ' / 5
                        </td>
                    </tr>';
            }
            
            $content .= '
                </tbody>
            </table>';
        }
        
        $content .= '
        <p>Keep up the good work and reach out to your manager if you have any questions about your ratings.</p>
        
        <p>Best regards,<br>' . APP_NAME . ' Team</p>';
        
        return $content;
    }
}
