<?php
// /admin/api/message_driver.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$ADMIN_OK = (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role']==='admin') || !empty($_SESSION['is_admin']));
if (!$ADMIN_OK) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!$csrf || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$driver_id = (int)($_POST['driver_id'] ?? 0);
$msg = trim((string)($_POST['message'] ?? ''));
if ($driver_id<=0 || $msg===''){ echo json_encode(['ok'=>false,'error'=>'bad_input']); exit; }

$st = $conn->prepare("INSERT INTO driver_messages (driver_id, sender, message, created_at) VALUES (?, 'admin', ?, NOW())");
$st->bind_param('is', $driver_id, $msg);
$st->execute(); $st->close();

echo json_encode(['ok'=>true]);







