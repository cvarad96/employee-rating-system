<?php
/**
 * AuditLog class for tracking system changes
 */

require_once __DIR__ . '/../config/database.php';

class AuditLog {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Log an action
     * 
     * @param int $user_id The ID of the user who performed the action
     * @param string $action The action performed (create, update, delete, etc.)
     * @param string $entity_type The type of entity (department, team, employee, etc.)
     * @param int|null $entity_id The ID of the entity
     * @param string $description A description of the action
     * @param string|null $old_values The old values (usually JSON encoded)
     * @param string|null $new_values The new values (usually JSON encoded)
     * @return int|bool The ID of the new log entry or false on failure
     */
    public function log($user_id, $action, $entity_type, $entity_id = null, $description, $old_values = null, $new_values = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $sql = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, old_values, new_values, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $this->db->query($sql, [
                $user_id,
                $action,
                $entity_type,
                $entity_id,
                $description,
                $old_values,
                $new_values,
                $ip_address
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating audit log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all audit logs with optional filtering
     * 
     * @param array $filters Associative array of filters (user_id, action, entity_type, etc.)
     * @param int $limit Maximum number of logs to return
     * @param int $offset Starting offset for pagination
     * @return array Array of audit log records
     */
    public function getAll($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT a.*, u.username, u.first_name, u.last_name
                FROM audit_logs a
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $sql .= " AND a.action LIKE ?";
            $params[] = $filters['action'] . '%';
        }
        
        if (isset($filters['entity_type'])) {
            $sql .= " AND a.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (isset($filters['entity_id'])) {
            $sql .= " AND a.entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->resultset($sql, $params);
    }
    
    /**
     * Get total count of audit logs based on filters
     * 
     * @param array $filters Associative array of filters
     * @return int Total count of matching audit logs
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as count FROM audit_logs a WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $sql .= " AND a.action LIKE ?";
            $params[] = $filters['action'] . '%';
        }
        
        if (isset($filters['entity_type'])) {
            $sql .= " AND a.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (isset($filters['entity_id'])) {
            $sql .= " AND a.entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND a.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND a.created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $result = $this->db->single($sql, $params);
        
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get a specific audit log by ID
     * 
     * @param int $id The ID of the audit log
     * @return array|bool The audit log record or false if not found
     */
    public function getById($id) {
        $sql = "SELECT a.*, u.username, u.first_name, u.last_name
                FROM audit_logs a
                JOIN users u ON a.user_id = u.id
                WHERE a.id = ?";
        
        return $this->db->single($sql, [$id]);
    }
    
    /**
     * Get audit logs for a specific entity
     * 
     * @param string $entity_type The type of entity
     * @param int $entity_id The ID of the entity
     * @return array Array of audit log records
     */
    public function getForEntity($entity_type, $entity_id) {
        $sql = "SELECT a.*, u.username, u.first_name, u.last_name
                FROM audit_logs a
                JOIN users u ON a.user_id = u.id
                WHERE a.entity_type = ? AND a.entity_id = ?
                ORDER BY a.created_at DESC";
        
        return $this->db->resultset($sql, [$entity_type, $entity_id]);
    }
}
