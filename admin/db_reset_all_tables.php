<?php
// db_reset_all_tables.php - Resets all known and suspected tables to clear hidden constraints.
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

// Use the reliable, absolute path for the root db.php file
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

// --- CRITICAL CONNECTION CHECK ---
// We expect the robust db.php to create the connection object $db.
if (!isset($db) || !($db instanceof mysqli)) {
    die("<h1>FATAL ERROR: Database Connection Failed!</h1><p>The <code>db.php</code> file failed to establish a proper <code>mysqli</code> connection (expected \$db object). Please check its contents and connection details.</p>");
}
$conn = $db; // Map the correct object ($db) to the script's expected variable ($conn)

// Set error reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Master Database Reset</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;margin:20px;background:#f4f5fb;color:#333}
h1{color:#ef4444;font-size:2em}
h2{color:#059669;font-size:1.5em}
p{margin:10px 0;font-size:1.1em}
.note{color:#f59e0b;font-weight:600}
.success-msg{color:#059669;font-weight:600}
.error-msg{color:#ef4444;font-weight:600}
</style>
</head>
<body>
<h1>Master Database Reset Execution</h1>
<?php
try {
    echo '<p class="note">Foreign key checks temporarily disabled for schema reset.</p>';
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    
    // --- STEP 1: FORCE-DROP ALL KNOWN/IMPLIED TABLES ---
    echo '<h2>1. Dropping existing tables (Including all suspected constraint holders)...</h2>';
    
    // BRUTE FORCE LIST: Covers all tables we've encountered or suspect.
    $tables_to_drop = [
        'driver_packages', 'packages', 'driver_chat', 'orders', 'order_driver', 
        'order_items', 'order_logs', 'drivers' // Drivers MUST be last
    ]; 
    $drop_count = 0;

    // Run the drop sequence multiple times to ensure all dependency chains are broken
    for ($i = 0; $i < 3; $i++) {
        foreach ($tables_to_drop as $table) {
            try {
                $conn->query("DROP TABLE IF EXISTS `{$table}`;");
                if ($i === 0) {
                     // Check if table actually existed and was dropped (optional, but cleaner output)
                     // If the query runs without error, we assume it was dropped or didn't exist, which is fine.
                     echo '<p>Table \'' . $table . '\' dropped (or already cleared).</p>';
                     $drop_count++;
                }
            } catch (\mysqli_sql_exception $e) {
                // Ignore errors during force drop
            }
        }
    }
    
    echo '<p class="success-msg">Tables cleared successfully. Proceeding to creation.</p>';

    // --- STEP 2: CREATE PACKAGES TABLE ---
    echo '<h2>2. Creating \'packages\' table...</h2>';
    $create_packages_sql = "
    CREATE TABLE packages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tracking_code VARCHAR(50) NOT NULL UNIQUE,
        recipient_name VARCHAR(255) NOT NULL,
        delivery_address TEXT NOT NULL,
        package_size ENUM('small', 'medium', 'large') NOT NULL,
        weight_kg DECIMAL(5, 2) NOT NULL,
        status ENUM('logged', 'assigned', 'picked_up', 'delivered', 'failed') NOT NULL DEFAULT 'logged',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_packages_sql);
    echo '<p class="success-msg">Table \'packages\' created successfully.</p>';

    // --- STEP 3: CREATE DRIVERS TABLE ---
    echo '<h2>3. Creating \'drivers\' table and inserting test data...</h2>';
    $create_drivers_sql = "
    CREATE TABLE drivers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20) UNIQUE,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_drivers_sql);
    
    // Insert a test driver
    $driver_code = 'DRIVER-1001';
    $name = 'Test Driver Alpha';
    $phone_number = '555-0101';
    $stmt = $conn->prepare("INSERT INTO drivers (driver_code, name, phone_number) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $driver_code, $name, $phone_number);
    $stmt->execute();
    $inserted_id = $stmt->insert_id;
    $stmt->close();

    echo '<p class="success-msg">Table \'drivers\' created successfully.</p>';
    echo '<p class="success-msg">Test driver inserted: ' . htmlspecialchars($driver_code) . ' (ID: ' . $inserted_id . ').</p>';

    // --- STEP 4: CREATE DRIVER_PACKAGES TABLE WITH FOREIGN KEYS ---
    echo '<h2>4. Creating \'driver_packages\' table with foreign keys...</h2>';
    $create_dp_sql = "
    CREATE TABLE driver_packages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_id INT UNSIGNED NOT NULL,
        package_id INT UNSIGNED NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        -- Composite unique index to ensure a package is assigned only once
        UNIQUE KEY uk_dp_assignment (package_id),

        -- Foreign Key: Links to drivers table
        CONSTRAINT fk_dp_driver
            FOREIGN KEY (driver_id) 
            REFERENCES drivers(id) 
            ON DELETE CASCADE
            ON UPDATE CASCADE,

        -- Foreign Key: Links to packages table
        CONSTRAINT fk_dp_package
            FOREIGN KEY (package_id) 
            REFERENCES packages(id) 
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_dp_sql);
    echo '<p class="success-msg">Table \'driver_packages\' created successfully (relationships established).</p>';
    
    // --- STEP 5: CREATE DRIVER_CHAT TABLE ---
    echo '<h2>5. Creating \'driver_chat\' table...</h2>';
    $create_dc_sql = "
    CREATE TABLE driver_chat (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_id INT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
        -- Foreign Key: Links to drivers table
        CONSTRAINT fk_dc_driver
            FOREIGN KEY (driver_id) 
            REFERENCES drivers(id) 
            ON DELETE CASCADE
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($create_dc_sql);
    echo '<p class="success-msg">Table \'driver_chat\' created successfully.</p>';


    echo '<h1>ALL DATABASE TABLES SUCCESSFULLY INITIALIZED!</h1>';

} catch (Throwable $e) {
    echo '<h1 class="error-msg">FATAL ERROR DURING MASTER SETUP!</h1>';
    echo '<p class="error-msg">A database error occurred: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("DB Master Setup Error: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        echo '<p class="note">Foreign key checks re-enabled.</p>';
    }
}
?>




