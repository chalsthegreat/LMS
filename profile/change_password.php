<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

$user_id = $_SESSION['user_id'];

// Get input
$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Validate input
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    setMessage('error', 'All password fields are required');
    redirect('profile.php');
}

// Check if new passwords match
if ($new_password !== $confirm_password) {
    setMessage('error', 'New passwords do not match');
    redirect('profile.php');
}

// Validate new password length
if (strlen($new_password) < 6) {
    setMessage('error', 'New password must be at least 6 characters long');
    redirect('profile.php');
}

// Get current password from database
$sql = "SELECT password FROM users WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    setMessage('error', 'Current password is incorrect');
    redirect('profile.php');
}

// Check if new password is same as current
if ($current_password === $new_password) {
    setMessage('error', 'New password must be different from current password');
    redirect('profile.php');
}

// Hash new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $hashed_password, $user_id);

if ($update_stmt->execute()) {
    // Log the activity
    $action = "Password changed";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "User changed their password";
    $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'Password changed successfully!');
} else {
    setMessage('error', 'Failed to change password. Please try again.');
}

$stmt->close();
$update_stmt->close();
$conn->close();
redirect('profile.php');
?>