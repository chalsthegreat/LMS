// Notification System JavaScript
let notificationsCache = [];

// Load notifications
async function loadNotifications() {
    try {
        const response = await fetch('../notifications/get_notifications.php?limit=10');
        const data = await response.json();
        
        if (data.success) {
            notificationsCache = data.notifications;
            renderNotifications(data.notifications);
            updateNotificationBadge(data.unread_count);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

// Render notifications in dropdown
function renderNotifications(notifications) {
    const dropdown = document.getElementById('notification-dropdown');
    
    if (notifications.length === 0) {
        dropdown.innerHTML = `
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-bell-slash text-4xl mb-2"></i>
                <p>No notifications yet</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Notifications</h3>
            <button onclick="markAllAsRead()" class="text-sm text-blue-600 hover:text-blue-700">
                Mark all as read
            </button>
        </div>
        <div class="max-h-96 overflow-y-auto">
    `;
    
    notifications.forEach(notif => {
        const bgColor = notif.is_read ? 'bg-white' : 'bg-blue-50';
        const iconColor = getIconColor(notif.color);
        
        html += `
            <div class="${bgColor} border-b hover:bg-gray-50 transition" data-notif-id="${notif.id}">
                <a href="${notif.link || '#'}" onclick="markAsReadAndNavigate(event, ${notif.id}, '${notif.link || ''}')" 
                   class="flex items-start p-4 space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full ${iconColor} flex items-center justify-center">
                            <i class="fas ${notif.icon} text-white"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">${notif.title}</p>
                        <p class="text-sm text-gray-600 mt-1">${notif.message}</p>
                        <p class="text-xs text-gray-400 mt-1">${notif.time_ago}</p>
                    </div>
                    ${!notif.is_read ? '<div class="flex-shrink-0"><div class="w-2 h-2 bg-blue-600 rounded-full"></div></div>' : ''}
                </a>
                <div class="px-4 pb-2 flex justify-end space-x-2">
                    ${!notif.is_read ? `<button onclick="markSingleAsRead(event, ${notif.id})" class="text-xs text-blue-600 hover:text-blue-700">Mark as read</button>` : ''}
                    <button onclick="deleteNotification(event, ${notif.id})" class="text-xs text-red-600 hover:text-red-700">Delete</button>
                </div>
            </div>
        `;
    });
    
    html += `
        </div>
        <div class="p-3 border-t bg-gray-50 text-center">
            <a href="../notifications/view_all.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                View all notifications
            </a>
        </div>
    `;
    
    dropdown.innerHTML = html;
}

// Get icon color class
function getIconColor(color) {
    const colors = {
        blue: 'bg-blue-500',
        green: 'bg-green-500',
        red: 'bg-red-500',
        orange: 'bg-orange-500',
        yellow: 'bg-yellow-500',
        purple: 'bg-purple-500',
        gray: 'bg-gray-500'
    };
    return colors[color] || 'bg-gray-500';
}

// Update notification badge
function updateNotificationBadge(count) {
    const badge = document.getElementById('notif-badge');
    const badgeMobile = document.getElementById('notif-badge-mobile');
    
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
            badge.style.display = 'inline-flex';
        }
        if (badgeMobile) {
            badgeMobile.textContent = count;
            badgeMobile.style.display = 'inline-flex';
        }
    } else {
        if (badge) badge.style.display = 'none';
        if (badgeMobile) badgeMobile.style.display = 'none';
    }
}

// Mark as read and navigate
async function markAsReadAndNavigate(event, notifId, link) {
    event.preventDefault();
    
    try {
        const formData = new FormData();
        formData.append('notification_id', notifId);
        
        await fetch('../notifications/mark_read.php', {
            method: 'POST',
            body: formData
        });
        
        if (link && link !== '') {
            window.location.href = link;
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark single notification as read
async function markSingleAsRead(event, notifId) {
    event.preventDefault();
    event.stopPropagation();
    
    try {
        const formData = new FormData();
        formData.append('notification_id', notifId);
        
        const response = await fetch('../notifications/mark_read.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all as read
async function markAllAsRead() {
    try {
        const formData = new FormData();
        formData.append('mark_all', '1');
        
        const response = await fetch('../notifications/mark_read.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

// Delete notification
async function deleteNotification(event, notifId) {
    event.preventDefault();
    event.stopPropagation();
    
    if (!confirm('Are you sure you want to delete this notification?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('notification_id', notifId);
        
        const response = await fetch('../notifications/delete_notification.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadNotifications();
        }
    } catch (error) {
        console.error('Error deleting notification:', error);
    }
}

// Auto-refresh notifications every 30 seconds
setInterval(() => {
    loadNotifications();
}, 30000);