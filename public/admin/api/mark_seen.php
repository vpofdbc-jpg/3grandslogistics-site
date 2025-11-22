<?php
// /driver/mark_seen.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['driver_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$driver_id = (int)$_SESSION['driver_id'];
$st = $conn->prepare("UPDATE driver_messages SET seen_by_driver_at=NOW() WHERE driver_id=? AND sender='admin' AND seen_by_driver_at IS NULL");
$st->bind_param('i',$driver_id); $st->execute(); $st->close();

echo json_encode(['ok'=>true]);
