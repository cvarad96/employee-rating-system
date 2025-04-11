<?php
/**
 * User class for Admin and Managers
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuditLog.php';

class User {
	private $db;

	// User properties
	public $id;
	public $username;
	public $email;
	public $first_name;
	public $last_name;
	public $role;
	public $created_at;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = Database::getInstance();
	}

	/**
	 * Get all users
	 */
	public function getAll($role = null, $includeInactive = false) {
		$sql = "SELECT * FROM users WHERE 1=1";
		$params = [];

		if (!$includeInactive) {
			$sql .= " AND is_active = 1";
		}

		if ($role) {
			$sql .= " AND role = ?";
			$params[] = $role;
		}

		$sql .= " ORDER BY created_at DESC";

		return $this->db->resultset($sql, $params);
	}	

	/**
	 * Get user by ID
	 */
	public function getById($id) {
		$sql = "SELECT * FROM users WHERE id = ?";
		$user = $this->db->single($sql, [$id]);

		if ($user) {
			$this->id = $user['id'];
			$this->username = $user['username'];
			$this->email = $user['email'];
			$this->first_name = $user['first_name'];
			$this->last_name = $user['last_name'];
			$this->role = $user['role'];
			$this->created_at = $user['created_at'];
		}

		return $user;
	}

	/**
	 * Create new user or reactivate inactive user
	 */
	public function create($data) {
		// Check if an inactive user with the same username or email exists
		$sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 0";
		$existingUser = $this->db->single($sql, [$data['username'], $data['email']]);

		if ($existingUser) {
			// Reactivate the existing user
			$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

			$sql = "UPDATE users SET
				password = ?,
				email = ?,
				first_name = ?,
				last_name = ?,
				role = ?,
				is_active = 1
				WHERE id = ?";

			try {
				$this->db->query($sql, [
					$password_hash,
					$data['email'],
					$data['first_name'],
					$data['last_name'],
					$data['role'],
					$existingUser['id']
				]);

				// Add audit log if created_by is provided
				if (isset($data['created_by'])) {
					$auditLog = new AuditLog();
					$auditLog->log(
						$data['created_by'],
						'reactivate',
						'user',
						$existingUser['id'],
						"Reactivated user: {$data['first_name']} {$data['last_name']} with role: {$data['role']}",
						null,
						json_encode([
							'username' => $data['username'],
							'email' => $data['email'],
							'first_name' => $data['first_name'],
							'last_name' => $data['last_name'],
							'role' => $data['role']
						])
					);
				}

				return $existingUser['id'];
			} catch (PDOException $e) {
				throw $e;
			}
		}

		// Original code for creating new user
		$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

		// Prepare SQL
		$sql = "INSERT INTO users (username, password, email, first_name, last_name, role)
			VALUES (?, ?, ?, ?, ?, ?)";

		// Execute query
		try {
			$this->db->query($sql, [
				$data['username'],
				$password_hash,
				$data['email'],
				$data['first_name'],
				$data['last_name'],
				$data['role']
			]);

			$user_id = $this->db->lastInsertId();

			// Add audit log if created_by is provided
			if (isset($data['created_by'])) {
				$auditLog = new AuditLog();
				$auditLog->log(
					$data['created_by'],
					'create',
					'user',
					$user_id,
					"Created new user: {$data['first_name']} {$data['last_name']} with role: {$data['role']}",
					null,
					json_encode([
						'username' => $data['username'],
						'email' => $data['email'],
						'first_name' => $data['first_name'],
						'last_name' => $data['last_name'],
						'role' => $data['role']
					])
				);
			}

			// Return the ID of the new user
			return $user_id;
		} catch (PDOException $e) {
			// Check for duplicate entry
			if ($e->getCode() == 23000) {
				return false;
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Update user
	 */
	public function update($data) {
		// Get current user data for audit log
		$oldData = null;
		if (isset($data['updated_by'])) {
			$oldData = $this->getById($data['id']);
		}

		// Start with basic SQL without password
		$sql = "UPDATE users SET username = ?, email = ?, first_name = ?, 
			last_name = ?, role = ? WHERE id = ?";
		$params = [
			$data['username'],
			$data['email'],
			$data['first_name'],
			$data['last_name'],
			$data['role'],
			$data['id']
		];

		$passwordChanged = false;

		// If password is provided, update it
		if (!empty($data['password'])) {
			$password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
			$sql = "UPDATE users SET username = ?, email = ?, first_name = ?, 
				last_name = ?, role = ?, password = ? WHERE id = ?";
			$params = [
				$data['username'],
				$data['email'],
				$data['first_name'],
				$data['last_name'],
				$data['role'],
				$password_hash,
				$data['id']
			];
			$passwordChanged = true;
		}

		// Execute query
		try {
			$this->db->query($sql, $params);

			// Add audit log if updated_by is provided
			if (isset($data['updated_by']) && $oldData) {
				$description = "Updated user: {$data['first_name']} {$data['last_name']}";

				if ($passwordChanged) {
					$description .= " (password changed)";
				}

				if ($oldData['role'] != $data['role']) {
					$description .= " (role changed from {$oldData['role']} to {$data['role']})";
				}

				$auditLog = new AuditLog();
				$auditLog->log(
					$data['updated_by'],
					'update',
					'user',
					$data['id'],
					$description,
					json_encode([
						'username' => $oldData['username'],
						'email' => $oldData['email'],
						'first_name' => $oldData['first_name'],
						'last_name' => $oldData['last_name'],
						'role' => $oldData['role']
					]),
					json_encode([
						'username' => $data['username'],
						'email' => $data['email'],
						'first_name' => $data['first_name'],
						'last_name' => $data['last_name'],
						'role' => $data['role'],
						'password_changed' => $passwordChanged
					])
				);
			}

			return true;
		} catch (PDOException $e) {
			if ($e->getCode() == 23000) {
				return false;
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Delete user
	 */
	public function delete($id, $deleted_by = null) {
		// Get current user data for audit log
		$oldData = null;
		if ($deleted_by) {
			$oldData = $this->getById($id);
		}

		// Instead of deleting, mark as inactive
		$sql = "UPDATE users SET is_active = 0 WHERE id = ?";

		try {
			$this->db->query($sql, [$id]);

			// Add audit log if deleted_by is provided
			if ($deleted_by && $oldData) {
				$auditLog = new AuditLog();
				$auditLog->log(
					$deleted_by,
					'delete',
					'user',
					$id,
					"Deleted user: {$oldData['first_name']} {$oldData['last_name']} with role: {$oldData['role']}",
					json_encode([
						'username' => $oldData['username'],
						'email' => $oldData['email'],
						'first_name' => $oldData['first_name'],
						'last_name' => $oldData['last_name'],
						'role' => $oldData['role']
					]),
					null
				);
			}

			return true;
		} catch (PDOException $e) {
			error_log("Error soft deleting user: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Login user
	 */
	public function login($username, $password) {
		$sql = "SELECT * FROM users WHERE username = ?";
		$user = $this->db->single($sql, [$username]);

		if ($user && password_verify($password, $user['password'])) {
			// Add login audit log
			$auditLog = new AuditLog();
			$auditLog->log(
				$user['id'],
				'login',
				'user',
				$user['id'],
				"User logged in: {$user['username']} ({$user['first_name']} {$user['last_name']})",
				null,
				null
			);

			return $user;
		}

		return false;
	}

	/**
	 * Get all managers
	 */
	public function getAllManagers($includeInactive = false) {
		return $this->getAll('manager', $includeInactive);
	}

	/**
	 * Get all admins
	 */
	public function getAllAdmins($includeInactive = false) {
		return $this->getAll('admin', $includeInactive);
	}

	/**
	 * Get username by ID
	 */
	public function getUsernameById($id) {
		$sql = "SELECT username FROM users WHERE id = ?";
		$result = $this->db->single($sql, [$id]);

		if ($result) {
			return $result['username'];
		}

		return 'Unknown';
	}

	/**
	 * Get full name by ID
	 */
	public function getFullNameById($id) {
		$sql = "SELECT first_name, last_name FROM users WHERE id = ?";
		$result = $this->db->single($sql, [$id]);

		if ($result) {
			return $result['first_name'] . ' ' . $result['last_name'];
		}

		return 'Unknown';
	}

	/**
	 * Get user ID by email
	 *
	 * @param string $email The user's email
	 * @return int|bool User ID if found, false otherwise
	 */
	public function getUserIdByEmail($email) {
		$sql = "SELECT id FROM users WHERE email = ?";
		$result = $this->db->single($sql, [$email]);

		if ($result) {
			return $result['id'];
		}

		return false;
	}

	/**
	 * Update user password
	 *
	 * @param int $user_id The user ID
	 * @param string $password The new password
	 * @return bool True on success, false on failure
	 */
	public function updatePassword($user_id, $password) {
		// Hash the new password
		$password_hash = password_hash($password, PASSWORD_DEFAULT);

		$sql = "UPDATE users SET password = ? WHERE id = ?";

		try {
			$this->db->query($sql, [$password_hash, $user_id]);

			// Add audit log entry
			$auditLog = new AuditLog();
			$auditLog->log(
				$user_id, // User is updating their own password
				'update',
				'user',
				$user_id,
				"User reset their password",
				null, // Don't log old password hash
				null  // Don't log new password hash
			);

			return true;
		} catch (PDOException $e) {
			error_log("Error updating password: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Change user password with current password verification
	 *
	 * @param int $user_id The user ID
	 * @param string $current_password The current password
	 * @param string $new_password The new password
	 * @return bool|string True on success, error message on failure
	 */
	public function changePassword($user_id, $current_password, $new_password) {
		// First verify current password
		$sql = "SELECT password FROM users WHERE id = ?";
		$user = $this->db->single($sql, [$user_id]);

		if (!$user) {
			return "User not found";
		}

		// Verify current password
		if (!password_verify($current_password, $user['password'])) {
			return "Current password is incorrect";
		}

		// Hash the new password
		$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

		$sql = "UPDATE users SET password = ? WHERE id = ?";

		try {
			$this->db->query($sql, [$password_hash, $user_id]);

			// Add audit log entry
			$auditLog = new AuditLog();
			$auditLog->log(
				$user_id,
				'update',
				'user',
				$user_id,
				"User changed their password",
				null, // Don't log old password hash
				null  // Don't log new password hash
			);

			return true;
		} catch (PDOException $e) {
			error_log("Error changing password: " . $e->getMessage());
			return "Database error: " . $e->getMessage();
		}
	}

	/**
	 * Admin reset password for user
	 *
	 * @param int $user_id User ID to reset password for
	 * @param string $new_password New password
	 * @param int $admin_id Admin user ID performing the reset
	 * @return bool True on success, false on failure
	 */
	public function adminResetPassword($user_id, $new_password, $admin_id) {
		// Hash the new password
		$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

		$sql = "UPDATE users SET password = ? WHERE id = ?";

		try {
			$this->db->query($sql, [$password_hash, $user_id]);

			// Get user info for audit log
			$user = $this->getById($user_id);
			$username = $user ? $user['username'] : 'Unknown';

			// Add audit log entry
			$auditLog = new AuditLog();
			$auditLog->log(
				$admin_id,
				'update',
				'user',
				$user_id,
				"Admin reset password for user: $username",
				null, // Don't log old password hash
				null  // Don't log new password hash
			);

			return true;
		} catch (PDOException $e) {
			error_log("Error resetting user password: " . $e->getMessage());
			return false;
		}
	}

}
