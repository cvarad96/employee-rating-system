<?php
/**
 * Team class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLog.php';

class Team {
    private $db;
    
    // Team properties
    public $id;
    public $name;
    public $department_id;
    public $manager_id;
    public $created_at;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all teams
     */
    public function getAll($includeInactive = false) {
        $sql = "SELECT t.*, d.name as department_name, 
            CONCAT(u.first_name, ' ', u.last_name) as manager_name,
            (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
            FROM teams t
            JOIN departments d ON t.department_id = d.id
            JOIN users u ON t.manager_id = u.id";

        // Only include active teams by default
        if (!$includeInactive) {
            $sql .= " WHERE t.is_active = 1";
        }

        $sql .= " ORDER BY t.name";

        return $this->db->resultset($sql);
    }

    /**
     * Get teams by manager ID
     */
    public function getByManagerId($manager_id) {
        $sql = "SELECT t.*, d.name as department_name,
                (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count
                FROM teams t
                JOIN departments d ON t.department_id = d.id
                WHERE t.manager_id = ?
                ORDER BY t.name";
        
        return $this->db->resultset($sql, [$manager_id]);
    }
    
    /**
     * Get team by ID
     */
    public function getById($id) {
        $sql = "SELECT t.*, d.name as department_name, 
                CONCAT(u.first_name, ' ', u.last_name) as manager_name
                FROM teams t
                JOIN departments d ON t.department_id = d.id
                JOIN users u ON t.manager_id = u.id
                WHERE t.id = ?";
                
        $team = $this->db->single($sql, [$id]);
        
        if ($team) {
            $this->id = $team['id'];
            $this->name = $team['name'];
            $this->department_id = $team['department_id'];
            $this->manager_id = $team['manager_id'];
            $this->created_at = $team['created_at'];
        }
        
        return $team;
    }

    /**
     * Create new team or reactivate inactive team
     */
    public function create($data) {
        // Check if an inactive team with the same name exists
        $sql = "SELECT * FROM teams WHERE name = ? AND department_id = ? AND is_active = 0";
        $existingTeam = $this->db->single($sql, [$data['name'], $data['department_id']]);

        if ($existingTeam) {
            // Reactivate the existing team
            $sql = "UPDATE teams SET 
                manager_id = ?,
                is_active = 1
                WHERE id = ?";

            try {
                $this->db->query($sql, [
                    $data['manager_id'],
                    $existingTeam['id']
                ]);

                // Add audit log if created_by is provided
                if (isset($data['created_by'])) {
                    $auditLog = new AuditLog();

                    // Get department and manager names for better context
                    $deptName = 'Unknown';
                    $sql = "SELECT name FROM departments WHERE id = ?";
                    $dept = $this->db->single($sql, [$data['department_id']]);
                    if ($dept) {
                        $deptName = $dept['name'];
                    }

                    $managerName = 'Unknown';
                    $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
                    $mgr = $this->db->single($sql, [$data['manager_id']]);
                    if ($mgr) {
                        $managerName = $mgr['name'];
                    }

                    $auditLog->log(
                        $data['created_by'],
                        'reactivate',
                        'team',
                        $existingTeam['id'],
                        "Reactivated team: {$data['name']} in department: {$deptName} with manager: {$managerName}",
                        null,
                        json_encode([
                            'name' => $data['name'],
                            'department_id' => $data['department_id'],
                            'manager_id' => $data['manager_id']
                        ])
                    );
                }

                return $existingTeam['id'];
            } catch (PDOException $e) {
                return false;
            }
        }

        // Original code for creating new team
        $sql = "INSERT INTO teams (name, department_id, manager_id) VALUES (?, ?, ?)";

        try {
            $this->db->query($sql, [
                $data['name'],
                $data['department_id'],
                $data['manager_id']
            ]);

            $id = $this->db->lastInsertId();

            // Add audit log if created_by is provided
            if (isset($data['created_by'])) {
                $auditLog = new AuditLog();

                // Get department and manager names for better context
                $deptName = 'Unknown';
                $sql = "SELECT name FROM departments WHERE id = ?";
                $dept = $this->db->single($sql, [$data['department_id']]);
                if ($dept) {
                    $deptName = $dept['name'];
                }

                $managerName = 'Unknown';
                $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
                $mgr = $this->db->single($sql, [$data['manager_id']]);
                if ($mgr) {
                    $managerName = $mgr['name'];
                }

                $auditLog->log(
                    $data['created_by'],
                    'create',
                    'team',
                    $id,
                    "Created new team: {$data['name']} in department: {$deptName} with manager: {$managerName}",
                    null,
                    json_encode([
                        'name' => $data['name'],
                        'department_id' => $data['department_id'],
                        'manager_id' => $data['manager_id']
                    ])
                );
            }

            return $id;
        } catch (PDOException $e) {
            return false;
        }
    } 
    
    /**
     * Update team
     */
    public function update($data) {
        // Get old values for audit log
        $oldData = null;
        if (isset($data['updated_by'])) {
            $oldData = $this->getById($data['id']);
        }
        
        $sql = "UPDATE teams SET name = ?, department_id = ?, manager_id = ? WHERE id = ?";
        
        try {
            $this->db->query($sql, [
                $data['name'],
                $data['department_id'],
                $data['manager_id'],
                $data['id']
            ]);
            
            // Add audit log if updated_by is provided
            if (isset($data['updated_by']) && $oldData) {
                $auditLog = new AuditLog();
                
                // Get department and manager names for better context
                $oldDeptName = 'Unknown';
                $sql = "SELECT name FROM departments WHERE id = ?";
                $dept = $this->db->single($sql, [$oldData['department_id']]);
                if ($dept) {
                    $oldDeptName = $dept['name'];
                }
                
                $newDeptName = 'Unknown';
                $dept = $this->db->single($sql, [$data['department_id']]);
                if ($dept) {
                    $newDeptName = $dept['name'];
                }
                
                $description = "Updated team: {$data['name']}";
                
                // Note department change if applicable
                if ($oldData['department_id'] != $data['department_id']) {
                    $description .= " (department changed from {$oldDeptName} to {$newDeptName})";
                }
                
                // Note manager change if applicable
                if ($oldData['manager_id'] != $data['manager_id']) {
                    $oldManagerName = 'Unknown';
                    $sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
                    $mgr = $this->db->single($sql, [$oldData['manager_id']]);
                    if ($mgr) {
                        $oldManagerName = $mgr['name'];
                    }
                    
                    $newManagerName = 'Unknown';
                    $mgr = $this->db->single($sql, [$data['manager_id']]);
                    if ($mgr) {
                        $newManagerName = $mgr['name'];
                    }
                    
                    $description .= " (manager changed from {$oldManagerName} to {$newManagerName})";
                }
                
                $auditLog->log(
                    $data['updated_by'],
                    'update',
                    'team',
                    $data['id'],
                    $description,
                    json_encode([
                        'name' => $oldData['name'],
                        'department_id' => $oldData['department_id'],
                        'manager_id' => $oldData['manager_id']
                    ]),
                    json_encode([
                        'name' => $data['name'],
                        'department_id' => $data['department_id'],
                        'manager_id' => $data['manager_id']
                    ])
                );
            }
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Delete team (soft delete)
     */
    public function delete($id, $deleted_by = null) {
        // Get old values for audit log
        $oldData = null;
        if ($deleted_by) {
            $oldData = $this->getById($id);
        }

        // Soft delete by marking as inactive
        $sql = "UPDATE teams SET is_active = 0 WHERE id = ?";

        try {
            $this->db->query($sql, [$id]);

            // Add audit log if deleted_by is provided
            if ($deleted_by && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $deleted_by,
                    'delete',
                    'team',
                    $id,
                    "Deleted team: {$oldData['name']} from department: {$oldData['department_name']}",
                    json_encode($oldData),
                    null
                );
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error soft deleting team: " . $e->getMessage());
            return false;
        }
    } 
    
    /**
     * Check if team name exists
     * 
     * @param string $name Team name
     * @param int $departmentId Department ID
     * @param int $excludeId Team ID to exclude from check (for updates)
     * @return bool True if name exists, false otherwise
     */
    public function nameExists($name, $departmentId, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM teams 
            WHERE name = ? AND department_id = ?";
        $params = [$name, $departmentId];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->single($sql, $params);
        return $result && $result['count'] > 0;
    }

    /**
     * Get team name by ID
     */
    public function getNameById($id) {
        $sql = "SELECT name FROM teams WHERE id = ?";
        $result = $this->db->single($sql, [$id]);
        
        if ($result) {
            return $result['name'];
        }
        
        return 'Unknown';
    }
    
    /**
     * Get team members
     */
    public function getMembers($team_id) {
        $sql = "SELECT e.* FROM employees e
                JOIN team_members tm ON e.id = tm.employee_id
                WHERE tm.team_id = ?
                ORDER BY e.first_name, e.last_name";
        
        return $this->db->resultset($sql, [$team_id]);
    }
    
    /**
     * Get team member count
     */
    public function getMemberCount($team_id) {
        $sql = "SELECT COUNT(*) as count FROM team_members WHERE team_id = ?";
        $result = $this->db->single($sql, [$team_id]);
        
        if ($result) {
            return $result['count'];
        }
        
        return 0;
    }
    
    /**
     * Check if manager owns team
     */
    public function isOwnedByManager($team_id, $manager_id) {
        $sql = "SELECT COUNT(*) as count FROM teams WHERE id = ? AND manager_id = ?";
        $result = $this->db->single($sql, [$team_id, $manager_id]);
        
        return $result && $result['count'] > 0;
    }
}
