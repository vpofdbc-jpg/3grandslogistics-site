<?php
// /driver/update_location.php - Endpoint to receive driver GPS coordinates
declare(strict_types=1);
session_start();
ini_set('display_errors','0'); // Suppress errors for API endpoint response
header('Content-Type: application/json');

// Check authentication: The driver must be logged in.
if (empty($_SESSION['driver_id'])) { 
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']); 
    exit; 
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']); 
    exit;
}

// Basic CSRF check (important for POST requests)
$csrf = $_POST['csrf'] ?? '';
if ($csrf === '' || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

// 1. Validate Input (latitude and longitude)
$latitude = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);
$driverId = (int)$_SESSION['driver_id'];

if ($latitude === false || $longitude === false || $latitude === null || $longitude === null) {
    echo json_encode(['ok' => false, 'error' => 'Invalid coordinates']); 
    exit;
}

// 2. Connect to Database
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

if (!$conn instanceof mysqli) {
    error_log("Failed to connect to database in update_location.php");
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']); 
    exit;
}

try {
    // 3. Update the Database
    // This query updates the current location of the driver in the 'drivers' table.
    // It only updates if the driver is currently marked as is_online = 1.
    $st = $conn->prepare("UPDATE drivers SET 
        latitude = ?, 
        longitude = ?, 
        location_updated_at = NOW() 
        WHERE id = ? AND is_online = 1"); 
    
    // 'd' is for double/float types (latitude, longitude), 'i' is for integer (driverId)
    $st->bind_param('ddi', $latitude, $longitude, $driverId);
    $st->execute();
    $st->close();
    
    // Success response - minimal data is fastest
    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    // Log the error internally but return a generic failure message to the client
    error_log("DB error updating location for driver $driverId: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}