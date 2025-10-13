<?php
/**
 * Application Bootstrap File
 * Path: /public_html/bootstrap.php
 */

// 1. Start Session Management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Include the Database Configuration
require_once('db.php');

// 3. Define Global Utility Functions (Optional, but useful)
/**
 * Utility function for safely redirecting the user.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

?>









