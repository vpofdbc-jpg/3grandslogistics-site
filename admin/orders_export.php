<?php
// /admin/orders_export.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---- read filters (same as orders.php) ----
$allowedStatus = ['All','Pending','In Transit','Delivered','Cancelled'];
$status  = isset($_GET['status']) && in_array($_GET['status'],$allowedStatus,true) ? $_GET['status'] : 'All';
$q       = trim($_GET['q'] ?? '');
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');

$where = [];
$params = [];
$types  = '';

if ($status !== 'All') { $where[] = 'o.status = ?'; $params[] = $status; $types .= 's'; }
if ($from !== '')      { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $from; $types .= 's'; }
if ($to !== '')        { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $to;   $types .= 's'; }

if ($q !== '') {
  $where[] = '('
        .'u.email LIKE CONCAT("%",?,"%")'
        .' OR u.username LIKE CONCAT("%",?,"%")'
        .' OR u.first_name LIKE CONCAT("%",?,"%")'
        .' OR u.last_name  LIKE CONCAT("%",?,"%")'
        .' OR o.order_id = ?'
        .' OR o.user_id  = ?'
        .')';

  $params[] = $q; // email
  $params[] = $q; // username
  $params[] = $q; // first_name
  $params[] = $q; // last_name
  $types   .= 'ssss';

  $num = (int)$q;
  $params[] = $num; // order_id
  $params[] = $num; // user_id
  $types   .= 'ii';
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// ---- query rows ----
$sql = "SELECT 
          o.order_id,
          o.user_id,
          CONCAT(u.first_name,' ',u.last_name) AS name,
          u.username,
          u.email,
          o.package_size,
          o.pickup_address,
          o.delivery_address,
          o.status,
          o.created_at
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        $whereSql
        ORDER BY o.order_id DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// ---- CSV headers ----
$filename = 'orders-'.date('Ymd-His').'.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// optional BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, [
  'Order #','User ID','Name','Username','Email',
  'Package','Pickup','Destination','Status','Created'
]);

while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    (int)$row['order_id'],
    (int)$row['user_id'],
    (string)$row['name'],
    (string)$row['username'],
    (string)$row['email'],
    (string)$row['package_size'],
    (string)$row['pickup_address'],
    (string)$row['delivery_address'],
    (string)$row['status'],
    (string)$row['created_at'],
  ]);
}

fclose($out);
exit;

