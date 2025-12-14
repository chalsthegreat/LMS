<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('verify_email.php');
}

// Check if there's a pending verification
if (!isset($_SESSION['pending_verification'])) {
    setMessage('error', 'No pending verification found. Please register first.');
    redirect('register.php');
}

$pending = $_SESSION['pending_verification'];

// Check if verification has expired
if (time() > $pending['expires']) {
    // Delete the unverified user
    $delete_sql = "DELETE FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $pending['user_id']);
    $stmt->execute();
    $stmt->close();
    
    unset($_SESSION['pending_verification']);
    setMessage('error', 'Verification code has expired. Please register again.');
    redirect('register.php');
}

// Get the OTP from form
$submitted_otp = sanitize($_POST['otp']);

// Validate OTP format
if (!preg_match('/^[0-9]{6}$/', $submitted_otp)) {
    setMessage('error', 'Invalid code format. Please enter a 6-digit code.');
    redirect('verify_email.php');
}

// Check if OTP matches
if ($submitted_otp !== $pending['otp']) {
    setMessage('error', 'Invalid verification code. Please check and try again.');
    redirect('verify_email.php');
}

// OTP is correct - Verify the user
$sql = "UPDATE users 
        SET email_verified = 1, 
            status = 'active',
            verification_token = NULL,
            verification_token_expires = NULL
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pending['user_id']);

if ($stmt->execute()) {
    // Log the activity
    $action = "Email verified";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "User {$pending['username']} verified their email successfully";
    $log_stmt->bind_param("isss", $pending['user_id'], $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    // Send welcome email
    sendWelcomeEmail($pending['email'], $pending['full_name']);
    
    // Clear pending verification
    unset($_SESSION['pending_verification']);
    
    // Set success message and redirect to login
    setMessage('success', 'Email verified successfully! You can now login to your account.');
    redirect('login.php');
} else {
    setMessage('error', 'Verification failed. Please try again.');
    redirect('verify_email.php');
}

$stmt->close();
$conn->close();
?>