<?php
require_once '../includes/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'count':
        getCartCount($conn, $user_id);
        break;
    
    case 'items':
        getCartItems($conn, $user_id);
        break;
    
    case 'summary':
        getCartSummary($conn, $user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get cart item count
 */
function getCartCount($conn, $user_id) {
    $sql = "SELECT COALESCE(SUM(quantity), 0) as count FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$row['count']
    ]);
}

/**
 * Get all cart items with book details
 */
function getCartItems($conn, $user_id) {
    $sql = "SELECT c.cart_id, c.book_id, c.quantity, c.added_at,
                   b.title, b.author, b.isbn, b.cover_image, b.available_quantity,
                   b.shelf_location, cat.category_name
            FROM cart c
            JOIN books b ON c.book_id = b.book_id
            LEFT JOIN categories cat ON b.category_id = cat.category_id
            WHERE c.user_id = ?
            ORDER BY c.added_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    $total_items = 0;
    $has_unavailable = false;
    
    while ($row = $result->fetch_assoc()) {
        $is_available = $row['quantity'] <= $row['available_quantity'];
        if (!$is_available) {
            $has_unavailable = true;
        }
        
        $items[] = [
            'cart_id' => (int)$row['cart_id'],
            'book_id' => (int)$row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'cover_image' => $row['cover_image'],
            'category_name' => $row['category_name'],
            'shelf_location' => $row['shelf_location'],
            'quantity' => (int)$row['quantity'],
            'available_quantity' => (int)$row['available_quantity'],
            'is_available' => $is_available,
            'added_at' => $row['added_at']
        ];
        
        $total_items += (int)$row['quantity'];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total_items' => $total_items,
        'has_unavailable' => $has_unavailable
    ]);
}

/**
 * Get cart summary
 */
function getCartSummary($conn, $user_id) {
    // Get total items and unique books
    $sql = "SELECT COUNT(*) as unique_books, COALESCE(SUM(quantity), 0) as total_copies
            FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
    
    // Check for unavailable items
    $check_sql = "SELECT COUNT(*) as unavailable_count
                  FROM cart c
                  JOIN books b ON c.book_id = b.book_id
                  WHERE c.user_id = ? AND c.quantity > b.available_quantity";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $unavailable = $check_result->fetch_assoc();
    $check_stmt->close();
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'unique_books' => (int)$summary['unique_books'],
            'total_copies' => (int)$summary['total_copies'],
            'has_unavailable' => (int)$unavailable['unavailable_count'] > 0
        ]
    ]);
}
?>