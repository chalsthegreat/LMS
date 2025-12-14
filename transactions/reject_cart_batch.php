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

$batch_id = isset($_POST['batch_id']) ? trim($_POST['batch_id']) : '';

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit;
}

$conn->begin_transaction();

try {
    // Get member ID, email, and name before rejecting - MODIFIED
    $member_sql = "SELECT DISTINCT u.user_id, u.email, u.full_name 
                   FROM borrowings bor
                   JOIN users u ON bor.user_id = u.user_id
                   WHERE bor.cart_batch_id = ? AND bor.status = 'pending' 
                   LIMIT 1";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("s", $batch_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    $member_data = $member_result->fetch_assoc();
    $member_id = $member_data['user_id'];
    $member_email = $member_data['email'];
    $member_name = $member_data['full_name'];
    $member_stmt->close();
    
    // Count rejected items
    $count_sql = "SELECT COUNT(*) as count FROM borrowings WHERE cart_batch_id = ? AND status = 'pending'";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("s", $batch_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $rejected_count = $count_result->fetch_assoc()['count'];
    $count_stmt->close();
    
    // Reject all pending borrowings in this batch
    $sql = "UPDATE borrowings SET status = 'declined', declined_date = NOW() 
            WHERE cart_batch_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $batch_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    // Log activity
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, 'Batch rejected', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_desc = "Rejected entire cart batch $batch_id ($affected items)";
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $log_desc, $_SERVER['REMOTE_ADDR']);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Send notification to member
    if ($member_id) {
        $notif_title = "Cart Order Rejected";
        $notif_message = "Your cart order has been rejected. Please contact the library for more information.";
        $notif_link = "../books/my_borrowings.php";
        createNotification($conn, $member_id, 'borrow_declined', $notif_title, $notif_message, null, $notif_link);
        
        // SEND EMAIL NOTIFICATION - ADD THIS BLOCK
        if ($member_email && $member_name) {
            $email_result = sendBatchRejectionEmail(
                $member_email,
                $member_name,
                $rejected_count
            );
            
            // Log email send attempt
            if (!$email_result['success']) {
                error_log("Failed to send batch rejection email to {$member_email}: {$email_result['message']}");
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
        'message' => "Entire batch rejected ($affected items)"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>