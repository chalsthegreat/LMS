<?php
require_once '../includes/config.php';
require_once '../notifications/functions.php';
require_once '../includes/email_functions.php'; // ADD THIS LINE

header('Content-Type: application/json');

// Check if user is admin or librarian
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$borrowing_id = isset($_POST['borrowing_id']) ? (int)$_POST['borrowing_id'] : 0;

if ($borrowing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrowing ID']);
    exit;
}

// Only get the due_date from the request
$due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;

if (!$due_date) {
    echo json_encode(['success' => false, 'message' => 'Due date is required']);
    exit;
}

// Validate due date format
$date = DateTime::createFromFormat('Y-m-d', $due_date);
if (!$date || $date->format('Y-m-d') !== $due_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid due date format']);
    exit;
}

$conn->begin_transaction();

try {
    // Get borrowing details - MODIFIED TO INCLUDE USER INFO AND EMAIL
    $sql = "SELECT bor.*, b.title, b.available_quantity, u.full_name, u.user_id as borrower_id, u.email
            FROM borrowings bor
            JOIN books b ON bor.book_id = b.book_id
            JOIN users u ON bor.user_id = u.user_id
            WHERE bor.borrowing_id = ? AND bor.status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $borrowing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Borrowing request not found or already processed');
    }
    
    $borrowing = $result->fetch_assoc();
    
    // Validate due_date is after borrow_date
    if (strtotime($due_date) <= strtotime($borrowing['borrow_date'])) {
        throw new Exception('Due date must be after borrow date');
    }
    
    if ($borrowing['available_quantity'] <= 0) {
        throw new Exception('Book is no longer available');
    }
    
    // Update borrowing status and due date only (borrow_date stays as originally set)
    $update_sql = "UPDATE borrowings 
                   SET status = 'borrowed', 
                       due_date = ? 
                   WHERE borrowing_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $due_date, $borrowing_id);
    $update_stmt->execute();
    
    // Update book availability
    $book_sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE book_id = ?";
    $book_stmt = $conn->prepare($book_sql);
    $book_stmt->bind_param("i", $borrowing['book_id']);
    $book_stmt->execute();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Borrow approved', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_desc = "Approved borrow request for: " . $borrowing['title'];
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_desc, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    
    // SEND NOTIFICATION TO MEMBER
    $notif_title = "Borrow Request Approved";
    $notif_message = "Your request to borrow '" . $borrowing['title'] . "' has been approved. Due date: " . date('M d, Y', strtotime($due_date));
    $notif_link = "../books/book_details.php?id=" . $borrowing['book_id'];
    createNotification($conn, $borrowing['borrower_id'], 'borrow_approved', $notif_title, $notif_message, $borrowing_id, $notif_link);
    
    // SEND EMAIL NOTIFICATION - ADD THIS BLOCK
    $email_result = sendBorrowApprovalEmail(
        $borrowing['email'],
        $borrowing['full_name'],
        $borrowing['title'],
        $due_date
    );
    
    // Log email send attempt
    if (!$email_result['success']) {
        error_log("Failed to send approval email to {$borrowing['email']}: {$email_result['message']}");
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Borrow request approved successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>