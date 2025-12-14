<?php
session_start();
require_once '../includes/config.php';

// Get user role from session
$role = $_SESSION['role'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is logged in and has permission
if (!isLoggedIn() || ($role !== 'admin' && $role !== 'librarian')) {
    setMessage('error', 'You do not have permission to perform this action');
    redirect('books.php');
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('books.php');
}

// Get book ID
if (!isset($_POST['book_id']) || empty($_POST['book_id'])) {
    setMessage('error', 'Invalid book ID');
    redirect('books.php');
}

$book_id = (int)$_POST['book_id'];

// Get book details before deleting
$sql = "SELECT title, author, cover_image FROM books WHERE book_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Book not found');
    redirect('books.php');
}

$book = $result->fetch_assoc();
$stmt->close();

// Check if book has active borrowings
$check_borrowings = "SELECT COUNT(*) as active_count FROM borrowings 
                     WHERE book_id = ? AND status = 'borrowed'";
$stmt = $conn->prepare($check_borrowings);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();
$borrowing_data = $result->fetch_assoc();
$stmt->close();

if ($borrowing_data['active_count'] > 0) {
    setMessage('error', 'Cannot delete book. There are active borrowings for this book. Please return all copies first.');
    redirect('edit_book.php?id=' . $book_id);
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete related records (due to CASCADE, these will be deleted automatically)
    // But we'll keep it explicit for clarity
    
    // Delete reviews
    $delete_reviews = "DELETE FROM reviews WHERE book_id = ?";
    $stmt = $conn->prepare($delete_reviews);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete reservations
    $delete_reservations = "DELETE FROM reservations WHERE book_id = ?";
    $stmt = $conn->prepare($delete_reservations);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete borrowings (and related fines will be deleted automatically via CASCADE)
    $delete_borrowings = "DELETE FROM borrowings WHERE book_id = ?";
    $stmt = $conn->prepare($delete_borrowings);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the book
    $delete_book = "DELETE FROM books WHERE book_id = ?";
    $stmt = $conn->prepare($delete_book);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Delete cover image file if exists
    if (!empty($book['cover_image'])) {
        $upload_dir = '../uploads/books/';
        $cover_path = $upload_dir . $book['cover_image'];
        if (file_exists($cover_path)) {
            unlink($cover_path);
        }
    }
    
    // Log the activity
    $action = "Book deleted";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Deleted book: {$book['title']} by {$book['author']}";
    $log_stmt->bind_param("isss", $user_id, $action, $log_description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'Book deleted successfully!');
    redirect('books.php');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    setMessage('error', 'Failed to delete book. Please try again.');
    redirect('edit_book.php?id=' . $book_id);
}

$conn->close();
?>