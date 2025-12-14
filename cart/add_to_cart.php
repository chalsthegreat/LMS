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
$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$user_id = $_SESSION['user_id'];

// Validate inputs
if ($book_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if book exists and get available quantity
    $book_sql = "SELECT title, available_quantity FROM books WHERE book_id = ?";
    $book_stmt = $conn->prepare($book_sql);
    $book_stmt->bind_param("i", $book_id);
    $book_stmt->execute();
    $book_result = $book_stmt->get_result();
    
    if ($book_result->num_rows === 0) {
        throw new Exception('Book not found');
    }
    
    $book = $book_result->fetch_assoc();
    $book_stmt->close();
    
    // Check if requested quantity is available
    if ($quantity > $book['available_quantity']) {
        throw new Exception('Requested quantity not available. Only ' . $book['available_quantity'] . ' copies available.');
    }
    
    // Check if user already has this book borrowed or in pending status
    $check_borrowed = "SELECT COUNT(*) as count FROM borrowings 
                       WHERE user_id = ? AND book_id = ? AND status IN ('borrowed', 'pending')";
    $check_stmt = $conn->prepare($check_borrowed);
    $check_stmt->bind_param("ii", $user_id, $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $borrowed = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($borrowed['count'] > 0) {
        throw new Exception('You already have this book borrowed or a pending request for it.');
    }
    
    // Check if book is already in cart
    $cart_check = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND book_id = ?";
    $cart_stmt = $conn->prepare($cart_check);
    $cart_stmt->bind_param("ii", $user_id, $book_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check if new quantity exceeds available
        if ($new_quantity > $book['available_quantity']) {
            throw new Exception('Cannot add more. Total would exceed available quantity.');
        }
        
        $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        $message = $quantity . ' more cop' . ($quantity > 1 ? 'ies' : 'y') . ' of "' . $book['title'] . '" added to cart';
    } else {
        // Insert new cart item
        $insert_sql = "INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iii", $user_id, $book_id, $quantity);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        $message = $quantity . ' cop' . ($quantity > 1 ? 'ies' : 'y') . ' of "' . $book['title'] . '" added to cart';
    }
    
    $cart_stmt->close();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Added to cart', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Added " . $quantity . " cop" . ($quantity > 1 ? "ies" : "y") . " of '" . $book['title'] . "' to cart";
    $log_stmt->bind_param("iss", $user_id, $log_description, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
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
        'message' => $message,
        'cart_count' => (int)$count
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>