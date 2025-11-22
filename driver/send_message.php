<?php
// /driver/admin_send_message.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['ok'=>false,'error'=>'method']); exit; }
if (empty($_SESSION['csrf']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$driverId = (int)($_POST['driver_id'] ?? 0);
$msg      = trim((string)($_POST['message'] ?? ''));
if ($driverId <= 0 || $msg === '') { echo json_encode(['ok'=>false,'error'=>'bad_input']); exit; }
if (mb_strlen($msg) > 1000) { $msg = mb_substr($msg, 0, 1000); }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

try {
  $st = $conn->prepare("INSERT INTO driver_messages (driver_id, sender, message, created_at)
                        VALUES (?, 'admin', ?, NOW())");
  $st->bind_param('is', $driverId, $msg);
  $st->execute();
  $st->close();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'db']);
}




