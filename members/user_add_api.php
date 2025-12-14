<?php
// Start session and configuration
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check if user is logged in and has appropriate role (admin or librarian)
if (!isLoggedIn() || (!hasRole('admin') && !hasRole('librarian'))) {
    http_response_code(403);
    $response['message'] = 'Access denied. You do not have permission to perform this action.';
    echo json_encode($response);
    exit();
}

// Check if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit();
}

try {
    // Get and sanitize input
    $full_name = sanitize($_POST['full_name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'member'); // Default to member if not set
    $status = sanitize($_POST['status'] ?? 'active'); // Default to active if not set

    // Basic validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        throw new Exception('Please fill in all required fields (Full Name, Username, Email, Password).');
    }

    // Validate username format (3-20 characters, alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        throw new Exception('Username must be 3-20 characters and contain only letters, numbers, and underscore.');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }

    // Validate password length
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long.');
    }

    // Validate role
    $allowed_roles = ['admin', 'librarian', 'member'];
    if (!in_array($role, $allowed_roles)) {
        throw new Exception('Invalid role specified.');
    }

    // Validate status
    $allowed_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid status specified.');
    }

    // Check for existing username
    $sql_username = "SELECT user_id FROM users WHERE username = ? LIMIT 1";
    $stmt_username = $conn->prepare($sql_username);
    $stmt_username->bind_param("s", $username);
    $stmt_username->execute();
    $result_username = $stmt_username->get_result();
    if ($result_username->num_rows > 0) {
        throw new Exception('Username already taken. Please choose another.');
    }
    $stmt_username->close();

    // Check for existing email
    $sql_email = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt_email = $conn->prepare($sql_email);
    $stmt_email->bind_param("s", $email);
    $stmt_email->execute();
    $result_email = $stmt_email->get_result();
    if ($result_email->num_rows > 0) {
        throw new Exception('Email already registered. Please use another email.');
    }
    $stmt_email->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into database
    $sql = "INSERT INTO users (username, password, email, full_name, phone, address, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $username, $hashed_password, $email, $full_name, $phone, $address, $role, $status);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        
        // Log the activity
        $action = "User added by " . $_SESSION['username'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                    VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $description = "New user {$username} (ID: {$new_user_id}) created by admin/librarian.";
        $log_stmt->bind_param("isss", $_SESSION['user_id'], $action, $description, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();

        $response['success'] = true;
        $response['message'] = "User **{$full_name}** added successfully with role: **{$role}**.";
    } else {
        throw new Exception("Database error: Could not insert user.");
    }

    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(400); // Bad Request or other client error
    $response['message'] = $e->getMessage();
}

// Return the JSON response
echo json_encode($response);
?>
