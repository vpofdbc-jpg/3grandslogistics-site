<?php
// db.php - Database connection file

// 1. Define Database Credentials
// REPLACE THESE VALUES with your actual database credentials
const DB_SERVER = 'localhost';   // Usually 'localhost'
const DB_USERNAME = 'hearmysm_Delivery_user'; // Your database username
const DB_PASSWORD = 'Trecarcas@4844'; // Your database password
const DB_NAME = 'hearmysm_Delivery_app';   // Your database name

// 2. Attempt to create the connection object
// The connection object is stored in the variable $conn
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 3. Check connection and handle errors immediately
if ($conn->connect_error) {
    // Log the actual error for debugging (visible in server logs, not browser)
    error_log("Database connection failed: " . $conn->connect_error);

    // Show a user-friendly error message and stop script execution
    die("ERROR: Could not connect to the database. Please check configuration.");
}

// 4. Set character set to UTF-8 for proper encoding
$conn->set_charset("utf8mb4");

// NOTE: The $conn object is now available to any file that uses 'require_once "db.php";'




