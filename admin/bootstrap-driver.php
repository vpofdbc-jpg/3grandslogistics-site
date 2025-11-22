<?php
// bootstrap-driver.php - Test version for subdirectory driver files

// 1. Absolute Path Inclusion Test
// We require the db.php file, but we won't try to connect to the database.
// This proves the file inclusion mechanism works without DB credentials causing issues.
require_once(__DIR__ . '/db.php');

// 2. Simple Output Test
echo "<!-- bootstrap-driver.php successfully loaded the db.php file. -->";

// 3. Keep the original `bootstrap.php` code for future restoration of session/auth logic.
// We are skipping session_start() and auth for this test.
?>