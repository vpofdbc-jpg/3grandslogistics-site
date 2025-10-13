<?php
// db_init_drivers_admin.php - Initializes the drivers table
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
<title>Database Setup: Drivers</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;margin:20px;background:#f4f5fb;color:#333}
h1{color:#10b981;font-size:2em}
p{margin:10px 0;font-size:1.1em}
.note{color:#f59e0b;font-weight:600}
.success-msg{color:#10b981;font-weight:600}
</style>
</head>
<body>
<?php
try {
    echo '<p class="note">Foreign key checks temporarily disabled for entire script execution.</p>';
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

    // Drop table if it exists
    $conn->query("DROP TABLE IF EXISTS drivers;");
    echo '<p class="note">Pre-existing table \'drivers\' dropped successfully for schema reset.</p>';

    // Create the drivers table
    $create_table_sql = "
    CREATE TABLE drivers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20) UNIQUE,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_table_sql);

    // Insert a test driver for immediate use in the intake form
    $driver_code = 'DRIVER-1001';
    $name = 'Test Driver Alpha';
    $phone_number = '555-0101';
    $stmt = $conn->prepare("INSERT INTO drivers (driver_code, name, phone_number) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $driver_code, $name, $phone_number);
    $stmt->execute();
    $inserted_id = $stmt->insert_id;
    $stmt->close();

    echo '<h1>Setup Complete!</h1>';
    echo '<p>Table \'drivers\' created successfully.</p>';
    echo '<p class="success-msg">Successfully inserted test driver: ' . htmlspecialchars($driver_code) . ' (ID: ' . $inserted_id . ').</p>';


} catch (Throwable $e) {
    echo '<h1 class="note">Error during Setup! (Check Logs)</h1>';
    echo '<p class="note">A database error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("DB Setup Error: " . $e->getMessage());
} finally {
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo '<p class="note">Foreign key checks re-enabled.</p>';
}
?>




