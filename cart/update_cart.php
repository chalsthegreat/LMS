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
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$user_id = $_SESSION['user_id'];

// Validate inputs
if ($cart_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Verify cart item belongs to user and get book info
    $verify_sql = "SELECT c.book_id, b.title, b.available_quantity
                   FROM cart c
                   JOIN books b ON c.book_id = b.book_id
                   WHERE c.cart_id = ? AND c.user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $cart_id, $user_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Cart item not found');
    }
    
    $cart_item = $result->fetch_assoc();
    $verify_stmt->close();
    
    // Check if requested quantity is available
    if ($quantity > $cart_item['available_quantity']) {
        throw new Exception('Only ' . $cart_item['available_quantity'] . ' copies available');
    }
    
    // Update cart item
    $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $quantity, $cart_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Updated cart', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Updated quantity to " . $quantity . " for '" . $cart_item['title'] . "' in cart";
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
        'message' => 'Cart updated successfully',
        'cart_count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>