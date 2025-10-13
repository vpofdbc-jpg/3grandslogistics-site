<?php
// /admin/driver_send_message.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$fail = function(string $m, int $code=400): never {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$m]); exit;
};

if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  $fail('csrf', 403);
}

$driverId = (int)($_POST['driver_id'] ?? 0);
$msg      = trim((string)($_POST['message'] ?? ''));
if ($driverId <= 0 || $msg === '') $fail('bad_params');

$conn->query("CREATE TABLE IF NOT EXISTS driver_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INT NOT NULL,
  sender ENUM('driver','admin') NOT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(driver_id), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$st = $conn->prepare("INSERT INTO driver_messages (driver_id, sender, message, created_at) VALUES (?,?,?,NOW())");
$sender = 'admin';
$st->bind_param('iss', $driverId, $sender, $msg);
$st->execute(); $st->close();

echo json_encode(['ok'=>true]);

