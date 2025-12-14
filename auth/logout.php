<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    
    // Log the activity before destroying session
    $action = "User logged out";
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "User {$username} logged out successfully";
    $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
    $log_stmt->execute();
    $log_stmt->close();
}

// Destroy all session data
session_unset();
session_destroy();

// Delete remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/");
}

// Redirect to login with message
session_start();
setMessage('success', 'You have been logged out successfully.');
redirect('login.php');
?>