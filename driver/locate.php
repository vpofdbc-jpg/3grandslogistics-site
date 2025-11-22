<?php
// /driver/locate.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['driver_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'auth']); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
if ($lat===null || $lng===null || $lat<-90 || $lat>90 || $lng<-180 || $lng>180) {
  http_response_code(400); echo json_encode(['ok'=>false,'err'=>'bad coords']); exit;
}

$driverId = (int)$_SESSION['driver_id'];

$st=$conn->prepare("UPDATE drivers SET last_lat=?, last_lng=?, last_seen=NOW(), is_online=1 WHERE id=?");
$st->bind_param('ddi', $lat, $lng, $driverId);
$st->execute(); $st->close();

echo json_encode(['ok'=>true]);
