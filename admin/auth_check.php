<?php
// admin/auth_check.php
// This script ensures only authenticated admin users can access the page.

// 1. Start the session if it hasn't been already.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. CRITICAL CHECK: Look for the specific $_SESSION['admin_id'] variable.
// This variable is set upon successful login in your login.php file.
// We check if it is NOT set, or if it is set to a non-positive value (like 0).
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_id'] < 1) {
    
    // 3. If the user is NOT authenticated, redirect them to the login page.
    header("Location: /admin/login.php");
    exit();
}

// If the code reaches here, the user is authenticated, and the page will load.
?>


