<?php
// /customer/order_cancel.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($mysqli) && $mysqli instanceof mysqli && !isset($conn)) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

function order_is_cancellable(array $o): bool {
  $status      = (string)($o['status'] ?? 'Pending');
  $scan_office = (string)($o['scan_office'] ?? '');
  $scan_pickup = (string)($o['scan_pickup'] ?? '');
  if ($scan_office !== '' || $scan_pickup !== '') return false;
  return in_array($status, ['Pending','Assigned','Accepted'], true);
}

if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
  http_response_code(400); exit('Bad CSRF');
}

$userId  = (int)$_SESSION['user_id'];
$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); exit('Bad order id'); }

/* Load order (must belong to this customer) + scan meta for rule */
$st = $conn->prepare("
  SELECT o.order_id, o.user_id, o.status,
         (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key IN('label_checked_out_at','scan_office')) AS scan_office,
         (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key='scan_pickup') AS scan_pickup
  FROM orders o WHERE o.order_id=? AND o.user_id=? LIMIT 1");
$st->bind_param('ii',$orderId,$userId); $st->execute();
$o = $st->get_result()->fetch_assoc(); $st->close();
if (!$o) { http_response_code(404); exit('Not found'); }

if (!order_is_cancellable($o)) {
  header('Location: /customer/dashboard.php#recent-orders'); exit;
}

/* Cancel: set orders.status, mark metadata, (optionally) reflect in order_driver */
$st = $conn->prepare("UPDATE orders SET status='Cancelled' WHERE order_id=? LIMIT 1");
$st->bind_param('i',$orderId); $st->execute(); $st->close();

/* make it obvious everywhere */
$conn->query("UPDATE order_driver SET status='Cancelled', updated_at=NOW() WHERE order_id=".$orderId);

/* meta trail */
$meta = $conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
$key='cancelled_by'; $val='customer';  $meta->bind_param('iss',$orderId,$key,$val); $meta->execute();
$key='cancelled_at'; $val=date('Y-m-d H:i:s'); $meta->bind_param('iss',$orderId,$key,$val); $meta->execute();
$meta->close();

header('Location: /customer/dashboard.php#recent-orders');
