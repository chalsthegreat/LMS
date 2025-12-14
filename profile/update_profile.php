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

// Get and sanitize input
$full_name = sanitize($_POST['full_name']);
$email = sanitize($_POST['email']);
$phone = sanitize($_POST['phone'] ?? '');
$address = sanitize($_POST['address'] ?? '');

// Validate required fields
if (empty($full_name) || empty($email)) {
    setMessage('error', 'Full name and email are required');
    redirect('profile.php');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage('error', 'Please enter a valid email address');
    redirect('profile.php');
}

// Check if email is already used by another user
$check_email = "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1";
$stmt = $conn->prepare($check_email);
$stmt->bind_param("si", $email, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    setMessage('error', 'This email is already in use by another account');
    redirect('profile.php');
}
$stmt->close();

// Update user information
$sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);

if ($stmt->execute()) {
    // Update session variables
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    
    // Log the activity
    $action = "Profile updated";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "User updated their profile information";
    $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'Profile updated successfully!');
} else {
    setMessage('error', 'Failed to update profile. Please try again.');
}

$stmt->close();
$conn->close();
redirect('profile.php');
?>