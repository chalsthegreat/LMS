<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/email_functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// Get and sanitize input
$full_name = sanitize($_POST['full_name']);
$username = sanitize($_POST['username']);
$email = sanitize($_POST['email']);
$phone = sanitize($_POST['phone'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$terms = isset($_POST['terms']) ? true : false;

// Validate required fields
if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
    setMessage('error', 'Please fill in all required fields');
    redirect('register.php');
}

// Validate username format (3-20 characters, alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    setMessage('error', 'Username must be 3-20 characters and contain only letters, numbers, and underscore');
    redirect('register.php');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage('error', 'Please enter a valid email address');
    redirect('register.php');
}

// Validate password length
if (strlen($password) < 6) {
    setMessage('error', 'Password must be at least 6 characters long');
    redirect('register.php');
}

// Check if passwords match
if ($password !== $confirm_password) {
    setMessage('error', 'Passwords do not match');
    redirect('register.php');
}

// Check if terms are accepted
if (!$terms) {
    setMessage('error', 'You must agree to the terms and conditions');
    redirect('register.php');
}

// Check if username already exists
$check_username = "SELECT user_id FROM users WHERE username = ? LIMIT 1";
$stmt = $conn->prepare($check_username);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    setMessage('error', 'Username already exists. Please choose another one.');
    redirect('register.php');
}
$stmt->close();

// Check if email already exists
$check_email = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($check_email);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    setMessage('error', 'Email already registered. Please use another email or login.');
    redirect('register.php');
}
$stmt->close();

// Generate OTP (6-digit code)
$otp_code = sprintf("%06d", mt_rand(1, 999999));

// Generate verification token
$verification_token = bin2hex(random_bytes(32));

// Set token expiration (15 minutes from now)
$token_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user into database (unverified)
$sql = "INSERT INTO users (username, password, email, full_name, phone, address, role, status, email_verified, verification_token, verification_token_expires) 
        VALUES (?, ?, ?, ?, ?, ?, 'member', 'inactive', 0, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", $username, $hashed_password, $email, $full_name, $phone, $address, $verification_token, $token_expires);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;
    
    // Store OTP in session for verification
    $_SESSION['pending_verification'] = [
        'user_id' => $user_id,
        'email' => $email,
        'otp' => $otp_code,
        'username' => $username,
        'full_name' => $full_name,
        'expires' => time() + (15 * 60) // 15 minutes
    ];
    
    // Send verification email
    $email_result = sendVerificationEmail($email, $full_name, $otp_code);
    
    if ($email_result['success']) {
        // Log the activity
        $action = "New user registered (pending verification)";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                    VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $description = "New user {$username} registered, verification email sent";
        $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
        
        // Redirect to verification page
        setMessage('success', 'Registration successful! Please check your email for the verification code.');
        redirect('verify_email.php');
    } else {
        // If email fails, delete the user and show error
        $delete_sql = "DELETE FROM users WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        setMessage('error', 'Failed to send verification email. Please try again later.');
        redirect('register.php');
    }
} else {
    setMessage('error', 'Registration failed. Please try again later.');
    redirect('register.php');
}

$stmt->close();
$conn->close();
?>