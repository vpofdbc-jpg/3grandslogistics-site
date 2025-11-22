<?php
// /api/order_live.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','1'); error_reporting(E_ALL);

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId<=0) { http_response_code(400); echo json_encode(['ok'=>false,'err'=>'bad order']); exit; }

/* auth: admin or the owner user */
$ADMIN = !empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role']==='admin') || !empty($_SESSION['is_admin']);
$USER  = (int)($_SESSION['user_id'] ?? 0);

$st=$conn->prepare("SELECT user_id, pickup_address, delivery_address FROM orders WHERE order_id=? LIMIT 1");
$st->bind_param('i',$orderId); $st->execute();
$o=$st->get_result()->fetch_assoc(); $st->close();
if(!$o){ http_response_code(404); echo json_encode(['ok'=>false,'err'=>'no order']); exit; }

if(!$ADMIN && (!$USER || (int)$o['user_id']!==$USER)) { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'forbidden']); exit; }

/* driver assignment + live coords */
$st=$conn->prepare("
  SELECT od.status AS driver_status, d.last_lat, d.last_lng, d.last_seen, d.name AS driver_name
  FROM order_driver od
  JOIN drivers d ON d.id=od.driver_id
  WHERE od.order_id=?
  ORDER BY od.updated_at DESC
  LIMIT 1");
$st->bind_param('i',$orderId); $st->execute();
$d=$st->get_result()->fetch_assoc(); $st->close();

echo json_encode([
  'ok'=>true,
  'order_id'=>$orderId,
  'pickup'=>$o['pickup_address'],
  'dropoff'=>$o['delivery_address'],
  'driver'=>[
    'name'=> $d['driver_name'] ?? null,
    'status'=> $d['driver_status'] ?? null,
    'lat'=> isset($d['last_lat']) ? (float)$d['last_lat'] : null,
    'lng'=> isset($d['last_lng']) ? (float)$d['last_lng'] : null,
    'seen'=> $d['last_seen'] ?? null
  ]
]);
