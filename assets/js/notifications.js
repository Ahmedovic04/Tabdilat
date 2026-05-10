/**
 * Notifications System JavaScript
 */

// Global variables
let notificationsData = [];
let isDropdownOpen = false;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeNotifications();
});

function initializeNotifications() {
    // Load notifications when dropdown is shown
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationDropdown) {
        notificationDropdown.addEventListener('show.bs.dropdown', function() {
            isDropdownOpen = true;
            loadNotifications();
        });
        
        notificationDropdown.addEventListener('hide.bs.dropdown', function() {
            isDropdownOpen = false;
        });
    }
    
    // Refresh unread count periodically
    setInterval(refreshUnreadCount, 30000); // Every 30 seconds
    
    // Initial unread count
    refreshUnreadCount();
}

/**
 * Load notifications from API
 */
async function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    try {
        const response = await fetch('/notifications_api.php?action=get_notifications&limit=10');
        const data = await response.json();
        
        if (data.success) {
            notificationsData = data.notifications;
            renderNotifications(notificationsData);
            updateBadge(data.unread_count);
        } else {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="bi bi-exclamation-circle"></i>
                    <p>حدث خطأ في تحميل الإشعارات</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="bi bi-wifi-off"></i>
                <p>تعذر الاتصال بالخادم</p>
            </div>
        `;
    }
}

/**
 * Render notifications list
 */
function renderNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    if (!notificationList) return;
    
    if (notifications.length === 0) {
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="bi bi-bell-slash"></i>
                <p>لا توجد إشعارات جديدة</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notifications.forEach(notification => {
        const iconClass = getNotificationIcon(notification.type);
        const typeClass = notification.type;
        const unreadClass = notification.is_read == 0 ? 'unread' : '';
        
        html += `
            <a href="#" class="notification-item ${unreadClass}" onclick="handleNotificationClick(event, ${notification.id}, ${notification.related_request_id || 'null'})">
                <div class="notification-icon ${typeClass}">
                    <i class="bi ${iconClass}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-time">${notification.time_ago}</div>
                </div>
            </a>
        `;
    });
    
    notificationList.innerHTML = html;
}

/**
 * Get icon based on notification type
 */
function getNotificationIcon(type) {
    const icons = {
        'substitution_request': 'bi-person-plus',
        'request_approved': 'bi-check-circle',
        'request_rejected': 'bi-x-circle',
        'compensation_reminder': 'bi-calendar-event',
        'system': 'bi-gear'
    };
    return icons[type] || 'bi-bell';
}

/**
 * Handle notification click
 */
async function handleNotificationClick(event, notificationId, requestId) {
    event.preventDefault();
    
    // Mark as read
    await markAsRead(notificationId);
    
    // Navigate to related request if exists
    if (requestId) {
        window.location.href = `/my_requests.php?id=${requestId}`;
    }
}

/**
 * Mark single notification as read
 */
async function markAsRead(notificationId) {
    try {
        const response = await fetch('/notifications_api.php?action=mark_read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notification_id: notificationId })
        });
        
        const data = await response.json();
        if (data.success) {
            updateBadge(data.unread_count);
            // Update the item in the list
            const item = document.querySelector(`.notification-item[onclick*="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
            }
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

/**
 * Mark all notifications as read
 */
async function markAllNotificationsRead() {
    try {
        const response = await fetch('/notifications_api.php?action=mark_all_read', {
            method: 'POST'
        });
        
        const data = await response.json();
        if (data.success) {
            updateBadge(0);
            // Update all items
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
        }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

/**
 * Refresh unread count
 */
async function refreshUnreadCount() {
    try {
        const response = await fetch('/notifications_api.php?action=get_unread_count');
        const data = await response.json();
        
        if (data.success) {
            updateBadge(data.unread_count);
        }
    } catch (error) {
        console.error('Error refreshing unread count:', error);
    }
}

/**
 * Update notification badge
 */
function updateBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export functions for global access
window.markAllNotificationsRead = markAllNotificationsRead;
window.handleNotificationClick = handleNotificationClick;
