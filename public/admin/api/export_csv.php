<?php
// /admin/api/export_csv.php
declare(strict_types=1);
session_start();

require __DIR__ . '/../../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$ADMIN_OK = (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role']==='admin') || !empty($_SESSION['is_admin']));
if (!$ADMIN_OK) { http_response_code(403); exit('auth'); }

$csrf = $_GET['csrf'] ?? '';
if (!$csrf || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  http_response_code(403); exit('csrf');
}

$driver_id = (int)($_GET['driver_id'] ?? 0);
$w = $driver_id>0 ? "WHERE dm.driver_id=".$driver_id : "";

$q = "SELECT dm.id, dm.driver_id, COALESCE(d.name, CONCAT('Driver #',dm.driver_id)) AS driver_name,
             dm.sender, dm.message, dm.created_at, dm.seen_by_admin_at, dm.seen_by_driver_at
      FROM driver_messages dm
      LEFT JOIN drivers d ON d.id=dm.driver_id
      $w
      ORDER BY dm.id ASC";
$res = $conn->query($q);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="driver_messages.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','driver_id','driver_name','sender','message','created_at','seen_by_admin_at','seen_by_driver_at']);
while($row=$res->fetch_assoc()){
  fputcsv($out, [$row['id'],$row['driver_id'],$row['driver_name'],$row['sender'],$row['message'],$row['created_at'],$row['seen_by_admin_at'],$row['seen_by_driver_at']]);
}
fclose($out);

