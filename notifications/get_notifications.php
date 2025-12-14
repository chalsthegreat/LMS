<?php
require_once '../includes/config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$unread_only = isset($_GET['unread_only']) ? (bool)$_GET['unread_only'] : false;

try {
    // Get notifications
    $result = getNotifications($conn, $user_id, $limit, $unread_only);
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['notification_id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'link' => $row['link'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'time_ago' => timeAgo($row['created_at']),
            'icon' => getNotificationIcon($row['type']),
            'color' => getNotificationColor($row['type'])
        ];
    }
    
    // Get unread count
    $unread_count = getUnreadCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>