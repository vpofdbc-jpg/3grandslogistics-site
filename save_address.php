<?php
// save_address.php - AJAX endpoint to save favorite addresses
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  echo json_encode(['ok'=>false,'error'=>'Not logged in']); exit;
}
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$uid    = (int)$_SESSION['user_id'];
$kind   = strtolower(trim($_POST['kind'] ?? ''));
$label  = trim($_POST['label'] ?? '');
$address= trim($_POST['address'] ?? '');

if (!in_array($kind, ['pickup','delivery','both'], true)) { echo json_encode(['ok'=>false,'error'=>'Bad kind']); exit; }
if ($label === '' || $address === '') { echo json_encode(['ok'=>false,'error'=>'Missing label/address']); exit; }

// Ensure table exists
$conn->query("
  CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    type ENUM('pickup','delivery','both') NOT NULL DEFAULT 'both',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$stmt = $conn->prepare("INSERT INTO user_addresses (user_id,label,address,type) VALUES (?,?,?,?)");
$stmt->bind_param('isss', $uid, $label, $address, $kind);
$stmt->execute();

echo json_encode(['ok'=>true,'id'=>$stmt->insert_id]);
