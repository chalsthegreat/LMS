<?php
$page_title = "All Notifications";
require_once '../includes/config.php';
require_once '../includes/header.php';
require_once 'functions.php';

// Get all notifications
$notifications = getNotifications($conn, $_SESSION['user_id'], 50);
?>

<style>
    html, body {
        height: 100%;
        margin: 0;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* This ensures your main content expands to fill the space before the footer */
    main {
        flex: 1;
    }
    
    /* Custom spinner for loading state (from users.php) */
    #submitLoader {
        border-right-color: white;
    }
</style>

<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Notifications</h1>
            <p class="text-gray-600 mt-1">Stay updated with your library activities</p>
        </div>
        <div class="flex space-x-2">
            <button onclick="markAllAsRead()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-check-double mr-2"></i>Mark All as Read
            </button>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($notifications->num_rows === 0): ?>
            <div class="p-12 text-center text-gray-500">
                <i class="fas fa-bell-slash text-6xl mb-4"></i>
                <p class="text-xl font-medium">No notifications yet</p>
                <p class="mt-2">You'll see notifications here when there's activity</p>
            </div>
        <?php else: ?>
            <?php while ($notif = $notifications->fetch_assoc()): ?>
                <?php
                $bgColor = $notif['is_read'] ? 'bg-white' : 'bg-blue-50';
                $icon = getNotificationIcon($notif['type']);
                $color = getNotificationColor($notif['type']);
                $iconColorClass = 'bg-' . $color . '-500';
                ?>
                <div class="<?php echo $bgColor; ?> border-b hover:bg-gray-50 transition p-6" 
                     data-notif-id="<?php echo $notif['notification_id']; ?>">
                    <div class="flex items-start space-x-4">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 rounded-full <?php echo $iconColorClass; ?> flex items-center justify-center">
                                <i class="fas <?php echo $icon; ?> text-white text-lg"></i>
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($notif['title']); ?>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                New
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <i class="far fa-clock mr-1"></i>
                                        <?php echo timeAgo($notif['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center space-x-4 mt-4">
                                <?php if ($notif['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" 
                                       onclick="markAsReadAndNavigate(event, <?php echo $notif['notification_id']; ?>, '<?php echo htmlspecialchars($notif['link']); ?>')"
                                       class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                        <i class="fas fa-external-link-alt mr-1"></i>View Details
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!$notif['is_read']): ?>
                                    <button onclick="markSingleAsRead(event, <?php echo $notif['notification_id']; ?>)" 
                                            class="text-sm text-gray-600 hover:text-gray-800">
                                        <i class="fas fa-check mr-1"></i>Mark as Read
                                    </button>
                                <?php endif; ?>
                                
                                <button onclick="deleteNotification(event, <?php echo $notif['notification_id']; ?>)" 
                                        class="text-sm text-red-600 hover:text-red-700">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<script src="notifications.js"></script>
<script>
// Reload notifications after actions
function markSingleAsRead(event, notifId) {
    event.preventDefault();
    event.stopPropagation();
    
    fetch('mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    if (!confirm('Mark all notifications as read?')) return;
    
    fetch('mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_all=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function deleteNotification(event, notifId) {
    event.preventDefault();
    event.stopPropagation();
    
    if (!confirm('Are you sure you want to delete this notification?')) return;
    
    fetch('delete_notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAsReadAndNavigate(event, notifId, link) {
    event.preventDefault();
    
    fetch('mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notifId
    })
    .then(() => {
        if (link) {
            window.location.href = link;
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php require_once '../includes/footer.php'; ?>