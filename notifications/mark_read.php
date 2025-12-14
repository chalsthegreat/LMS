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
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$mark_all = isset($_POST['mark_all']) ? (bool)$_POST['mark_all'] : false;

try {
    if ($mark_all) {
        // Mark all as read
        if (markAllAsRead($conn, $user_id)) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            throw new Exception('Failed to mark all as read');
        }
    } else {
        // Mark single notification as read
        if ($notification_id <= 0) {
            throw new Exception('Invalid notification ID');
        }
        
        if (markAsRead($conn, $notification_id, $user_id)) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            throw new Exception('Failed to mark as read');
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>