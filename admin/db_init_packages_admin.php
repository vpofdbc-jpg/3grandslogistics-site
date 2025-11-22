<?php
// db_init_packages_admin.php - Initializes the packages table
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
<title>Database Setup: Packages</title>
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
    $conn->query("DROP TABLE IF EXISTS packages;");
    echo '<p class="note">Pre-existing table \'packages\' dropped successfully for schema reset.</p>';

    // Create the packages table with all necessary columns
    $create_table_sql = "
    CREATE TABLE packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tracking_code VARCHAR(50) NOT NULL UNIQUE,
        recipient_name VARCHAR(255) NOT NULL,
        delivery_address TEXT NOT NULL,
        package_size ENUM('small', 'medium', 'large') NOT NULL,
        weight_kg DECIMAL(5, 2) NOT NULL,
        status ENUM('logged', 'assigned', 'picked_up', 'delivered', 'failed') NOT NULL DEFAULT 'logged',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_table_sql);
    
    echo '<h1>Setup Complete!</h1>';
    echo '<p>Table \'packages\' created successfully with full schema (including **status**).</p>';
    echo '<p>The `packages` table is now ready for intake. No data was inserted.</p>';

} catch (Throwable $e) {
    echo '<h1 style="color:#ef4444;">Error during Setup!</h1>';
    echo '<p>A database error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("DB Setup Error: " . $e->getMessage());
} finally {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo '<p class="note">Foreign key checks re-enabled.</p>';
}
?>
