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

// Get current book details
$sql = "SELECT * FROM books WHERE book_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Book not found');
    redirect('books.php');
}

$current_book = $result->fetch_assoc();
$stmt->close();

// Get and sanitize input
$title = sanitize($_POST['title']);
$author = sanitize($_POST['author']);
$isbn = sanitize($_POST['isbn'] ?? '');
$publisher = sanitize($_POST['publisher'] ?? '');
$publish_year = !empty($_POST['publish_year']) ? (int)$_POST['publish_year'] : null;
$category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$quantity = (int)$_POST['quantity'];
$available_quantity = (int)$_POST['available_quantity'];
$shelf_location = sanitize($_POST['shelf_location'] ?? '');
$description = sanitize($_POST['description'] ?? '');

// Validate required fields
if (empty($title) || empty($author)) {
    setMessage('error', 'Title and Author are required fields');
    redirect('edit_book.php?id=' . $book_id);
}

// Validate quantities
if ($quantity < 1) {
    setMessage('error', 'Quantity must be at least 1');
    redirect('edit_book.php?id=' . $book_id);
}

if ($available_quantity < 0 || $available_quantity > $quantity) {
    setMessage('error', 'Available quantity must be between 0 and total quantity');
    redirect('edit_book.php?id=' . $book_id);
}

// Check if ISBN already exists (excluding current book)
if (!empty($isbn)) {
    $check_isbn = "SELECT book_id FROM books WHERE isbn = ? AND book_id != ? LIMIT 1";
    $stmt = $conn->prepare($check_isbn);
    $stmt->bind_param("si", $isbn, $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        setMessage('error', 'A book with this ISBN already exists');
        redirect('edit_book.php?id=' . $book_id);
    }
    $stmt->close();
}

// Handle cover image
$cover_image = $current_book['cover_image'];
$upload_dir = '../Uploads/books/';

// Check if user wants to remove cover
if (isset($_POST['remove_cover']) && $_POST['remove_cover'] == '1') {
    // Delete old cover file
    if (!empty($cover_image) && file_exists($upload_dir . $cover_image)) {
        unlink($upload_dir . $cover_image);
    }
    $cover_image = null;
}

// Handle new cover image upload
if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['cover_image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_type = mime_content_type($file['tmp_name']) ?: $file['type'];
    
    if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
        setMessage('error', 'Invalid cover image file type. Only JPG, PNG, and GIF are allowed');
        redirect('edit_book.php?id=' . $book_id);
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5242880) {
        setMessage('error', 'Cover image file size exceeds 5MB limit');
        redirect('edit_book.php?id=' . $book_id);
    }
    
    // Check for partial upload
    if ($file['size'] === 0 || !is_uploaded_file($file['tmp_name'])) {
        setMessage('error', 'File upload failed. The file may be corrupted or too large.');
        redirect('edit_book.php?id=' . $book_id);
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Delete old cover file
    if (!empty($current_book['cover_image']) && file_exists($upload_dir . $current_book['cover_image'])) {
        unlink($upload_dir . $current_book['cover_image']);
    }
    
    // Generate unique filename
    $cover_image = 'book_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $cover_image;
    
    // Move uploaded file with proper permissions
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        chmod($upload_path, 0644); // Set file permissions
    } else {
        $cover_image = $current_book['cover_image']; // Keep old image
        setMessage('error', 'Failed to upload cover image. Please check server permissions or try again.');
        // Log error for debugging
        error_log("Failed to upload cover image for book ID {$book_id}: " . print_r($file, true));
        redirect('edit_book.php?id=' . $book_id);
    }
}

// Update book in database
$update_sql = "UPDATE books SET 
                isbn = ?, 
                title = ?, 
                author = ?, 
                publisher = ?, 
                publish_year = ?, 
                category_id = ?, 
                quantity = ?, 
                available_quantity = ?, 
                shelf_location = ?, 
                description = ?, 
                cover_image = ?
              WHERE book_id = ?";

$stmt = $conn->prepare($update_sql);
$stmt->bind_param("sssssiissssi", $isbn, $title, $author, $publisher, $publish_year, $category_id, 
                  $quantity, $available_quantity, $shelf_location, $description, $cover_image, $book_id);

if ($stmt->execute()) {
    // Log the activity
    $action = "Book updated";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Updated book: {$title} by {$author}";
    $log_stmt->bind_param("isss", $user_id, $action, $log_description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'Book updated successfully!');
    redirect('book_details.php?id=' . $book_id);
} else {
    setMessage('error', 'Failed to update book. Please try again.');
    redirect('edit_book.php?id=' . $book_id);
}

$stmt->close();
$conn->close();
?>