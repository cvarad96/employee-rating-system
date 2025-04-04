<?php
/**
 * Header file to be included on all pages after authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config
$root_path = '/var/www/employee-rating-system';
require_once $root_path . '/config/config.php';
require_once $root_path . '/includes/auth.php';
require_once $root_path . '/includes/functions.php';
require_once $root_path . '/classes/Notification.php';

// Force login
requireLogin();

// Get unread notification count
$notification = new Notification();
$unreadCount = $notification->getUnreadCount($_SESSION['user_id']);

// Get user role
$isAdmin = isAdmin();
$isManager = isManager();

// Determine active page
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        main {
            flex: 1;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        @media (max-width: 767.98px) {
            .sidebar {
                position: static;
                padding-top: 0;
            }
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .sidebar .nav-link.active {
            color: #0d6efd;
        }
        
        .content {
            padding-top: 48px;
        }
        
        @media (min-width: 768px) {
            .content {
                margin-left: 240px;
            }
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        
        /* Profile dropdown styles */
        .profile-dropdown {
            position: relative;
        }
        
        .profile-dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 1000;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            font-size: 0.875rem;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
        }
        
        .profile-dropdown-menu.show {
            display: block;
        }
        
        .profile-dropdown-menu.hide {
            display: none;
        }
        
        .profile-dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }
        
        .profile-dropdown-item:hover, .profile-dropdown-item:focus {
            color: #1e2125;
            background-color: #f8f9fa;
            text-decoration: none;
        }
        
        .profile-dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }
        
        .profile-dropdown-item-text {
            padding: 0.5rem 1rem;
            color: #6c757d;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap d-flex align-items-center">
                <a class="nav-link px-3 position-relative me-2" href="<?php echo APP_URL; ?>/views/notifications.php">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $unreadCount; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <div class="profile-dropdown">
                    <a class="nav-link px-3" href="#" id="profileDropdownToggle">
                        <i class="bi bi-person-circle"></i>
                    </a>
                    <div class="profile-dropdown-menu hide" id="profileDropdownMenu">
                        <div class="profile-dropdown-item-text"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="profile-dropdown-divider"></div>
                        <a class="profile-dropdown-item" href="<?php echo APP_URL; ?>/change_password.php">
                            <i class="bi bi-key me-2"></i>Change Password
                        </a>
                        <a class="profile-dropdown-item" href="<?php echo APP_URL; ?>/views/auth/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="sidebar-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>
                            <?php if ($isAdmin): ?>
                                Admin Dashboard
                            <?php elseif ($isManager): ?>
                                Manager Dashboard
                            <?php else: ?>
                                Dashboard
                            <?php endif; ?>
                        </span>
                    </h6>
                    <ul class="nav flex-column">
                        <?php if ($isAdmin): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'departments.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/departments.php">
                                    <i class="bi bi-building me-1"></i>
                                    Departments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'managers.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/managers.php">
                                    <i class="bi bi-people me-1"></i>
                                    Managers
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'teams.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/teams.php">
                                    <i class="bi bi-diagram-3 me-1"></i>
                                    Teams
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/employees.php">
                                    <i class="bi bi-person me-1"></i>
                                    Employees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/reports.php">
                                    <i class="bi bi-bar-chart me-1"></i>
                                    Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'annual_report.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/annual_report.php">
                                    <i class="bi bi-calendar-range me-1"></i>
                                    Annual Report
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'audit_logs.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/admin/audit_logs.php">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Audit Logs
                                </a>
                            </li>
                        <?php elseif ($isManager): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/manager/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i>
                                    Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/manager/employees.php">
                                    <i class="bi bi-person me-1"></i>
                                    Employees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'ratings.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/manager/ratings.php">
                                    <i class="bi bi-star me-1"></i>
                                    Ratings
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($current_page == 'history.php') ? 'active' : ''; ?>" 
                                   href="<?php echo APP_URL; ?>/views/manager/history.php">
                                    <i class="bi bi-clock-history me-1"></i>
                                    History
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <?php if (isset($_SESSION['message']) && isset($_SESSION['message_type'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mt-3" role="alert">
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                <?php endif; ?>

<script>
    // Profile dropdown toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const profileToggle = document.getElementById('profileDropdownToggle');
        const profileMenu = document.getElementById('profileDropdownMenu');
        
        if (profileToggle && profileMenu) {
            // Toggle dropdown when clicking the profile icon
            profileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                profileMenu.classList.toggle('hide');
                profileMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!profileToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.add('hide');
                    profileMenu.classList.remove('show');
                }
            });
        }
    });
</script>
