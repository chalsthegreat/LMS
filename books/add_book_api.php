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
    redirect('add_book.php');
}

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
    redirect('add_book.php');
}

// Validate quantities
if ($quantity < 1) {
    setMessage('error', 'Quantity must be at least 1');
    redirect('add_book.php');
}

if ($available_quantity < 0 || $available_quantity > $quantity) {
    setMessage('error', 'Available quantity must be between 0 and total quantity');
    redirect('add_book.php');
}

// Check if ISBN already exists (if provided)
if (!empty($isbn)) {
    $check_isbn = "SELECT book_id FROM books WHERE isbn = ? LIMIT 1";
    $stmt = $conn->prepare($check_isbn);
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        setMessage('error', 'A book with this ISBN already exists');
        redirect('add_book.php');
    }
    $stmt->close();
}

// Handle cover image upload
$cover_image = null;
$upload_dir = '../Uploads/books/';

if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['cover_image'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_type = mime_content_type($file['tmp_name']) ?: $file['type'];
    
    if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
        setMessage('error', 'Invalid cover image file type. Only JPG, PNG, and GIF are allowed');
        redirect('add_book.php');
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5242880) {
        setMessage('error', 'Cover image file size exceeds 5MB limit');
        redirect('add_book.php');
    }
    
    // Check for partial upload
    if ($file['size'] === 0 || !is_uploaded_file($file['tmp_name'])) {
        setMessage('error', 'File upload failed. The file may be corrupted or too large.');
        redirect('add_book.php');
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $cover_image = 'book_' . time() . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $cover_image;
    
    // Move uploaded file with proper permissions
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        chmod($upload_path, 0644); // Set file permissions
    } else {
        $cover_image = null;
        setMessage('error', 'Failed to upload cover image. Please check server permissions or try again.');
        // Log error for debugging
        error_log("Failed to upload cover image for new book: " . print_r($file, true));
        redirect('add_book.php');
    }
}

// Insert book into database
$sql = "INSERT INTO books (isbn, title, author, publisher, publish_year, category_id, quantity, available_quantity, shelf_location, description, cover_image) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssiissss", $isbn, $title, $author, $publisher, $publish_year, $category_id, $quantity, $available_quantity, $shelf_location, $description, $cover_image);

if ($stmt->execute()) {
    $book_id = $stmt->insert_id;
    
    // Log the activity
    $action = "New book added";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_description = "Added new book: {$title} by {$author}";
    $log_stmt->bind_param("isss", $user_id, $action, $log_description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'Book added successfully!');
    redirect('book_details.php?id=' . $book_id);
} else {
    // Delete uploaded image if database insert fails
    if ($cover_image && file_exists($upload_dir . $cover_image)) {
        unlink($upload_dir . $cover_image);
    }
    
    setMessage('error', 'Failed to add book. Please try again.');
    redirect('add_book.php');
}

$stmt->close();
$conn->close();
?>