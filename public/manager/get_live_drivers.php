<?php
// Set headers for JSON output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// --- 1. Database Configuration ---
$db_host = "localhost";
$db_user = "hearmysm_Delivery_user";
// CORRECT PASSWORD INSERTED HERE
$db_pass = "Trecarcas@4844"; 
$db_name = "hearmysm_Delivery_app";
$charset = 'utf8mb4';

// File for simple error logging (optional, but helpful for debugging)
$error_log_file = __DIR__ . "/error_log.txt";

function log_error($message) {
    global $error_log_file;
    file_put_contents($error_log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// --- 2. Connection Setup ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 1. Attempt to connect
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // 2. SQL Query: Select online drivers with valid location data
    $sql = "SELECT 
                d.user_id,
                u.username,
                l.latitude AS lat,
                l.longitude AS lng
            FROM drivers d
            LEFT JOIN users u ON d.user_id = u.id 
            JOIN driver_locations l ON d.user_id = l.user_id
            WHERE d.is_online = 1 AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
            
    $stmt = $pdo->query($sql);
    $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Return the array of drivers as JSON
    echo json_encode([
        'success' => true,
        'drivers' => $drivers
    ]);

} catch (\PDOException $e) {
    // Return a 500 error if connection or query fails
    http_response_code(500);
    $errorMessage = $e->getMessage();
    log_error("DB Error: " . $errorMessage);
    echo json_encode([
        'success' => false,
        // Using a generic message for security, but logging the detail
        'error' => "Failed to retrieve driver data. See server log for details." 
    ]);
}
?>