<?php
// public_html/driver/ping.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['driver_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
if (!$lat || !$lng) { echo json_encode(['ok'=>false,'error'=>'bad_coords']); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$driverId = (int)$_SESSION['driver_id'];
$st = $conn->prepare("UPDATE drivers SET last_lat=?, last_lng=?, last_seen=NOW() WHERE id=?");
$st->bind_param('ddi', $lat, $lng, $driverId);
$st->execute(); $st->close();

echo json_encode(['ok'=>true]);


