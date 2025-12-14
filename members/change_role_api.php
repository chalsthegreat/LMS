<?php
session_start();
require_once '../includes/config.php';

// Only Admin can access this API
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setMessage('error', 'Invalid request method.');
    redirect('members.php');
}

// Get and sanitize input
$user_id = isset($_POST['user_id']) ? sanitize($_POST['user_id']) : null;
$new_role = isset($_POST['new_role']) ? sanitize($_POST['new_role']) : null;

// Validate input
if (empty($user_id) || empty($new_role)) {
    setMessage('error', 'Missing user ID or new role.');
    redirect('members.php');
}

// Ensure the new role is a valid option
$allowed_roles = ['admin', 'librarian', 'member'];
if (!in_array($new_role, $allowed_roles)) {
    setMessage('error', 'Invalid role selected.');
    redirect('members.php');
}

// Prevent changing own role
if ((int)$user_id === (int)$_SESSION['user_id']) {
    setMessage('error', 'You cannot change your own role.');
    redirect('members.php');
}

// Database update
$update_sql = "UPDATE users SET role = ? WHERE user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $new_role, $user_id);

if ($update_stmt->execute()) {
    // Log the action
    $admin_id = $_SESSION['user_id'];
    $action = "Role Change";
    $description = "User ID {$user_id} role changed to {$new_role} by Admin ID {$admin_id}.";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("isss", $admin_id, $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();

    setMessage('success', "Role for user ID: {$user_id} successfully changed to " . ucfirst($new_role) . ".");
} else {
    setMessage('error', 'Failed to update user role: ' . $conn->error);
}

$update_stmt->close();
redirect('members.php');

?>
