<?php
// /driver/typing.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['driver_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$conn->query("CREATE TABLE IF NOT EXISTS typing_status (
  driver_id INT NOT NULL,
  actor ENUM('admin','driver') NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (driver_id, actor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$driver_id = (int)$_SESSION['driver_id'];

$st = $conn->prepare("INSERT INTO typing_status (driver_id,actor,updated_at)
                      VALUES (?,'driver',NOW())
                      ON DUPLICATE KEY UPDATE updated_at=NOW()");
$st->bind_param('i',$driver_id);
$st->execute(); $st->close();

echo json_encode(['ok'=>true]);
