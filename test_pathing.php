<?php
// test_pathing.php - Placed in the ROOT directory (e.g., public_html/)

// -------------------------------------------------------------------------
// 1. Set Debugging
// -------------------------------------------------------------------------
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<h1>Pathing and Inclusion Test</h1>";
echo "<p>Running from: " . htmlspecialchars(__FILE__) . "</p>";

// -------------------------------------------------------------------------
// 2. Test Inclusion of db.php
// We will use the same path logic as bootstrap.php.
// -------------------------------------------------------------------------

$db_path = __DIR__ . '/db.php';
echo "<p>Attempting to include db.php from path: <strong>" . htmlspecialchars($db_path) . "</strong></p>";

try {
    require_once $db_path;

    // -------------------------------------------------------------------------
    // 3. Check for Successful Database Connection
    // The minimal db.php should have established $conn
    // -------------------------------------------------------------------------
    if (isset($conn) && is_object($conn) && !empty($conn->connect_error)) {
        // This means it connected but found an error.
        echo "<h2 style='color: orange;'>⚠️ SUCCESSFUL INCLUDE, BUT DATABASE ERROR FOUND</h2>";
        echo "<p>The file included successfully, but MySQL returned an error:</p>";
        echo "<pre style='color: orange;'>" . htmlspecialchars($conn->connect_error) . "</pre>";
    } else if (isset($conn) && is_object($conn) && empty($conn->connect_error)) {
        // Connection succeeded.
        echo "<h2 style='color: green;'>✅ SUCCESS! Database Connection Established.</h2>";
        echo "<p>Host Info: " . htmlspecialchars($conn->host_info) . "</p>";
    } else {
        // $conn was not set, meaning the db.php included, but failed silently before setting $conn
        echo "<h2 style='color: red;'>❌ FAILURE: db.php included, but \$conn was not a valid object.</h2>";
        echo "<p>This indicates a syntax or compile-time error inside db.php.</p>";
    }

} catch (Throwable $e) {
    // Catch any fatal errors during inclusion
    echo "<h2 style='color: red;'>❌ FATAL ERROR DURING REQUIRE_ONCE:</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr><p>End of script. If you see this, PHP did not crash!</p>";
?>
