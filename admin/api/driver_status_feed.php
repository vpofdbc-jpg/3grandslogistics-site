<?php
// /admin/api/driver_status_feed.php
declare(strict_types=1);
session_start();
require __DIR__ . '/../bootstrap.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// simple admin gate
if (empty($_SESSION['admin_id'])) { http_response_code(403); exit; }

$since = trim($_GET['since'] ?? '');
$params = []; $types=''; $where = '';
if ($since !== '') { $where = 'WHERE od.updated_at > ? OR od.pod_time > ?'; $params = [$since,$since]; $types='ss'; }

$sql = "
  SELECT
    od.order_id,
    od.status        AS driver_status,
    COALESCE(od.pod_photo,'') AS pod_photo,
    DATE_FORMAT(GREATEST(COALESCE(od.updated_at,'1970-01-01'), COALESCE(od.pod_time,'1970-01-01')), '%Y-%m-%d %H:%i:%s') AS changed_at
  FROM order_driver od
  $where
  ORDER BY changed_at ASC
  LIMIT 500
";
$st = $conn->prepare($sql);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'rows'=>$rows]);
