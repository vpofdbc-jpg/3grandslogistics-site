<?php
// /admin/api/drivers_list.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$ADMIN_OK = !empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role']==='admin') || !empty($_SESSION['is_admin']);
if (!$ADMIN_OK) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

try {
  $rows = $conn->query("SELECT id, name, email FROM drivers ORDER BY name ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
  echo json_encode(['ok'=>true,'rows'=>$rows]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'rows'=>[]]);
}

