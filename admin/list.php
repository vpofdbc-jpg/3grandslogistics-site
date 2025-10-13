<?php
// Package Logistics Dashboard (VIEWER)

declare(strict_types=1);

// 1. Database Connection
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
// 2. Session Start (from admin_header.php)
session_start();

// 3. Simple Database Query (must be before any output)
$db = $db_connect;

function fetch_test_data(mysqli $db): array {
    $sql = "SELECT id, tracking_id FROM packages LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt || !$stmt->execute()) {
        return ['Database Error' => $db->error . ' / ' . $stmt->error];
    }
    $result = $stmt->get_result();
    $data = $result->fetch_assoc() ?? ['No Records'];
    $stmt->close();
    return $data;
}

$test_data = fetch_test_data($db);
$db->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Minimal Test Output</title>
</head>
<body>
    <h1>TEST SUCCESS: PHP LOGIC EXECUTED</h1>
    <p>If you see this, the entire PHP section (includes, session, database) worked.</p>
    
    <h2>Test Data Fetched:</h2>
    <pre><?php print_r($test_data); ?></pre>

</body>
</html>









