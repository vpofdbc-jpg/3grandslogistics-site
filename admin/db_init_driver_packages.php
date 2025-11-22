<?php
// db_init_driver_packages.php - Initializes the driver_packages table
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

// CORRECTED PATH: Use dirname(__DIR__) to go up one level (from /admin/ to /)
// Use the reliable, absolute path with require_once
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php'; 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
$conn->set_charset('utf8mb4');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database Setup: Driver Packages</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;margin:20px;background:#f4f5fb;color:#333}
h1{color:#10b981;font-size:2em}
p{margin:10px 0;font-size:1.1em}
.note{color:#f59e0b;font-weight:600}
</style>
</head>
<body>
<?php
try {
    echo '<p class="note">Foreign key checks temporarily disabled.</p>';
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

    // Drop table if it exists
    $conn->query("DROP TABLE IF EXISTS driver_packages;");
    echo '<p class="note">Pre-existing table \'driver_packages\' dropped successfully for schema reset.</p>';

    // Create the driver_packages table
    $create_table_sql = "
    CREATE TABLE driver_packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id INT NOT NULL,
        package_id INT NOT NULL,
        assignment_status ENUM('assigned', 'picked_up', 'delivered', 'failed') NOT NULL DEFAULT 'assigned',
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        -- Composite unique index to ensure a package is assigned to only one driver at a time
        UNIQUE KEY uk_package_driver (package_id, driver_id),
        
        -- Foreign Key Constraints
        CONSTRAINT fk_dp_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
        CONSTRAINT fk_dp_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_table_sql);
    
    echo '<h1>Setup Complete!</h1>';
    echo '<p>Table \'driver_packages\' created successfully. This table links drivers and packages.</p>';

} catch (Throwable $e) {
    echo '<h1 style="color:#ef4444;">Error during Setup!</h1>';
    echo '<p>A database error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("DB Setup Error: " . $e->getMessage());
} finally {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo '<p class="note">Foreign key checks re-enabled.</p>';
}
?>

