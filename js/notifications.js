/**
 * Notifications System for NileTech Learning Platform
 * Handles fetching, displaying, and marking notifications as read
 */

let notificationPollInterval = null;

// Initialize notifications system
function initNotifications() {
    // Check if user is logged in
    const notificationBell = document.getElementById('notification-bell');
    if (!notificationBell) {
        return; // User not logged in or bell not present
    }
    
    // Load initial notifications
    fetchNotifications();
    
    // Set up polling (refresh every 25 seconds)
    notificationPollInterval = setInterval(fetchNotifications, 25000);
    
    // Set up click handler for notification bell
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleNotificationDropdown();
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notification-dropdown');
        if (dropdown && !dropdown.contains(e.target) && !notificationBell.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function fetchNotifications() {
    fetch('notifications/fetch.php?limit=10&unread_only=false')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                updateNotificationDropdown(data.notifications, data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

function updateNotificationBadge(unreadCount) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function updateNotificationDropdown(notifications, unreadCount) {
    const dropdown = document.getElementById('notification-dropdown');
    if (!dropdown) {
        return;
    }
    
    let html = '<div class="notification-header">';
    html += `<h3>Notifications ${unreadCount > 0 ? `(${unreadCount})` : ''}</h3>`;
    if (unreadCount > 0) {
        html += '<button id="mark-all-read-btn" class="btn-mark-all-read">Mark all as read</button>';
    }
    html += '</div>';
    
    if (notifications.length === 0) {
        html += '<div class="notification-empty">No notifications yet</div>';
    } else {
        html += '<div class="notification-list">';
        notifications.forEach(notification => {
            const readClass = notification.is_read ? 'read' : 'unread';
            const icon = getNotificationIcon(notification.type);
            html += `
                <div class="notification-item ${readClass}" data-notification-id="${notification.id}">
                    <div class="notification-icon">${icon}</div>
                    <div class="notification-content">
                        <p class="notification-message">${escapeHtml(notification.message)}</p>
                        <span class="notification-time">${notification.time_ago}</span>
                    </div>
                    ${!notification.is_read ? '<div class="notification-unread-indicator"></div>' : ''}
                </div>
            `;
        });
        html += '</div>';
    }
    
    dropdown.innerHTML = html;
    
    // Add click handlers
    const notificationItems = dropdown.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = parseInt(this.dataset.notificationId);
            markNotificationAsRead(notificationId);
        });
    });
    
    // Add mark all as read handler
    const markAllBtn = dropdown.querySelector('#mark-all-read-btn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    if (dropdown) {
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
            fetchNotifications(); // Refresh when opening
        }
    }
}

function markNotificationAsRead(notificationId) {
    fetch('notifications/mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                notificationItem.classList.add('read');
                const indicator = notificationItem.querySelector('.notification-unread-indicator');
                if (indicator) {
                    indicator.remove();
                }
            }
            
            // Update badge
            updateNotificationBadge(data.unread_count);
            
            // Refresh dropdown
            fetchNotifications();
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsAsRead() {
    fetch('notifications/mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            mark_all: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const unreadItems = document.querySelectorAll('.notification-item.unread');
            unreadItems.forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
                const indicator = item.querySelector('.notification-unread-indicator');
                if (indicator) {
                    indicator.remove();
                }
            });
            
            // Update badge
            updateNotificationBadge(data.unread_count);
            
            // Refresh dropdown
            fetchNotifications();
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function getNotificationIcon(type) {
    const icons = {
        'lesson_completed': '✅',
        'feedback_reply': '💬',
        'admin_message': '📢',
        'mentor_reply': '👨‍🏫',
        'default': '🔔'
    };
    return icons[type] || icons['default'];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initNotifications();
});

// Clean up interval on page unload
window.addEventListener('beforeunload', function() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
    }
});

