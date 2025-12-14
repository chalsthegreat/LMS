<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_functions.php';

// Check if there's a pending verification
if (!isset($_SESSION['pending_verification'])) {
    setMessage('error', 'No pending verification found. Please register first.');
    redirect('register.php');
}

$pending = $_SESSION['pending_verification'];

// Generate new OTP
$otp_code = sprintf("%06d", mt_rand(1, 999999));

// Update session with new OTP and extended expiration
$_SESSION['pending_verification']['otp'] = $otp_code;
$_SESSION['pending_verification']['expires'] = time() + (15 * 60); // 15 minutes

// Update database expiration
$token_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$sql = "UPDATE users SET verification_token_expires = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $token_expires, $pending['user_id']);
$stmt->execute();
$stmt->close();

// Send new verification email
$email_result = sendVerificationEmail($pending['email'], $pending['full_name'], $otp_code);

if ($email_result['success']) {
    // Log the activity
    $action = "OTP resent";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "Verification code resent to {$pending['email']}";
    $log_stmt->bind_param("isss", $pending['user_id'], $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
    
    setMessage('success', 'A new verification code has been sent to your email!');
} else {
    setMessage('error', 'Failed to resend verification code. Please try again.');
}

redirect('verify_email.php');
?>