<?php
/**
 * Department class
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLog.php';

class Department {
    private $db;

    // Department properties
    public $id;
    public $name;
    public $description;
    public $created_at;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all departments
     */
    public function getAll($includeInactive = false) {
        $sql = "SELECT d.*,
            (SELECT COUNT(*) FROM teams t WHERE t.department_id = d.id AND t.is_active = 1) as team_count,
            (SELECT COUNT(*) FROM rating_parameters rp WHERE rp.department_id = d.id) as parameter_count
            FROM departments d";

        // Only include active departments by default
        if (!$includeInactive) {
            $sql .= " WHERE d.is_active = 1";
        }

        $sql .= " ORDER BY d.name";

        return $this->db->resultset($sql);
    }

    /**
     * Check if department name exists
     * 
     * @param string $name Department name
     * @param int $excludeId Department ID to exclude from check (for updates)
     * @return bool True if name exists, false otherwise
     */
    public function nameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM departments WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->single($sql, $params);
        return $result && $result['count'] > 0;
    }

    /**
     * Get teams by department
     */
    public function getTeams($department_id) {
        $sql = "SELECT t.*, u.first_name, u.last_name FROM teams t
            JOIN users u ON t.manager_id = u.id
            WHERE t.department_id = ? AND t.is_active = 1
            ORDER BY t.name";

        return $this->db->resultset($sql, [$department_id]);
    } 

    /**
     * Get department by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM departments WHERE id = ?";
        $department = $this->db->single($sql, [$id]);

        if ($department) {
            $this->id = $department['id'];
            $this->name = $department['name'];
            $this->description = $department['description'];
            $this->created_at = $department['created_at'];
        }

        return $department;
    }

    /**
     * Create new department or reactivate inactive department
     */
    public function create($data) {
        // Check if an inactive department with the same name exists
        $sql = "SELECT * FROM departments WHERE name = ? AND is_active = 0";
        $existingDept = $this->db->single($sql, [$data['name']]);

        if ($existingDept) {
            // Reactivate the existing department
            $sql = "UPDATE departments SET 
                description = ?,
                is_active = 1
                WHERE id = ?";

            try {
                $this->db->query($sql, [
                    $data['description'],
                    $existingDept['id']
                ]);

                // Add audit log if created_by is provided
                if (isset($data['created_by'])) {
                    $auditLog = new AuditLog();
                    $auditLog->log(
                        $data['created_by'],
                        'reactivate',
                        'department',
                        $existingDept['id'],
                        "Reactivated department: {$data['name']}",
                        null,
                        json_encode([
                            'name' => $data['name'],
                            'description' => $data['description']
                        ])
                    );
                }

                return $existingDept['id'];
            } catch (PDOException $e) {
                return false;
            }
        }

        // Original code for creating new department
        $sql = "INSERT INTO departments (name, description) VALUES (?, ?)";

        try {
            $this->db->query($sql, [
                $data['name'],
                $data['description']
            ]);

            $id = $this->db->lastInsertId();

            // Add audit log if user_id is provided
            if (isset($data['created_by'])) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $data['created_by'],
                    'create',
                    'department',
                    $id,
                    "Created new department: {$data['name']}",
                    null,
                    json_encode([
                        'name' => $data['name'],
                        'description' => $data['description']
                    ])
                );
            }

            return $id;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return false; // Duplicate entry
            } else {
                throw $e;
            }
        }
    }

    /**
     * Update department
     */
    public function update($data) {
        // Get old values for audit log
        $oldData = null;
        if (isset($data['updated_by'])) {
            $oldData = $this->getById($data['id']);
        }

        $sql = "UPDATE departments SET name = ?, description = ? WHERE id = ?";

        try {
            $this->db->query($sql, [
                $data['name'],
                $data['description'],
                $data['id']
            ]);

            // Add audit log if user_id is provided
            if (isset($data['updated_by']) && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $data['updated_by'],
                    'update',
                    'department',
                    $data['id'],
                    "Updated department: {$data['name']}",
                    json_encode([
                        'name' => $oldData['name'],
                        'description' => $oldData['description']
                    ]),
                    json_encode([
                        'name' => $data['name'],
                        'description' => $data['description']
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
     * Delete department (soft delete)
     */
    public function delete($id, $deleted_by = null) {
        // Get old values for audit log
        $oldData = null;
        if ($deleted_by) {
            $oldData = $this->getById($id);
        }

        // Soft delete by marking as inactive
        $sql = "UPDATE departments SET is_active = 0 WHERE id = ?";

        try {
            $this->db->query($sql, [$id]);

            // Add audit log if user_id is provided
            if ($deleted_by && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $deleted_by,
                    'delete',
                    'department',
                    $id,
                    "Deleted department: {$oldData['name']}",
                    json_encode($oldData),
                    null
                );
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error soft deleting department: " . $e->getMessage());
            return false;
        }
    } 

    /**
     * Get department name by ID
     */
    public function getNameById($id) {
        $sql = "SELECT name FROM departments WHERE id = ?";
        $result = $this->db->single($sql, [$id]);

        if ($result) {
            return $result['name'];
        }

        return 'Unknown';
    }

    /**
     * Get rating parameters for department
     */
    public function getParameters($department_id) {
        $sql = "SELECT * FROM rating_parameters WHERE department_id = ? ORDER BY name";

        return $this->db->resultset($sql, [$department_id]);
    }

    /**
     * Add rating parameter
     */
    public function addParameter($data) {
        $sql = "INSERT INTO rating_parameters (department_id, name, description) 
            VALUES (?, ?, ?)";

        try {
            $this->db->query($sql, [
                $data['department_id'],
                $data['name'],
                $data['description']
            ]);

            $id = $this->db->lastInsertId();

            // Add audit log if user_id is provided
            if (isset($data['created_by'])) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $data['created_by'],
                    'create',
                    'parameter',
                    $id,
                    "Added rating parameter: {$data['name']} to department ID: {$data['department_id']}",
                    null,
                    json_encode([
                        'department_id' => $data['department_id'],
                        'name' => $data['name'],
                        'description' => $data['description']
                    ])
                );
            }

            return $id;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Update rating parameter
     */
    public function updateParameter($data) {
        // Get old values for audit log
        $oldData = null;
        if (isset($data['updated_by'])) {
            $sql = "SELECT * FROM rating_parameters WHERE id = ?";
            $oldData = $this->db->single($sql, [$data['id']]);
        }

        $sql = "UPDATE rating_parameters SET name = ?, description = ? WHERE id = ?";

        try {
            $this->db->query($sql, [
                $data['name'],
                $data['description'],
                $data['id']
            ]);

            // Add audit log if user_id is provided
            if (isset($data['updated_by']) && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $data['updated_by'],
                    'update',
                    'parameter',
                    $data['id'],
                    "Updated rating parameter: {$data['name']}",
                    json_encode($oldData),
                    json_encode([
                        'name' => $data['name'],
                        'description' => $data['description']
                    ])
                );
            }

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Delete rating parameter
     */
    public function deleteParameter($id, $deleted_by = null) {
        // Get old values for audit log
        $oldData = null;
        if ($deleted_by) {
            $sql = "SELECT * FROM rating_parameters WHERE id = ?";
            $oldData = $this->db->single($sql, [$id]);
        }

        $sql = "DELETE FROM rating_parameters WHERE id = ?";

        try {
            $this->db->query($sql, [$id]);

            // Add audit log if user_id is provided
            if ($deleted_by && $oldData) {
                $auditLog = new AuditLog();
                $auditLog->log(
                    $deleted_by,
                    'delete',
                    'parameter',
                    $id,
                    "Deleted rating parameter: {$oldData['name']}",
                    json_encode($oldData),
                    null
                );
            }

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
