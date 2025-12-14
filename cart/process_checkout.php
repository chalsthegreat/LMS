<?php
require_once '../includes/config.php';
require_once '../notifications/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get all cart items
    $cart_sql = "SELECT c.cart_id, c.book_id, c.quantity, 
                        b.title, b.available_quantity
                 FROM cart c
                 JOIN books b ON c.book_id = b.book_id
                 WHERE c.user_id = ?";
    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        throw new Exception('Cart is empty');
    }
    
    $cart_items = [];
    $total_books = 0;
    
    while ($row = $cart_result->fetch_assoc()) {
        // Check availability
        if ($row['quantity'] > $row['available_quantity']) {
            throw new Exception('Book "' . $row['title'] . '" - Only ' . $row['available_quantity'] . ' copies available');
        }
        
        // Check if user already has this book borrowed/pending
        $check_sql = "SELECT COUNT(*) as count FROM borrowings 
                      WHERE user_id = ? AND book_id = ? AND status IN ('borrowed', 'pending')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $row['book_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $exists = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($exists > 0) {
            throw new Exception('You already have "' . $row['title'] . '" borrowed or pending');
        }
        
        $cart_items[] = $row;
        $total_books += $row['quantity'];
    }
    $cart_stmt->close();
    
    // Check total borrowing limit (max 5 books)
    $current_borrowed_sql = "SELECT COUNT(*) as count FROM borrowings 
                             WHERE user_id = ? AND status = 'borrowed'";
    $current_stmt = $conn->prepare($current_borrowed_sql);
    $current_stmt->bind_param("i", $user_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_borrowed = $current_result->fetch_assoc()['count'];
    $current_stmt->close();
    
    if ($current_borrowed + $total_books > 5) {
        $remaining = 5 - $current_borrowed;
        throw new Exception('Borrowing limit exceeded. You can only borrow ' . $remaining . ' more book(s)');
    }
    
    // Check for unpaid fines
    $fine_sql = "SELECT COUNT(*) as unpaid_fines FROM fines 
                 WHERE user_id = ? AND status = 'unpaid'";
    $fine_stmt = $conn->prepare($fine_sql);
    $fine_stmt->bind_param("i", $user_id);
    $fine_stmt->execute();
    $fine_result = $fine_stmt->get_result();
    $unpaid_fines = $fine_result->fetch_assoc()['unpaid_fines'];
    $fine_stmt->close();
    
    if ($unpaid_fines > 0) {
        throw new Exception('You have unpaid fines. Please clear them before borrowing.');
    }
    
    // Generate unique batch ID for this cart
    $batch_id = 'CART_' . $user_id . '_' . time();
    $borrow_date = date('Y-m-d');
    
    // Insert borrowing records for each cart item
    $insert_sql = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status, cart_batch_id) 
                   VALUES (?, ?, ?, NULL, 'pending', ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    $book_titles = [];
    foreach ($cart_items as $item) {
        // Insert multiple records for quantity > 1
        for ($i = 0; $i < $item['quantity']; $i++) {
            $insert_stmt->bind_param("iiss", $user_id, $item['book_id'], $borrow_date, $batch_id);
            if (!$insert_stmt->execute()) {
                throw new Exception('Failed to create borrowing records');
            }
        }
        $book_titles[] = $item['title'] . ' (x' . $item['quantity'] . ')';
    }
    $insert_stmt->close();
    
    // Clear the cart
    $clear_sql = "DELETE FROM cart WHERE user_id = ?";
    $clear_stmt = $conn->prepare($clear_sql);
    $clear_stmt->bind_param("i", $user_id);
    $clear_stmt->execute();
    $clear_stmt->close();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Cart checkout', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Submitted borrow request for " . count($cart_items) . " book(s): " . implode(', ', $book_titles);
    $log_stmt->bind_param("iss", $user_id, $log_description, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Get user's full name for notification
    $user_sql = "SELECT full_name FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_name = $user_result->fetch_assoc()['full_name'];
    $user_stmt->close();
    
    // Create notification for admins and librarians
    $notif_title = "New Cart Borrow Request";
    $notif_message = $user_name . " submitted a cart borrow request for " . count($cart_items) . " book(s) (" . $total_books . " total copies)";
    $notif_link = "../transactions/cart_batch_details.php?batch_id=" . urlencode($batch_id);
    notifyAdminsAndLibrarians($conn, 'borrow_request', $notif_title, $notif_message, null, $notif_link);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Borrow request submitted successfully',
        'batch_id' => $batch_id,
        'total_books' => count($cart_items),
        'total_copies' => $total_books
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>