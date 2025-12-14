<?php
// Start session
session_start();

// Include configuration
require_once 'includes/config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on user role
    if (hasRole('admin') || hasRole('librarian')) {
        redirect('pages/dashboard.php');
    } else {
        redirect('pages/dashboard.php');
    }
} else {
    // Not logged in, redirect to login page
    redirect('pages/home.php');
}
?>