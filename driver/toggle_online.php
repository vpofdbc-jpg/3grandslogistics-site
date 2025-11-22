<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['driver_id'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  echo json_encode(['ok'=>false,'error'=>'csrf']); exit;
}

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$state    = (isset($_POST['state']) && $_POST['state']=='1') ? 1 : 0;
$driverId = (int)$_SESSION['driver_id'];

/* Try to set both last_online and last_seen if the columns exist */
$cols=[]; $res=$conn->query("SHOW COLUMNS FROM drivers"); while($c=$res->fetch_assoc()) $cols[$c['Field']]=true; $res->close();
$hasLastSeen   = !empty($cols['last_seen']);
$hasLastOnline = !empty($cols['last_online']);

if ($hasLastSeen && $hasLastOnline) {
  $st=$conn->prepare("UPDATE drivers SET is_online=?, last_online=NOW(), last_seen=NOW() WHERE id=?");
} elseif ($hasLastOnline) {
  $st=$conn->prepare("UPDATE drivers SET is_online=?, last_online=NOW() WHERE id=?");
} elseif ($hasLastSeen) {
  $st=$conn->prepare("UPDATE drivers SET is_online=?, last_seen=NOW() WHERE id=?");
} else {
  $st=$conn->prepare("UPDATE drivers SET is_online=? WHERE id=?");
}
$st->bind_param('ii',$state,$driverId); $st->execute(); $st->close();

echo json_encode(['ok'=>true,'is_online'=>$state]);


