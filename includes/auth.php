<?php
/**
 * Authentication functions
 */

session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if the current user is an Admin (previously CEO)
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// Check if the current user is a Manager
function isManager() {
    return isLoggedIn() && $_SESSION['user_role'] === 'manager';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['message'] = 'Please log in to access this page';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

// Redirect if not Admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['message'] = 'You do not have permission to access this page';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

// Redirect if not Manager
function requireManager() {
    requireLogin();
    if (!isManager()) {
        $_SESSION['message'] = 'You do not have permission to access this page';
        $_SESSION['message_type'] = 'danger';
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

// Login user
function loginUser($id, $username, $role) {
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['last_activity'] = time();
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
}

// Check session timeout
function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            logoutUser();
            $_SESSION['message'] = 'Your session has expired. Please log in again.';
            $_SESSION['message_type'] = 'warning';
            header('Location: ' . APP_URL . '/index.php');
            exit;
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}

// Run timeout check on every page load
checkSessionTimeout();
?>
