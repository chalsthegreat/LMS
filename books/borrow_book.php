<?php
require_once '../includes/config.php';
require_once '../notifications/functions.php'; // ADD THIS LINE

// Check if user is logged in and is a member
if (!isLoggedIn() || $_SESSION['role'] !== 'member') {
    setMessage('error', 'Access denied. Only members can borrow books.');
    redirect('books.php');
}

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    setMessage('error', 'Invalid book ID');
    redirect('books.php');
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if book exists and is available
    $sql = "SELECT book_id, title, available_quantity FROM books WHERE book_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Book not found');
    }
    
    $book = $result->fetch_assoc();
    
    if ($book['available_quantity'] <= 0) {
        throw new Exception('This book is currently not available. You may reserve it instead.');
    }
    
    // Check if user already has this book borrowed
    $check_sql = "SELECT borrowing_id FROM borrowings 
                  WHERE user_id = ? AND book_id = ? AND status IN ('borrowed', 'pending') LIMIT 1";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $_SESSION['user_id'], $book_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('You have already borrowed or requested this book');
    }
    $check_stmt->close();
    
    // Check if user has reached maximum borrowing limit (e.g., 5 books)
    $limit_sql = "SELECT COUNT(*) as borrowed_count FROM borrowings 
                  WHERE user_id = ? AND status = 'borrowed'";
    $limit_stmt = $conn->prepare($limit_sql);
    $limit_stmt->bind_param("i", $_SESSION['user_id']);
    $limit_stmt->execute();
    $limit_result = $limit_stmt->get_result()->fetch_assoc();
    
    if ($limit_result['borrowed_count'] >= 5) {
        throw new Exception('You have reached the maximum borrowing limit of 5 books');
    }
    $limit_stmt->close();
    
    // Check if user has any unpaid fines
    $fine_sql = "SELECT COUNT(*) as unpaid_fines FROM fines 
                 WHERE user_id = ? AND status = 'unpaid'";
    $fine_stmt = $conn->prepare($fine_sql);
    $fine_stmt->bind_param("i", $_SESSION['user_id']);
    $fine_stmt->execute();
    $fine_result = $fine_stmt->get_result()->fetch_assoc();
    
    if ($fine_result['unpaid_fines'] > 0) {
        throw new Exception('You have unpaid fines. Please clear them before borrowing new books.');
    }
    $fine_stmt->close();
    
    // Set borrow date to today - due_date will be set by librarian/admin upon approval
    $borrow_date = date('Y-m-d');
    
    // Insert borrowing record with NULL due_date (will be set on approval)
    $insert_sql = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) 
                   VALUES (?, ?, ?, NULL, 'pending')";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iis", $_SESSION['user_id'], $book_id, $borrow_date);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to create borrowing record');
    }
    $borrowing_id = $conn->insert_id; // Get the new borrowing ID
    $insert_stmt->close();
    
    // Log the activity
    $log_description = "User requested to borrow book: " . $book['title'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Borrow request', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_description, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // CREATE NOTIFICATION FOR ADMINS AND LIBRARIANS - ADD THIS BLOCK
    $notif_title = "New Borrow Request";
    $notif_message = $_SESSION['full_name'] . " requested to borrow '" . $book['title'] . "'";
    $notif_link = "../transactions/transaction_details.php?id=" . $borrowing_id;
    notifyAdminsAndLibrarians($conn, 'borrow_request', $notif_title, $notif_message, $borrowing_id, $notif_link);
    
    // Commit transaction
    $conn->commit();
    
    setMessage('success', 'Borrow request submitted successfully! Please wait for approval.');
    redirect('my_borrowings.php');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    setMessage('error', $e->getMessage());
    redirect('book_details.php?id=' . $book_id);
}
?>