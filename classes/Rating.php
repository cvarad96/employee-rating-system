<?php
/**
 * Rating class with audit logging
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLog.php';

class Rating {
	private $db;

	// Rating properties
	public $id;
	public $employee_id;
	public $parameter_id;
	public $rating;
	public $rated_by;
	public $rating_week;
	public $rating_year;
	public $comments;
	public $created_at;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = Database::getInstance();
	}

	/**
	 * Get database instance - safer method to access the database
	 */
	public function getDb() {
		return $this->db;
	}

	/**
	 * Get ratings by employee ID
	 */
	public function getByEmployeeId($employee_id) {
		$sql = "SELECT er.*, rp.name as parameter_name, 
			CONCAT(u.first_name, ' ', u.last_name) as rated_by_name
			FROM employee_ratings er
			JOIN rating_parameters rp ON er.parameter_id = rp.id
			JOIN users u ON er.rated_by = u.id
			WHERE er.employee_id = ?
			ORDER BY er.rating_year DESC, er.rating_week DESC, rp.name";

		return $this->db->resultset($sql, [$employee_id]);
	}

	/**
	 * Get ratings by week and year
	 */
	public function getByWeekYear($week, $year, $manager_id = null) {
		$sql = "SELECT er.*, rp.name as parameter_name, 
			CONCAT(e.first_name, ' ', e.last_name) as employee_name,
			t.id as team_id, t.name as team_name, 
			d.id as department_id, d.name as department_name,
			CONCAT(u.first_name, ' ', u.last_name) as rated_by_name
			FROM employee_ratings er
			JOIN rating_parameters rp ON er.parameter_id = rp.id
			JOIN employees e ON er.employee_id = e.id
			JOIN team_members tm ON e.id = tm.employee_id
			JOIN teams t ON tm.team_id = t.id
			JOIN departments d ON t.department_id = d.id
			JOIN users u ON er.rated_by = u.id
			WHERE er.rating_week = ? AND er.rating_year = ?";

		$params = [$week, $year];

		if ($manager_id) {
			$sql .= " AND t.manager_id = ?";
			$params[] = $manager_id;
		}

		$sql .= " ORDER BY d.name, t.name, e.first_name, e.last_name, rp.name";

		return $this->db->resultset($sql, $params);
	}

	/**
	 * Get specific rating
	 */
	public function getSpecificRating($employee_id, $parameter_id, $week, $year) {
		$sql = "SELECT * FROM employee_ratings 
			WHERE employee_id = ? AND parameter_id = ? AND rating_week = ? AND rating_year = ?";

		return $this->db->single($sql, [$employee_id, $parameter_id, $week, $year]);
	}

	/**
	 * Create or update rating with audit logging
	 */
	public function saveRating($data) {
		// Check if rating exists
		$existing = $this->getSpecificRating(
			$data['employee_id'], 
			$data['parameter_id'], 
			$data['rating_week'], 
			$data['rating_year']
		);

		// Get employee and parameter info for better audit logs
		$employeeName = 'Unknown';
		$sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?";
		$emp = $this->db->single($sql, [$data['employee_id']]);
		if ($emp) {
			$employeeName = $emp['name'];
		}

		$parameterName = 'Unknown';
		$sql = "SELECT name FROM rating_parameters WHERE id = ?";
		$param = $this->db->single($sql, [$data['parameter_id']]);
		if ($param) {
			$parameterName = $param['name'];
		}

		if ($existing) {
			// Update existing
			$sql = "UPDATE employee_ratings SET rating = ?, comments = ? 
				WHERE id = ?";

			try {
				$this->db->query($sql, [
					$data['rating'],
					$data['comments'],
					$existing['id']
				]);

				// Add audit log
				$auditLog = new AuditLog();
				$auditLog->log(
					$data['rated_by'],
					'update',
					'rating',
					$existing['id'],
					"Updated rating for {$employeeName} on parameter: {$parameterName} (Week {$data['rating_week']}, {$data['rating_year']})",
					json_encode([
						'rating' => $existing['rating'],
						'comments' => $existing['comments']
					]),
					json_encode([
						'rating' => $data['rating'],
						'comments' => $data['comments']
					])
				);

				return ['success' => true, 'id' => $existing['id']];
			} catch (PDOException $e) {
				return ['success' => false, 'errors' => [$e->getMessage()]];
			}
		} else {
			// Create new
			$sql = "INSERT INTO employee_ratings 
				(employee_id, parameter_id, rating, rated_by, rating_week, 
				rating_year, comments) 
				VALUES (?, ?, ?, ?, ?, ?, ?)";

			try {
				$this->db->query($sql, [
					$data['employee_id'],
					$data['parameter_id'],
					$data['rating'],
					$data['rated_by'],
					$data['rating_week'],
					$data['rating_year'],
					$data['comments']
				]);

				$id = $this->db->lastInsertId();

				// Add audit log
				$auditLog = new AuditLog();
				$auditLog->log(
					$data['rated_by'],
					'create',
					'rating',
					$id,
					"Created new rating for {$employeeName} on parameter: {$parameterName} (Week {$data['rating_week']}, {$data['rating_year']})",
					null,
					json_encode([
						'employee_id' => $data['employee_id'],
						'parameter_id' => $data['parameter_id'],
						'rating' => $data['rating'],
						'rating_week' => $data['rating_week'],
						'rating_year' => $data['rating_year'],
						'comments' => $data['comments']
					])
				);

				return ['success' => true, 'id' => $id];
			} catch (PDOException $e) {
				return ['success' => false, 'errors' => [$e->getMessage()]];
			}
		}
	}

	/**
	 * Get Admin users (formerly CEO)
	 */
	public function getAdmins() {
		$sql = "SELECT id FROM users WHERE role = 'admin'";
		return $this->db->resultset($sql);
	}

	/**
	 * Delete rating
	 */
	public function delete($id, $deleted_by = null) {
		// Get old values for audit log
		$oldData = null;
		if ($deleted_by) {
			$sql = "SELECT er.*, 
				CONCAT(e.first_name, ' ', e.last_name) as employee_name,
				rp.name as parameter_name
				FROM employee_ratings er
				JOIN employees e ON er.employee_id = e.id
				JOIN rating_parameters rp ON er.parameter_id = rp.id
				WHERE er.id = ?";
			$oldData = $this->db->single($sql, [$id]);
		}

		$sql = "DELETE FROM employee_ratings WHERE id = ?";

		try {
			$this->db->query($sql, [$id]);

			// Add audit log if deleted_by is provided
			if ($deleted_by && $oldData) {
				$auditLog = new AuditLog();
				$auditLog->log(
					$deleted_by,
					'delete',
					'rating',
					$id,
					"Deleted rating for {$oldData['employee_name']} on parameter: {$oldData['parameter_name']} (Week {$oldData['rating_week']}, {$oldData['rating_year']})",
					json_encode($oldData),
					null
				);
			}

			return true;
		} catch (PDOException $e) {
			return false;
		}
	}

	/**
	 * Get average ratings by employee
	 */
	public function getAverageByEmployee($employee_id) {
		$sql = "SELECT AVG(rating) as average_rating, 
			rating_week, rating_year
			FROM employee_ratings
			WHERE employee_id = ?
			GROUP BY rating_week, rating_year
			ORDER BY rating_year DESC, rating_week DESC";

		return $this->db->resultset($sql, [$employee_id]);
	}

	/**
	 * Get average ratings by parameter
	 */
	public function getAverageByParameter($employee_id) {
		$sql = "SELECT AVG(er.rating) as average_rating, 
			er.parameter_id, rp.name as parameter_name
			FROM employee_ratings er
			JOIN rating_parameters rp ON er.parameter_id = rp.id
			WHERE er.employee_id = ?
			GROUP BY er.parameter_id
			ORDER BY rp.name";

		return $this->db->resultset($sql, [$employee_id]);
	}

	/**
	 * Get average ratings by team
	 */
	public function getAverageByTeam($team_id, $week = null, $year = null) {
		$sql = "SELECT AVG(er.rating) as average_rating,
			tm.team_id, t.name as team_name
			FROM employee_ratings er
			JOIN employees e ON er.employee_id = e.id
			JOIN team_members tm ON e.id = tm.employee_id
			JOIN teams t ON tm.team_id = t.id
			WHERE tm.team_id = ?";

		$params = [$team_id];

		if ($week && $year) {
			$sql .= " AND er.rating_week = ? AND er.rating_year = ?";
			$params[] = $week;
			$params[] = $year;
		}

		$sql .= " GROUP BY tm.team_id";

		return $this->db->single($sql, $params);
	}

	/**
	 * Get employees pending ratings
	 */
	public function getPendingRatings($manager_id, $week, $year) {
		$sql = "SELECT e.id, e.first_name, e.last_name, t.name as team_name,
			(SELECT COUNT(*) FROM rating_parameters rp 
			JOIN departments d ON rp.department_id = d.id
			JOIN teams t2 ON d.id = t2.department_id
			WHERE t2.id = t.id) as total_parameters,
			(SELECT COUNT(*) FROM employee_ratings er 
			WHERE er.employee_id = e.id
			AND er.rating_week = ? AND er.rating_year = ?) as rated_parameters
			FROM employees e
			JOIN team_members tm ON e.id = tm.employee_id
			JOIN teams t ON tm.team_id = t.id
			WHERE t.manager_id = ?
			ORDER BY t.name, e.first_name, e.last_name";

		return $this->db->resultset($sql, [$week, $year, $manager_id]);
	}

	/**
	 * Get current week and year
	 */
	public function getCurrentWeekYear() {
		$week = date('W');
		$year = date('Y');

		return ['week' => $week, 'year' => $year];
    }

    /**
     * Check if there are any incomplete ratings for previous weeks
     *
     * @param int $manager_id Manager ID
     * @param int $current_week Current week number
     * @param int $current_year Current year
     * @return array|bool Array of incomplete weeks data or false if all complete
     */
    public function hasIncompletePreviousWeeks($manager_id, $current_week, $current_year) {
        // Generate previous weeks to check (only check 2 weeks back)
        $previousWeeks = [];

        // Get last 2 weeks
        for ($i = 1; $i <= 2; $i++) {
            $date = new DateTime();
            $date->setISODate($current_year, $current_week);
            $date->modify("-$i week");

            $weekNum = (int)$date->format('W');
            $yearNum = (int)$date->format('Y');

            $previousWeeks[] = [
                'week' => $weekNum,
                'year' => $yearNum
            ];
        }

        // Check each week for incomplete ratings
        $incompleteWeeks = [];

        foreach ($previousWeeks as $week) {
            $pendingRatings = $this->getPendingRatings($manager_id, $week['week'], $week['year']);

            // Check if any employees have incomplete ratings
            $incompleteEmployees = [];
            foreach ($pendingRatings as $pending) {
                if ($pending['rated_parameters'] < $pending['total_parameters']) {
                    $incompleteEmployees[] = [
                        'id' => $pending['id'],
                        'name' => $pending['first_name'] . ' ' . $pending['last_name'],
                        'team_name' => $pending['team_name'],
                        'rated' => $pending['rated_parameters'],
                        'total' => $pending['total_parameters']
                    ];
                }
            }

            if (!empty($incompleteEmployees)) {
                $weekDateStart = new DateTime();
                $weekDateStart->setISODate($week['year'], $week['week'], 1); // Monday
                $weekDateEnd = clone $weekDateStart;
                $weekDateEnd->modify('+6 days'); // Sunday

                $incompleteWeeks[] = [
                    'week' => $week['week'],
                    'year' => $week['year'],
                    'formatted' => $weekDateStart->format('M j') . ' - ' . $weekDateEnd->format('M j, Y'),
                    'employees' => $incompleteEmployees
                ];
            }
        }

        if (empty($incompleteWeeks)) {
            return false;
        }

        return $incompleteWeeks;
    }
}
