<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define the absolute path to the main directory's db.php
// We use $_SERVER['DOCUMENT_ROOT'] which always points to the public_html folder.
$db_file_path = $_SERVER['DOCUMENT_ROOT'] . '/db.php';

echo "<h1>Subdirectory Connection Test</h1>";

// 1. Check if the file exists using the absolute path
if (file_exists($db_file_path)) {
    echo "<p><strong style='color: green;'>**SUCCESS:**</strong> Database file found at: <code>" . htmlspecialchars($db_file_path) . "</code></p>";

    // 2. Safely attempt to include the file and suppress warnings/errors during include
    // We expect this to work now.
    @include $db_file_path;

    // 3. Check for the connection variable ($db) after inclusion
    if (isset($db) && is_object($db)) {
        echo "<p><strong style='color: green;'>**SUCCESS:**</strong> Database connection established!</p>";

        // 4. Try running a simple query to ensure the connection is active
        try {
            $result = $db->query("SELECT 1+1 AS test_result");
            $row = $result->fetch_assoc();
            if ($row && $row['test_result'] == 2) {
                echo "<p><strong style='color: green;'>**SUCCESS:**</strong> Database query test successful!</p>";
            } else {
                echo "<p><strong style='color: orange;'>**WARNING:**</strong> Connection established, but simple query failed.</p>";
            }
        } catch (Exception $e) {
             echo "<p><strong style='color: red;'>**ERROR:**</strong> Database query failed during test: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

    } else {
        echo "<p><strong style='color: red;'>**ERROR:**</strong> File included, but \$db variable is not a valid database connection object. This indicates an issue inside the contents of <code>db.php</code> itself.</p>";
    }

} else {
    echo "<p><strong style='color: red;'>**ERROR:**</strong> Database file **NOT** found at expected absolute path: <code>" . htmlspecialchars($db_file_path) . "</code>. Please ensure <code>db.php</code> is in the main <code>public_html</code> folder.</p>";
}

// Ensure the script executes to the end without crashing
echo "<hr><p>Script finished execution.</p>";

?>
