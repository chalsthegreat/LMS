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

try {
    // Get borrowing details - MODIFIED TO INCLUDE USER INFO AND EMAIL
    $sql = "SELECT bor.*, b.title, u.full_name, u.user_id as borrower_id, u.email
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
    
    // Update borrowing status to declined with current date and time
    $declined_date = date('Y-m-d H:i:s');
    $update_sql = "UPDATE borrowings SET status = 'declined', declined_date = ? WHERE borrowing_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $declined_date, $borrowing_id);
    $update_stmt->execute();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Borrow rejected', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_desc = "Rejected borrow request for: " . $borrowing['title'];
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_desc, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    
    // SEND NOTIFICATION TO MEMBER
    $notif_title = "Borrow Request Declined";
    $notif_message = "Your request to borrow '" . $borrowing['title'] . "' has been declined. Please contact the library for more information.";
    $notif_link = "../books/book_details.php?id=" . $borrowing['book_id'];
    createNotification($conn, $borrowing['borrower_id'], 'borrow_declined', $notif_title, $notif_message, $borrowing_id, $notif_link);
    
    // SEND EMAIL NOTIFICATION - ADD THIS BLOCK
    $email_result = sendBorrowRejectionEmail(
        $borrowing['email'],
        $borrowing['full_name'],
        $borrowing['title']
    );
    
    // Log email send attempt
    if (!$email_result['success']) {
        error_log("Failed to send rejection email to {$borrowing['email']}: {$email_result['message']}");
    }
    
    echo json_encode(['success' => true, 'message' => 'Borrow request declined']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>