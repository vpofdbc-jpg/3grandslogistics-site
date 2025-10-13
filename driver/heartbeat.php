<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['driver_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$driverId = (int)$_SESSION['driver_id'];
$conn->query("UPDATE drivers SET last_seen=NOW() WHERE id=".$driverId);
echo json_encode(['ok'=>true]);
