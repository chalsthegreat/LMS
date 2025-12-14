<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$user_id = $_SESSION['user_id'];
$file = $_FILES['photo'];

// Get file extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file extension
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed']);
    exit;
}

// Additional validation: Check MIME type (more lenient)
$allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/gif', 'image/x-png'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_mime_types)) {
    // Fallback: Check if getimagesize works (validates it's actually an image)
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        echo json_encode(['success' => false, 'message' => 'The uploaded file is not a valid image']);
        exit;
    }
}

// Validate file size (max 5MB)
if ($file['size'] > 5242880) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/users/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Get old photo to delete it later
$sql = "SELECT photo FROM users WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$old_photo = $user['photo'];
$stmt->close();

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Update database
    $update_sql = "UPDATE users SET photo = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_filename, $user_id);
    
    if ($update_stmt->execute()) {
        // Delete old photo if exists
        if ($old_photo && file_exists($upload_dir . $old_photo)) {
            unlink($upload_dir . $old_photo);
        }
        
        // Update session
        $_SESSION['photo'] = $new_filename;
        
        // Log the activity
        $action = "Profile photo updated";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $description = "User updated their profile photo";
        $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Photo uploaded successfully',
            'photo_url' => '../uploads/users/' . $new_filename
        ]);
    } else {
        // Delete uploaded file if database update fails
        unlink($upload_path);
        echo json_encode(['success' => false, 'message' => 'Failed to update database']);
    }
    
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}

$conn->close();
?>