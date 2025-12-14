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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$batch_id = isset($input['batch_id']) ? trim($input['batch_id']) : '';
$due_date = isset($input['due_date']) ? $input['due_date'] : '';
$quantities = isset($input['quantities']) ? $input['quantities'] : [];

if (empty($batch_id) || empty($due_date) || empty($quantities)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$conn->begin_transaction();

try {
    $approved_count = 0;
    $rejected_count = 0;
    $member_id = null;
    $member_email = null;
    $member_name = null;
    
    foreach ($quantities as $book_id => $approve_qty) {
        $book_id = (int)$book_id;
        $approve_qty = (int)$approve_qty;
        
        // Get all pending borrowings for this book in this batch - INCLUDE EMAIL
        $sql = "SELECT bor.borrowing_id, bor.user_id, bor.book_id, u.email, u.full_name 
                FROM borrowings bor
                JOIN users u ON bor.user_id = u.user_id
                WHERE bor.cart_batch_id = ? AND bor.book_id = ? AND bor.status = 'pending'
                ORDER BY bor.borrowing_id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $batch_id, $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $borrowing_ids = [];
        while ($row = $result->fetch_assoc()) {
            $borrowing_ids[] = $row['borrowing_id'];
            if (!$member_id) {
                $member_id = $row['user_id'];
                $member_email = $row['email'];
                $member_name = $row['full_name'];
            }
        }
        $stmt->close();
        
        $total_requested = count($borrowing_ids);
        
        // Approve the specified quantity
        for ($i = 0; $i < min($approve_qty, $total_requested); $i++) {
            $borrowing_id = $borrowing_ids[$i];
            
            // Update to borrowed status
            $update_sql = "UPDATE borrowings SET status = 'borrowed', due_date = ? WHERE borrowing_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $due_date, $borrowing_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Decrease available quantity
            $book_sql = "UPDATE books SET available_quantity = available_quantity - 1 WHERE book_id = ?";
            $book_stmt = $conn->prepare($book_sql);
            $book_stmt->bind_param("i", $book_id);
            $book_stmt->execute();
            $book_stmt->close();
            
            $approved_count++;
        }
        
        // Reject the remaining
        for ($i = $approve_qty; $i < $total_requested; $i++) {
            $borrowing_id = $borrowing_ids[$i];
            
            $reject_sql = "UPDATE borrowings SET status = 'declined', declined_date = NOW() WHERE borrowing_id = ?";
            $reject_stmt = $conn->prepare($reject_sql);
            $reject_stmt->bind_param("i", $borrowing_id);
            $reject_stmt->execute();
            $reject_stmt->close();
            
            $rejected_count++;
        }
    }
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Batch approved', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_desc = "Approved cart batch $batch_id: $approved_count approved, $rejected_count rejected";
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_desc, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Send notification to member
    if ($member_id) {
        $notif_title = "Cart Order Processed";
        $notif_message = "Your cart order has been processed. $approved_count book(s) approved" . 
                        ($rejected_count > 0 ? ", $rejected_count rejected due to availability" : "");
        $notif_link = "../books/my_borrowings.php";
        createNotification($conn, $member_id, 'borrow_approved', $notif_title, $notif_message, null, $notif_link);
        
        // SEND EMAIL NOTIFICATION - ADD THIS BLOCK
        if ($member_email && $member_name) {
            $email_result = sendBatchApprovalEmail(
                $member_email,
                $member_name,
                $approved_count,
                $rejected_count,
                $due_date
            );
            
            // Log email send attempt
            if (!$email_result['success']) {
                error_log("Failed to send batch approval email to {$member_email}: {$email_result['message']}");
            }
        }
    }
    
    // Delete the original batch notification
    $delete_notif_sql = "DELETE FROM notifications WHERE link LIKE ?";
    $delete_notif_stmt = $conn->prepare($delete_notif_sql);
    $link_pattern = "%batch_id=" . $batch_id . "%";
    $delete_notif_stmt->bind_param("s", $link_pattern);
    $delete_notif_stmt->execute();
    $delete_notif_stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Batch processed: $approved_count approved, $rejected_count rejected",
        'approved' => $approved_count,
        'rejected' => $rejected_count
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>