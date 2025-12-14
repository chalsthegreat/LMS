<?php
/**
 * Notification Helper Functions
 * Place this file in: notifications/functions.php
 */

/**
 * Create a new notification
 */
function createNotification($conn, $user_id, $type, $title, $message, $related_id = null, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, related_id, link) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $user_id, $type, $title, $message, $related_id, $link);
    return $stmt->execute();
}

/**
 * Notify all admins and librarians
 */
function notifyAdminsAndLibrarians($conn, $type, $title, $message, $related_id = null, $link = null) {
    $sql = "SELECT user_id FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'";
    $result = $conn->query($sql);
    
    $success = true;
    while ($row = $result->fetch_assoc()) {
        if (!createNotification($conn, $row['user_id'], $type, $title, $message, $related_id, $link)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Get unread notification count for a user
 */
function getUnreadCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

/**
 * Get recent notifications for a user
 */
function getNotifications($conn, $user_id, $limit = 10, $unread_only = false) {
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Mark notification as read
 */
function markAsRead($conn, $notification_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 */
function markAllAsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Delete a notification
 */
function deleteNotification($conn, $notification_id, $user_id) {
    $sql = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Get time ago format
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    $icons = [
        'borrow_request' => 'fa-book',
        'borrow_approved' => 'fa-check-circle',
        'borrow_declined' => 'fa-times-circle',
        'book_overdue' => 'fa-exclamation-triangle',
        'fine_added' => 'fa-money-bill',
        'reservation_ready' => 'fa-bookmark'
    ];
    return $icons[$type] ?? 'fa-bell';
}

/**
 * Get notification color based on type
 */
function getNotificationColor($type) {
    $colors = [
        'borrow_request' => 'blue',
        'borrow_approved' => 'green',
        'borrow_declined' => 'red',
        'book_overdue' => 'orange',
        'fine_added' => 'yellow',
        'reservation_ready' => 'purple'
    ];
    return $colors[$type] ?? 'gray';
}
?>