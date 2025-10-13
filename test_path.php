<?php
// Set error reporting to maximum to catch all potential issues
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Database Connection Test</h1>";

// --- STEP 1: Test file existence ---
$db_path_alt = __DIR__ . '/db.php'; // Path used in your update_status.php code
$db_path_config = __DIR__ . '/config/db_connect.php'; // Another common path

$found_path = null;
if (file_exists($db_path_alt)) {
    $found_path = $db_path_alt;
} elseif (file_exists($db_path_config)) {
    $found_path = $db_path_config;
}

if ($found_path) {
    echo "<p style='color: green;'>✅ **SUCCESS:** Database file found at: <code>" . htmlspecialchars($found_path) . "</code></p>";
    echo "<hr>";
} else {
    echo "<p style='color: red;'>❌ **ERROR:** Database connection file not found. Checked paths: <code>/db.php</code> and <code>/config/db_connect.php</code> from the root directory.</p>";
    exit;
}

// --- STEP 2: Attempt to load the file and connect ---
// We suppress errors during the require to try and catch them cleanly in the catch block
// However, fatal PHP errors might still cause a 500, but displaying errors should help.
try {
    require_once($found_path);

    echo "<p>File loaded successfully. Now checking connection variable...</p>";

    // We assume the connection variable is $conn (or $mysqli)
    $conn = $conn ?? ($mysqli ?? null);

    if ($conn && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "<p style='color: red;'>❌ **ERROR:** Connection failed! Error: " . htmlspecialchars($conn->connect_error) . "</p>";
        } else {
            echo "<p style='color: green; font-size: 1.25rem;'>🎉 **SUCCESS:** Database connection established!</p>";
            echo "<p>Host Info: " . htmlspecialchars($conn->host_info) . "</p>";
            $conn->close();
        }
    } else {
        echo "<p style='color: orange;'>⚠️ **WARNING:** The required connection variable (<code>\$conn</code> or <code>\$mysqli</code>) was not created or is not an object. This likely means the database file had a syntax error and crashed, or the connection failed silently.</p>";
        echo "<p>If the page still shows a 500 error, there is a fundamental syntax issue in the included database file itself.</p>";
    }
} catch (Throwable $e) {
    // This catches exceptions thrown during the require process or connection attempt
    echo "<p style='color: red;'>💥 **FATAL ERROR** caught during database file include or connection:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Check the exact contents of your database connection file for syntax errors.</p>";
}

?>

