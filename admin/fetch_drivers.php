<?php
// Configuration
header('Content-Type: application/json');
$response = ["success" => false, "drivers" => [], "error" => ""];

// ===============================================
// !!! IMPORTANT: REPLACE WITH YOUR ACTUAL DB CREDENTIALS !!!
// ===============================================
$host = 'localhost';
$db   = 'hearmysm_Delivery_app'; // <--- Replace with your database name
$user = 'hearmysm_Delivery_user'; // <--- Replace with your database user
$pass = 'Trecarcas@4844'; // <--- Replace with your database password
// ===============================================

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Database Interaction ---
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $response["error"] = "Database connection failed. Check credentials/config.";
    // For debugging, you can use $e->getMessage(), but it's often best to hide detailed errors in production
    error_log("DB Connection Error: " . $e->getMessage()); 
    echo json_encode($response);
    exit;
}

try {
    // Select all drivers. ORDER BY is_online DESC ensures online drivers are listed first.
    // Ensure your table name is 'drivers' and columns are 'user_id', 'is_online', 'lat', 'lng'.
    $stmt = $pdo->query("SELECT user_id, is_online, lat, lng FROM drivers ORDER BY is_online DESC, user_id ASC");
    $drivers = $stmt->fetchAll();

    $response["success"] = true;
    $response["drivers"] = $drivers;

} catch (\PDOException $e) {
    $response["error"] = "DB Query Error: Could not fetch driver list. Check your 'drivers' table and column names.";
    error_log("DB Query Error: " . $e->getMessage()); 
}

echo json_encode($response);
?>
