<?php
// /admin/driver_messages_feed.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$driverId = (int)($_GET['driver_id'] ?? 0);
if ($driverId <= 0) { echo json_encode(['ok'=>false,'error'=>'bad_driver']); exit; }

try {
  $st = $conn->prepare("SELECT id, created_at, sender, message
                        FROM driver_messages
                        WHERE driver_id=? ORDER BY id ASC LIMIT 500");
  $st->bind_param('i', $driverId);
  $st->execute();
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();
  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'error'=>'db']); 
}



