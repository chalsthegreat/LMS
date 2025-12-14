<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is admin or librarian
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$borrowing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($borrowing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid borrowing ID']);
    exit;
}

try {
    $sql = "SELECT borrow_date, due_date FROM borrowings WHERE borrowing_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $borrowing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Borrowing record not found');
    }
    
    $borrowing = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'borrow_date' => $borrowing['borrow_date'],
        'due_date' => $borrowing['due_date']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>