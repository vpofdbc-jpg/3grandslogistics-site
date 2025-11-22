<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Path to the database connection file
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
// Check if the connection variable ($conn) was successfully created
if (!$conn || $conn->connect_error) {
    echo "<h1>CRITICAL ERROR</h1>";
    echo "<p>Database connection failed.</p>";
    if (isset($conn) && $conn->connect_error) {
        echo "<p><strong>Specific Error:</strong> " . $conn->connect_error . "</p>";
    }
    exit;
}

// 1. Define the table name for driver-package assignments
$tableName = "driver_packages";

// --- CRITICAL STEP: Temporarily disable foreign key checks ---
// This ensures that table creation succeeds even if 'drivers' or 'packages' tables 
// were temporarily dropped or the creation order is strict.
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
echo "<p style='color: orange;'>Foreign key checks temporarily disabled.</p>";


// Drop the table to ensure a clean slate and correct schema
$drop_sql = "DROP TABLE IF EXISTS $tableName";
if ($conn->query($drop_sql) === TRUE) {
    echo "<p style='color: orange;'>Pre-existing table '$tableName' dropped successfully for schema reset.</p>";
} else {
    echo "<p style='color: red;'>Warning: Could not drop table '$tableName': " . $conn->error . "</p>";
}


// 2. SQL to create the driver_packages table
// This table links drivers (drivers.id) to packages (packages.id)
$sql = "CREATE TABLE $tableName (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Link to the drivers table
    driver_id INT NOT NULL, 
    
    -- Link to the packages table (we assume packages.id is INT)
    package_id INT NOT NULL UNIQUE, 

    -- Status of the package *for this driver*
    assignment_status ENUM('assigned', 'en_route', 'delivered', 'failed') DEFAULT 'assigned',
    
    -- Timestamps
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_attempted_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    -- Composite Index for faster lookups
    INDEX idx_driver_status (driver_id, assignment_status),

    -- Define Foreign Keys
    -- IMPORTANT: 'INT' matches the primary key type in 'drivers'
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE RESTRICT,
    
    -- IMPORTANT: 'INT' matches the primary key type in 'packages'
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
)";

// 3. Execute the SQL command
if ($conn->query($sql) === TRUE) {
    echo "<h1><span style='color: green;'>Setup Complete!</span></h1>";
    echo "<p>Table '$tableName' created successfully. This table links drivers and packages.</p>";

} else {
    // If the table creation failed (usually permissions or syntax error)
    echo "<h1><span style='color: red;'>Table Creation Error</span></h1>";
    echo "<p>Error creating table: " . $conn->error . "</p>";
    echo "<p>Ensure 'drivers' and 'packages' tables exist and use INT primary keys.</p>";
}

// --- CRITICAL STEP: Re-enable foreign key checks before closing connection ---
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "<p style='color: orange;'>Foreign key checks re-enabled.</p>";

// Close connection
$conn->close();
?>
