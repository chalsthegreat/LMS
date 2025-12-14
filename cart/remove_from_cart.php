<?php
require_once '../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
$user_id = $_SESSION['user_id'];

// Validate input
if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
    exit;
}

try {
    // Get book title for logging before deleting
    $get_sql = "SELECT b.title 
                FROM cart c
                JOIN books b ON c.book_id = b.book_id
                WHERE c.cart_id = ? AND c.user_id = ?";
    $get_stmt = $conn->prepare($get_sql);
    $get_stmt->bind_param("ii", $cart_id, $user_id);
    $get_stmt->execute();
    $result = $get_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Cart item not found');
    }
    
    $book = $result->fetch_assoc();
    $get_stmt->close();
    
    // Delete cart item
    $delete_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $cart_id, $user_id);
    $delete_stmt->execute();
    
    if ($delete_stmt->affected_rows === 0) {
        throw new Exception('Failed to remove item from cart');
    }
    
    $delete_stmt->close();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Removed from cart', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Removed '" . $book['title'] . "' from cart";
    $log_stmt->bind_param("iss", $user_id, $log_description, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Get updated cart count
    $count_sql = "SELECT COALESCE(SUM(quantity), 0) as count FROM cart WHERE user_id = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count = $count_result->fetch_assoc()['count'];
    $count_stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart',
        'cart_count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>