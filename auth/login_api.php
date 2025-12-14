<?php
session_start();
require_once '../includes/config.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

// Get and sanitize input
$username = sanitize($_POST['username']);
$password = $_POST['password'];
$remember = isset($_POST['remember']) ? true : false;

// Validate input
if (empty($username) || empty($password)) {
    setMessage('error', 'Please enter both username and password');
    redirect('login.php');
}

// Check if user exists (by username or email)
$sql = "SELECT user_id, username, password, email, full_name, role, status, photo, email_verified 
        FROM users 
        WHERE (username = ? OR email = ?) 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage('error', 'Invalid username or password');
    redirect('login.php');
}

$user = $result->fetch_assoc();

// Verify password first
if (!password_verify($password, $user['password'])) {
    setMessage('error', 'Invalid username or password');
    redirect('login.php');
}

// Check if email is verified
if ($user['email_verified'] == 0) {
    setMessage('error', 'Please verify your email address before logging in. Check your inbox for the verification code.');
    redirect('login.php');
}

// Check if account is active
if ($user['status'] !== 'active') {
    setMessage('error', 'Your account is ' . $user['status'] . '. Please contact administrator.');
    redirect('login.php');
}

// Login successful - Set session variables
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['photo'] = $user['photo'];

// Set remember me cookie (optional)
if ($remember) {
    $token = bin2hex(random_bytes(32));
    setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 days
}

// Log the activity
$action = "User logged in";
$ip_address = $_SERVER['REMOTE_ADDR'];
$log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
            VALUES (?, ?, ?, ?)";
$log_stmt = $conn->prepare($log_sql);
$description = "User {$user['username']} logged in successfully";
$log_stmt->bind_param("isss", $user['user_id'], $action, $description, $ip_address);
$log_stmt->execute();

// Set success message
setMessage('success', 'Welcome back, ' . $user['full_name'] . '!');

// Redirect based on role
if ($user['role'] === 'admin' || $user['role'] === 'librarian') {
    redirect('../pages/dashboard.php');
} else {
    redirect('../pages/dashboard.php');
}

$stmt->close();
$conn->close();
?>