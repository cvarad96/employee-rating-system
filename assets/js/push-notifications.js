/**
 * Push Notifications for Employee Rating System
 */

// VAPID public key - will need to be replaced with a real key generated with web-push
const VAPID_PUBLIC_KEY = 'BKrSF97GOYNaVT6w5sOGbsZM5mYmeLG8NSQOUu5IGzk3R6zvlrU93vqB7aUAF2VvVjfMtJWFBtTtgPSqJUTNj-A'; // Replace with your actual VAPID public key after generating it

// Function to convert base64 to Uint8Array (needed for push subscription)
function urlB64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');
    
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Check if browser supports push notifications
function arePushNotificationsSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
}

// Request permission for notifications
async function requestNotificationPermission() {
    if (!arePushNotificationsSupported()) {
        showNotificationToast('Push notifications are not supported by your browser', 'warning');
        return false;
    }
    
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            return true;
        } else {
            showNotificationToast('Notification permission denied', 'warning');
            return false;
        }
    } catch (error) {
        console.error('Error requesting notification permission:', error);
        return false;
    }
}

// Subscribe to push notifications
async function subscribeToPushNotifications() {
    if (!await requestNotificationPermission()) {
        return false;
    }
    
    try {
        // Wait for service worker to be ready
        const registration = await navigator.serviceWorker.ready;
        
        // Get existing subscription
        let subscription = await registration.pushManager.getSubscription();
        
        // If already subscribed, return the subscription
        if (subscription) {
            return subscription;
        }
        
        // Subscribe
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64ToUint8Array(VAPID_PUBLIC_KEY)
        });
        
        // Send to server
        await sendSubscriptionToServer(subscription, 'subscribe');
        
        showNotificationToast('Push notifications enabled!', 'success');
        
        return subscription;
    } catch (error) {
        console.error('Failed to subscribe to push notifications:', error);
        showNotificationToast('Failed to enable push notifications', 'danger');
        return null;
    }
}

// Unsubscribe from push notifications
async function unsubscribeFromPushNotifications() {
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        
        if (!subscription) {
            showNotificationToast('Not subscribed to push notifications', 'info');
            return true;
        }
        
        // Unsubscribe
        const result = await subscription.unsubscribe();
        
        if (result) {
            // Notify server
            await sendSubscriptionToServer(subscription, 'unsubscribe');
            showNotificationToast('Push notifications disabled', 'success');
        }
        
        return result;
    } catch (error) {
        console.error('Error unsubscribing from push notifications:', error);
        showNotificationToast('Failed to disable push notifications', 'danger');
        return false;
    }
}

// Send subscription info to server
async function sendSubscriptionToServer(subscription, action) {
    try {
        const response = await fetch('/employee-rating-system/api/push.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                subscription: subscription
            })
        });
        
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        return data.success;
    } catch (error) {
        console.error('Error sending subscription to server:', error);
        return false;
    }
}

// Check subscription status
async function checkPushSubscription() {
    if (!arePushNotificationsSupported()) {
        return false;
    }
    
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        return !!subscription;
    } catch (error) {
        console.error('Error checking push subscription:', error);
        return false;
    }
}

// Show a toast notification
function showNotificationToast(message, type = 'info') {
    // Bootstrap toast implementation
    const toastContainer = document.getElementById('toast-container');
    
    // Create container if it doesn't exist
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.getElementById('toast-container').appendChild(toast);
    
    // Initialize Bootstrap toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });
    
    bsToast.show();
}

// Add notification settings button
function addNotificationSettingsButton() {
    // Check if we're on the notifications page
    const notificationsLink = document.querySelector('a.nav-link[href*="notifications.php"]');
    
    if (notificationsLink) {
        // Create the dropdown container
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown';
        
        // Replace the link with a dropdown toggle button
        const toggleButton = document.createElement('button');
        toggleButton.className = 'nav-link dropdown-toggle position-relative px-3 me-2';
        toggleButton.setAttribute('data-bs-toggle', 'dropdown');
        toggleButton.setAttribute('aria-expanded', 'false');
        toggleButton.innerHTML = '<i class="bi bi-bell"></i>';
        
        // Add unread badge if present
        const badge = notificationsLink.querySelector('.badge');
        if (badge) {
            toggleButton.appendChild(badge.cloneNode(true));
        }
        
        // Create dropdown menu
        const dropdownMenu = document.createElement('div');
        dropdownMenu.className = 'dropdown-menu dropdown-menu-end';
        
        // Add menu items
        dropdownMenu.innerHTML = `
            <a class="dropdown-item" href="${notificationsLink.getAttribute('href')}">View Notifications</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#" id="enable-notifications">Enable Push Notifications</a>
            <a class="dropdown-item" href="#" id="disable-notifications">Disable Push Notifications</a>
        `;
        
        // Replace the original link with our dropdown
        const parent = notificationsLink.parentNode;
        dropdown.appendChild(toggleButton);
        dropdown.appendChild(dropdownMenu);
        parent.replaceChild(dropdown, notificationsLink);
        
        // Add event listeners
        document.getElementById('enable-notifications').addEventListener('click', function(e) {
            e.preventDefault();
            subscribeToPushNotifications();
        });
        
        document.getElementById('disable-notifications').addEventListener('click', function(e) {
            e.preventDefault();
            unsubscribeFromPushNotifications();
        });
        
        // Update button states based on current subscription
        checkPushSubscription().then(isSubscribed => {
            document.getElementById('enable-notifications').style.display = isSubscribed ? 'none' : 'block';
            document.getElementById('disable-notifications').style.display = isSubscribed ? 'block' : 'none';
        });
    }
}

// Initialize push notifications
document.addEventListener('DOMContentLoaded', function() {
    if (arePushNotificationsSupported()) {
        // Add toast container
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }
        
        // Add notification settings button
        addNotificationSettingsButton();
        
        // Check if we should ask for permission
        // This is disabled by default to avoid annoying users
        // We'll let them enable notifications via the dropdown menu
        
        /*
        // Only ask for permission if we haven't asked before
        const hasAskedBefore = localStorage.getItem('notificationPermissionAsked');
        
        if (!hasAskedBefore) {
            // Mark as asked
            localStorage.setItem('notificationPermissionAsked', 'true');
            
            // Wait a moment before asking
            setTimeout(() => {
                subscribeToPushNotifications();
            }, 3000);
        }
        */
    }
});
