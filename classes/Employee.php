<?php
/**
 * Employee class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLog.php';

class Employee {
    private $db;

    // Employee properties
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $position;
    public $created_by;
    public $created_at;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all employees
     */
    public function getAll($manager_id = null, $includeInactive = false) {
        $sql = "SELECT e.*, 
            (SELECT t.name FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.employee_id = e.id LIMIT 1) as team_name
            FROM employees e";
        $params = [];

        if ($manager_id) {
            $sql = "SELECT e.*, t.name as team_name
                FROM employees e
                JOIN team_members tm ON e.id = tm.employee_id
                JOIN teams t ON tm.team_id = t.id
                WHERE t.manager_id = ?";
            $params[] = $manager_id;

            if (!$includeInactive) {
                $sql .= " AND e.is_active = 1";
            }
        } else {
            // When not filtering by manager_id, add WHERE clause for is_active
            if (!$includeInactive) {
                $sql .= " WHERE e.is_active = 1";
            }
        }

        $sql .= " ORDER BY e.created_at DESC";

        return $this->db->resultset($sql, $params);
    } 

    /**
     * Get employee by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM employees WHERE id = ?";
        $employee = $this->db->single($sql, [$id]);

        if ($employee) {
            $this->id = $employee['id'];
            $this->first_name = $employee['first_name'];
            $this->last_name = $employee['last_name'];
            $this->email = $employee['email'];
            $this->phone = $employee['phone'];
            $this->position = $employee['position'];
            $this->created_by = $employee['created_by'];
            $this->created_at = $employee['created_at'];
        }

        return $employee;
    }


    /**
     * Create new employee or reactivate inactive employee
     */
    public function create($data) {
        // Check if an inactive employee with the same email exists
        $sql = "SELECT * FROM employees WHERE email = ? AND is_active = 0";
        $existingEmployee = $this->db->single($sql, [$data['email']]);

        if ($existingEmployee) {
            // Reactivate the existing employee
            $sql = "UPDATE employees SET
                first_name = ?,
                last_name = ?,
                phone = ?,
                position = ?,
                is_active = 1
                WHERE id = ?";

            try {
                $this->db->query($sql, [
                    $data['first_name'],
                    $data['last_name'],
                    $data['phone'],
                    $data['position'],
                    $existingEmployee['id']
                ]);

                $employee_id = $existingEmployee['id'];

                // Add to team if team_id is provided
                if (isset($data['team_id']) && $data['team_id']) {
                    $this->addToTeam($employee_id, $data['team_id']);
                }

                // Log the action
                $auditLog = new AuditLog();
                $auditLog->log(
                    $data['created_by'],
                    'reactivate',
                    'employee',
                    $employee_id,
                    "Reactivated employee: {$data['first_name']} {$data['last_name']}",
                    null,
                    json_encode([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'position' => $data['position'],
                        'team_id' => $data['team_id'] ?? null
                    ])
                );

                return $employee_id;
            } catch (PDOException $e) {
                throw $e;
            }
        }

        // Original code for creating new employee
        $sql = "INSERT INTO employees (first_name, last_name, email, phone, position, created_by)
            VALUES (?, ?, ?, ?, ?, ?)";

        try {
            $this->db->query($sql, [
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['position'],
                $data['created_by']
            ]);

            $employee_id = $this->db->lastInsertId();

            // Add to team if team_id is provided
            if (isset($data['team_id']) && $data['team_id']) {
                $this->addToTeam($employee_id, $data['team_id']);
            }

            // Log the action
            $auditLog = new AuditLog();
            $auditLog->log(
                $data['created_by'],
                'create',
                'employee',
                $employee_id,
                "Created new employee: {$data['first_name']} {$data['last_name']}",
                null,
                json_encode([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'position' => $data['position'],
                    'team_id' => $data['team_id'] ?? null
                ])
            );

            return $employee_id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return false; // Duplicate entry
            } else {
                throw $e;
            }
        }
    }

    /**
     * Update employee
     */
    public function update($data) {
        // Get current employee data for audit log
        $oldData = $this->getById($data['id']);
        $oldTeam = $this->getTeam($data['id']);

        $sql = "UPDATE employees SET first_name = ?, last_name = ?, email = ?, 
            phone = ?, position = ? WHERE id = ?";

        try {
            $this->db->query($sql, [
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['position'],
                $data['id']
            ]);

            // Update team if team_id is provided
            if (isset($data['team_id']) && $data['team_id']) {
                $this->updateTeam($data['id'], $data['team_id']);
            }

            // Log the action if updated_by is provided
            if (isset($data['updated_by'])) {
                $auditLog = new AuditLog();

                // Prepare description with important changes
                $description = "Updated employee: {$data['first_name']} {$data['last_name']}";

                // Note team change if applicable
                if (isset($data['team_id']) && $oldTeam && $oldTeam['id'] != $data['team_id']) {
                    $newTeamName = 'Unknown';
                    $sql = "SELECT name FROM teams WHERE id = ?";
                    $newTeam = $this->db->single($sql, [$data['team_id']]);
                    if ($newTeam) {
                        $newTeamName = $newTeam['name'];
                    }

                    $description .= " (team changed from {$oldTeam['name']} to {$newTeamName})";
                }

                $auditLog->log(
                    $data['updated_by'],
                    'update',
                    'employee',
                    $data['id'],
                    $description,
                    json_encode([
                        'first_name' => $oldData['first_name'],
                        'last_name' => $oldData['last_name'],
                        'email' => $oldData['email'],
                        'phone' => $oldData['phone'],
                        'position' => $oldData['position'],
                        'team_id' => $oldTeam ? $oldTeam['id'] : null
                    ]),
                    json_encode([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'],
                        'position' => $data['position'],
                        'team_id' => $data['team_id'] ?? null
                    ])
                );
            }

            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return false; // Duplicate entry
            } else {
                throw $e;
            }
        }
    }

    /**
     * Delete employee (soft delete)
     */
    public function delete($id, $deleted_by = null) {
        // Get employee data for audit log
        $oldData = $this->getById($id);
        $oldTeam = $this->getTeam($id);

        // Soft delete by marking as inactive
        $sql = "UPDATE employees SET is_active = 0 WHERE id = ?";

        try {
            $this->db->query($sql, [$id]);

            // Log the action if deleted_by is provided
            if ($deleted_by && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $deleted_by,
                    'delete',
                    'employee',
                    $id,
                    "Deleted employee: {$oldData['first_name']} {$oldData['last_name']}",
                    json_encode([
                        'first_name' => $oldData['first_name'],
                        'last_name' => $oldData['last_name'],
                        'email' => $oldData['email'],
                        'phone' => $oldData['phone'],
                        'position' => $oldData['position'],
                        'team_id' => $oldTeam ? $oldTeam['id'] : null,
                        'team_name' => $oldTeam ? $oldTeam['name'] : null
                    ]),
                    null
                );
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error soft deleting employee: " . $e->getMessage());
            return false;
        }
    } 

    /**
     * Add employee to team
     */
    public function addToTeam($employee_id, $team_id, $added_by = null) {
        $sql = "INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)";
        try {
            $this->db->query($sql, [$team_id, $employee_id]);

            // Log the action if added_by is provided
            if ($added_by) {
                $employee = $this->getById($employee_id);
                $teamName = 'Unknown';
                $sql = "SELECT name FROM teams WHERE id = ?";
                $team = $this->db->single($sql, [$team_id]);
                if ($team) {
                    $teamName = $team['name'];
                }

                $auditLog = new AuditLog();
                $auditLog->log(
                    $added_by,
                    'update',
                    'team_member',
                    $employee_id,
                    "Added employee: {$employee['first_name']} {$employee['last_name']} to team: {$teamName}",
                    null,
                    json_encode([
                        'employee_id' => $employee_id,
                        'team_id' => $team_id
                    ])
                );
            }

            return true;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                // Already in team, ignore
                return true;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Update employee team
     */
    public function updateTeam($employee_id, $team_id, $updated_by = null) {
        // Get current team for audit log
        $oldTeam = $this->getTeam($employee_id);

        // First remove from all teams
        $sql1 = "DELETE FROM team_members WHERE employee_id = ?";

        // Then add to new team
        $sql2 = "INSERT INTO team_members (team_id, employee_id) VALUES (?, ?)";

        try {
            $this->db->beginTransaction();
            $this->db->query($sql1, [$employee_id]);
            $this->db->query($sql2, [$team_id, $employee_id]);
            $this->db->endTransaction();

            // Log the action if updated_by is provided
            if ($updated_by) {
                $employee = $this->getById($employee_id);
                $teamName = 'Unknown';
                $sql = "SELECT name FROM teams WHERE id = ?";
                $team = $this->db->single($sql, [$team_id]);
                if ($team) {
                    $teamName = $team['name'];
                }

                $description = "Updated team for employee: {$employee['first_name']} {$employee['last_name']}";
                if ($oldTeam) {
                    $description .= " (changed from {$oldTeam['name']} to {$teamName})";
                } else {
                    $description .= " (assigned to {$teamName})";
                }

                $auditLog = new AuditLog();
                $auditLog->log(
                    $updated_by,
                    'update',
                    'team_member',
                    $employee_id,
                    $description,
                    $oldTeam ? json_encode(['team_id' => $oldTeam['id'], 'team_name' => $oldTeam['name']]) : null,
                    json_encode(['team_id' => $team_id, 'team_name' => $teamName])
                );
            }

            return true;
        } catch (PDOException $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    /**
     * Get employee's team
     */
    public function getTeam($employee_id) {
        $sql = "SELECT t.* FROM teams t 
            JOIN team_members tm ON t.id = tm.team_id 
            WHERE tm.employee_id = ?";

        return $this->db->single($sql, [$employee_id]);
    }

    /**
     * Get full name
     */
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get full name by ID
     */
    public function getFullNameById($id) {
        $sql = "SELECT first_name, last_name FROM employees WHERE id = ?";
        $result = $this->db->single($sql, [$id]);

        if ($result) {
            return $result['first_name'] . ' ' . $result['last_name'];
        }

        return 'Unknown';
    }

    /**
     * Get employees by team ID
     */
    public function getByTeamId($team_id, $includeInactive = false) {
        $sql = "SELECT e.* FROM employees e
            JOIN team_members tm ON e.id = tm.employee_id
            WHERE tm.team_id = ?";

        if (!$includeInactive) {
            $sql .= " AND e.is_active = 1";
        }

        $sql .= " ORDER BY e.first_name, e.last_name";

        return $this->db->resultset($sql, [$team_id]);
    } 

    /**
     * Check if employee belongs to manager
     */
    public function belongsToManager($employee_id, $manager_id) {
        $sql = "SELECT COUNT(*) as count FROM team_members tm
            JOIN teams t ON tm.team_id = t.id
            JOIN employees e ON tm.employee_id = e.id
            WHERE tm.employee_id = ? AND t.manager_id = ? AND e.is_active = 1";

        $result = $this->db->single($sql, [$employee_id, $manager_id]);

        return $result && $result['count'] > 0;
    }

    /**
     * Search employees
     *
     * @param string $searchTerm The search term to find in employee records
     * @param int $manager_id Optional manager ID to restrict search to a manager's employees
     * @param int $team_id Optional team ID to restrict search to a specific team
     * @return array Array of employee records matching the search criteria
     */
    public function search($searchTerm, $manager_id = null, $team_id = null) {
        $searchTerm = '%' . trim($searchTerm) . '%';
        $params = [];

        if ($manager_id && $team_id) {
            // Search within a specific team for a manager
            $sql = "SELECT e.*, t.name as team_name
                FROM employees e
                JOIN team_members tm ON e.id = tm.employee_id
                JOIN teams t ON tm.team_id = t.id
                WHERE t.manager_id = ? AND tm.team_id = ? AND e.is_active = 1
                AND (
                    e.first_name LIKE ? OR
                    e.last_name LIKE ? OR
                    CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR
                    e.email LIKE ? OR
                    e.position LIKE ?
                )
                ORDER BY e.first_name, e.last_name";

            $params = [$manager_id, $team_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        elseif ($manager_id) {
            // Search all teams for a manager
            $sql = "SELECT e.*, t.name as team_name
                FROM employees e
                JOIN team_members tm ON e.id = tm.employee_id
                JOIN teams t ON tm.team_id = t.id
                WHERE t.manager_id = ? AND e.is_active = 1
                AND (
                    e.first_name LIKE ? OR
                    e.last_name LIKE ? OR
                    CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR
                    e.email LIKE ? OR
                    e.position LIKE ?
                )
                ORDER BY e.first_name, e.last_name";

            $params = [$manager_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        elseif ($team_id) {
            // Search within a specific team (admin)
            $sql = "SELECT e.*,
                (SELECT t.name FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.employee_id = e.id LIMIT 1) as team_name
                FROM employees e
                JOIN team_members tm ON e.id = tm.employee_id
                WHERE tm.team_id = ? AND e.is_active = 1
                AND (
                    e.first_name LIKE ? OR
                    e.last_name LIKE ? OR
                    CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR
                    e.email LIKE ? OR
                    e.position LIKE ?
                )
                ORDER BY e.first_name, e.last_name";

            $params = [$team_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        else {
            // Search all employees (admin)
            $sql = "SELECT e.*,
                (SELECT t.name FROM teams t JOIN team_members tm ON t.id = tm.team_id WHERE tm.employee_id = e.id LIMIT 1) as team_name
                FROM employees e
                WHERE e.is_active = 1
                AND (
                    e.first_name LIKE ? OR
                    e.last_name LIKE ? OR
                    CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR
                    e.email LIKE ? OR
                    e.position LIKE ?
                )
                ORDER BY e.first_name, e.last_name";

            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        return $this->db->resultset($sql, $params);
    }

    /**
     * Get manager user ID for an employee if they are a manager
     *
     * @param int $employee_id The employee ID
     * @return int|null Manager user ID or null if employee is not a manager
     */
    public function getManagerUserIdByEmployeeId($employee_id) {
        $sql = "SELECT u.id FROM users u
            JOIN employees e ON u.email = e.email
            WHERE e.id = ? AND u.role = 'manager' AND u.is_active = 1";

        $result = $this->db->single($sql, [$employee_id]);
        return $result ? $result['id'] : null;
    }

    /**
     * Get employee ID for a manager
     *
     * @param int $manager_user_id The manager user ID
     * @return int|null Employee ID or null if not found
     */
    public function getEmployeeIdByManagerUserId($manager_user_id) {
        $sql = "SELECT e.id FROM employees e
            JOIN users u ON e.email = u.email
            WHERE u.id = ? AND e.is_active = 1";

        $result = $this->db->single($sql, [$manager_user_id]);
        return $result ? $result['id'] : null;
    }

    /**
     * Get all subordinate manager IDs in the hierarchy
     *
     * @param int $manager_employee_id The manager's employee ID
     * @return array Array of subordinate manager employee IDs
     */
    public function getSubordinateManagerIds($manager_employee_id) {
        $sql = "WITH RECURSIVE subordinates AS (
            SELECT manager_employee_id
            FROM manager_hierarchy
            WHERE reports_to_id = ?
            UNION ALL
            SELECT mh.manager_employee_id
            FROM manager_hierarchy mh
            JOIN subordinates s ON mh.reports_to_id = s.manager_employee_id
            )
            SELECT manager_employee_id FROM subordinates";

        return $this->db->resultset($sql, [$manager_employee_id]);
    }

    /**
     * Get all employees under a manager (direct and indirect)
     *
     * @param int $manager_id The manager user ID
     * @return array Array of employees
     */
    public function getAllHierarchyEmployees($manager_id) {
        // Get manager's employee ID
        $manager_employee_id = $this->getEmployeeIdByManagerUserId($manager_id);
        if (!$manager_employee_id) {
            return [];
        }

        // Get direct reports (team members)
        $direct_employees = $this->getAll($manager_id);

        // Get subordinate managers
        $subordinate_managers = $this->getSubordinateManagerIds($manager_employee_id);

        // Get indirect reports
        $indirect_employees = [];
        foreach ($subordinate_managers as $sub_manager) {
            $sub_manager_user_id = $this->getManagerUserIdByEmployeeId($sub_manager['manager_employee_id']);
            if ($sub_manager_user_id) {
                $team_employees = $this->getAll($sub_manager_user_id);
                foreach ($team_employees as $emp) {
                    // Add a flag to indicate this is an indirect report
                    $emp['is_indirect'] = true;
                    $emp['reporting_manager_id'] = $sub_manager_user_id;
                    $indirect_employees[] = $emp;
                }
            }
        }

        // Combine direct and indirect reports
        $all_employees = array_merge($direct_employees, $indirect_employees);

        return $all_employees;
    }
}
