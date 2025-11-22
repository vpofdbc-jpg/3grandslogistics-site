<?php
// FILE: db_connect.php
// This file establishes the connection to the MySQL database.

// >>> CRITICAL: REPLACE THESE PLACEHOLDERS WITH YOUR REAL DATABASE CREDENTIALS <<<
// Even a single typo will cause an HTTP 500 error.

$servername = "localhost";           // 1. Database Hostname (usually 'localhost')
$username = "hearmysm_Delivery_user";  // 2. Database Username
$password = "Trecarcas@4844";  // 3. Database Password
$dbname = "hearmysm_Delivery_app";        // 4. Database Name

// --- Connection Logic ---

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// *** ERROR CHECKING ***
// If the connection fails, this will display the specific error message 
// instead of a generic HTTP 500.
if ($conn->connect_error) {
    // We die here to prevent any script from running without a database connection.
    die("CRITICAL DATABASE CONNECTION ERROR: Failed to connect to MySQL: " . $conn->connect_error);
}

// Optional: Set character set
$conn->set_charset("utf8mb4");

// Connection is now successful ($conn object is ready).
?>
