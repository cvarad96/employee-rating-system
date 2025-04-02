<?php
/**
 * Notifications Page
 */

// Set absolute path resolution to go up one directory
$root_dir = dirname(dirname(__FILE__));

// Include necessary files with correct paths
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/auth.php';
require_once $root_dir . '/classes/Notification.php';

// Ensure user is logged in
requireLogin();

// Get user ID
$user_id = $_SESSION['user_id'];

// Initialize notification object
$notification = new Notification();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_read') {
            $notification_id = $_POST['notification_id'];
            $notification->markAsRead($notification_id);
            
            $_SESSION['message'] = 'Notification marked as read';
            $_SESSION['message_type'] = 'success';
            
        } elseif ($_POST['action'] === 'mark_all_read') {
            $notification->markAllAsRead($user_id);
            
            $_SESSION['message'] = 'All notifications marked as read';
            $_SESSION['message_type'] = 'success';
            
        } elseif ($_POST['action'] === 'delete') {
            $notification_id = $_POST['notification_id'];
            $notification->delete($notification_id);
            
            $_SESSION['message'] = 'Notification deleted';
            $_SESSION['message_type'] = 'success';
        }
        
        // Redirect to avoid form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get notifications for the user
$notifications = $notification->getForUser($user_id, 100);

// Count unread notifications
$unreadCount = $notification->getUnreadCount($user_id);

// Include header
include $root_dir . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Notifications</h1>
    <?php if (!empty($notifications)): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <form method="post">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-check-all me-1"></i> Mark All as Read
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i> You have no notifications.
    </div>
<?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">
                Your Notifications
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="list-group">
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item list-group-item-action <?php echo $n['is_read'] ? '' : 'list-group-item-primary'; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">
                                <?php if (!$n['is_read']): ?>
                                <i class="bi bi-circle-fill text-primary me-2" style="font-size: 0.5rem;"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($n['message']); ?>
                            </h5>
                            <small class="text-muted"><?php echo formatDateTime($n['created_at']); ?></small>
                        </div>
                        <div class="mt-2">
                            <?php if (!$n['is_read']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-check"></i> Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include $root_dir . '/includes/footer.php';
?>
