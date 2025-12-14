<?php
require_once '../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get borrowing ID
$borrowing_id = isset($_POST['borrowing_id']) ? (int)$_POST['borrowing_id'] : 0;

if ($borrowing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrowing ID']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get borrowing details
    $sql = "SELECT bor.*, b.title, b.book_id, b.available_quantity
            FROM borrowings bor
            JOIN books b ON bor.book_id = b.book_id
            WHERE bor.borrowing_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $borrowing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Borrowing record not found');
    }
    
    $borrowing = $result->fetch_assoc();
    $stmt->close();
    
    // Check if book is already returned
    if ($borrowing['status'] === 'returned') {
        throw new Exception('This book has already been returned');
    }
    
    // Check if book can be returned (must be borrowed or overdue)
    if ($borrowing['status'] !== 'borrowed' && $borrowing['status'] !== 'overdue') {
        throw new Exception('This book cannot be returned. Current status: ' . $borrowing['status']);
    }
    
    // Calculate fine if overdue
    $return_date = date('Y-m-d');
    $due_date = new DateTime($borrowing['due_date']);
    $return_datetime = new DateTime($return_date);
    $fine_amount = 0;
    
    if ($return_datetime > $due_date) {
        // Calculate days overdue
        $days_overdue = $due_date->diff($return_datetime)->days;
        // Fine rate: 10 pesos per day (you can adjust this)
        $fine_per_day = 10;
        $fine_amount = $days_overdue * $fine_per_day;
    }
    
    // Update borrowing record
    $sql = "UPDATE borrowings 
            SET status = 'returned',
                return_date = ?,
                fine_amount = ?,
                updated_at = NOW()
            WHERE borrowing_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $return_date, $fine_amount, $borrowing_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update borrowing record');
    }
    $stmt->close();
    
    // Increase available quantity of the book
    $sql = "UPDATE books 
            SET available_quantity = available_quantity + 1,
                updated_at = NOW()
            WHERE book_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $borrowing['book_id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update book availability');
    }
    $stmt->close();
    
    // If there's a fine, create a fine record
    if ($fine_amount > 0) {
        $sql = "INSERT INTO fines (borrowing_id, user_id, amount, reason, status, created_at)
                VALUES (?, ?, ?, 'Late return fee', 'unpaid', NOW())";
        
        $stmt = $conn->prepare($sql);
        $reason = "Late return fee - " . $days_overdue . " day(s) overdue";
        $stmt->bind_param("iid", $borrowing_id, $borrowing['user_id'], $fine_amount);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create fine record');
        }
        $stmt->close();
    }
    
    // Log the activity
    $description = "Book returned: " . $borrowing['title'] . 
                   ($fine_amount > 0 ? " (Fine: ₱" . number_format($fine_amount, 2) . ")" : "");

    
    // Commit transaction
    $conn->commit();
    
    $message = $fine_amount > 0 
        ? "Book marked as returned. Fine amount: ₱" . number_format($fine_amount, 2) 
        : "Book marked as returned successfully";
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'fine_amount' => $fine_amount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>