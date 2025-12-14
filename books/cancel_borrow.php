<?php
require_once '../includes/config.php';

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.'
    ]);
    exit();
}

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$cancel_quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 0;

if ($book_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit();
}

// Get the cart_batch_id and count total pending for this book
$check_sql = "SELECT cart_batch_id, COUNT(*) as total_count, GROUP_CONCAT(borrowing_id) as borrowing_ids
              FROM borrowings 
              WHERE user_id = ? AND book_id = ? AND status = 'pending'
              GROUP BY cart_batch_id";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $_SESSION['user_id'], $book_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'No pending requests found for this book'
    ]);
    $check_stmt->close();
    exit();
}

$cart_info = $result->fetch_assoc();
$check_stmt->close();

$total_count = (int)$cart_info['total_count'];
$cart_batch_id = $cart_info['cart_batch_id'];
$borrowing_ids = explode(',', $cart_info['borrowing_ids']);

// Validate cancel quantity
if ($cancel_quantity > 0 && $cancel_quantity > $total_count) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Cannot cancel more copies than you have pending'
    ]);
    exit();
}

// Determine if partial or full cancellation
$is_partial = ($cancel_quantity > 0 && $cancel_quantity < $total_count);

if ($is_partial) {
    // Partial cancellation - delete only specified quantity
    $delete_sql = "DELETE FROM borrowings 
                   WHERE user_id = ? AND book_id = ? AND status = 'pending'
                   ORDER BY borrowing_id LIMIT ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("iii", $_SESSION['user_id'], $book_id, $cancel_quantity);
    
    $message = "Cancelled $cancel_quantity cop" . ($cancel_quantity > 1 ? 'ies' : 'y') . " of your pending request";
    $log_description = "User cancelled $cancel_quantity cop" . ($cancel_quantity > 1 ? 'ies' : 'y') . " of borrow request for book ID: " . $book_id;
} else {
    // Cancel all (either quantity is 0, equals total, or not specified)
    $delete_sql = "DELETE FROM borrowings 
                   WHERE user_id = ? AND book_id = ? AND status = 'pending'";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $_SESSION['user_id'], $book_id);
    
    $message = "Cancelled all " . ($total_count > 1 ? "$total_count copies" : "copy") . " of your pending request";
    $log_description = "User cancelled all " . ($total_count > 1 ? "$total_count copies" : "copy") . " of borrow request for book ID: " . $book_id;
}

$success = $delete_stmt->execute();
$affected_rows = $delete_stmt->affected_rows;
$delete_stmt->close();

if (!$success || $affected_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to cancel the request'
    ]);
    exit();
}

// Delete related notifications if all copies were cancelled
if (!$is_partial) {
    if ($cart_batch_id) {
        // For cart orders, delete notifications with the cart_batch_id in the link
        $notif_delete_sql = "DELETE FROM notifications 
                            WHERE type = 'borrow_request' 
                            AND link LIKE ?";
        $notif_delete_stmt = $conn->prepare($notif_delete_sql);
        $link_pattern = "%batch_id=" . $cart_batch_id . "%";
        $notif_delete_stmt->bind_param("s", $link_pattern);
        $notif_delete_stmt->execute();
        $notif_delete_stmt->close();
    } else {
        // For individual orders, delete notifications by borrowing_id
        foreach ($borrowing_ids as $borrowing_id) {
            $notif_delete_sql = "DELETE FROM notifications 
                                WHERE type = 'borrow_request' 
                                AND related_id = ?";
            $notif_delete_stmt = $conn->prepare($notif_delete_sql);
            $notif_delete_stmt->bind_param("i", $borrowing_id);
            $notif_delete_stmt->execute();
            $notif_delete_stmt->close();
        }
    }
}

// Log the activity
$log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
            VALUES (?, 'Cancel borrow request', ?, ?)";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("iss", $_SESSION['user_id'], $log_description, $_SERVER['REMOTE_ADDR']);
$log_stmt->execute();
$log_stmt->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => $message,
    'cancelled_count' => $affected_rows,
    'remaining_count' => $total_count - $affected_rows
]);
exit();
?>