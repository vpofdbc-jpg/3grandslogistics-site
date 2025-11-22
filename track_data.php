<?php
// /public_html/track_data.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$fail = function(string $msg, int $code = 400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; };

$orderId = (int)($_GET['order_id'] ?? 0);
$token   = (string)($_GET['token'] ?? '');
if ($orderId<=0 || !preg_match('/^[A-Fa-f0-9]{32}$/',$token)) $fail('bad_params',400);

$hasOdUpdated=false;$hasLat=false;$hasLng=false;
try{ if($r=$conn->query("SHOW COLUMNS FROM order_driver LIKE 'updated_at'")){$hasOdUpdated=$r->num_rows>0;$r->close();} }catch(Throwable $e){}
try{ if($r=$conn->query("SHOW COLUMNS FROM drivers LIKE 'last_lat'")){ $hasLat=$r->num_rows>0;$r->close();} }catch(Throwable $e){}
try{ if($r=$conn->query("SHOW COLUMNS FROM drivers LIKE 'last_lng'")){ $hasLng=$r->num_rows>0;$r->close();} }catch(Throwable $e){}

$latlngSel  = ($hasLat&&$hasLng) ? ", d.last_lat, d.last_lng" : ", NULL AS last_lat, NULL AS last_lng";
$updatedSel = $hasOdUpdated ? "od.updated_at" : "o.created_at";
$orderBy    = $hasOdUpdated ? "ORDER BY od.updated_at DESC" : "ORDER BY o.created_at DESC";

$sql = "
  SELECT o.order_id, o.tracking_token,
         COALESCE(od.status,'Assigned') AS driver_status,
         $updatedSel AS last_update,
         d.id AS driver_id, d.name AS driver_name, d.is_online, d.last_seen
         $latlngSel
  FROM orders o
  LEFT JOIN order_driver od ON od.order_id=o.order_id
  LEFT JOIN drivers d ON d.id=od.driver_id
  WHERE o.order_id=? AND o.tracking_token=?
  $orderBy
  LIMIT 1";
$st=$conn->prepare($sql);
$st->bind_param('is',$orderId,$token);
$st->execute();
$row=$st->get_result()->fetch_assoc();
$st->close();
if(!$row) $fail('not_found',404);

$lat = isset($row['last_lat']) ? (float)$row['last_lat'] : null;
$lng = isset($row['last_lng']) ? (float)$row['last_lng'] : null;
if (!is_finite((float)$lat) || !is_finite((float)$lng) || ($lat==0.0 && $lng==0.0)) { $lat=null; $lng=null; }

echo json_encode([
  'ok'=>true,
  'status'=>(string)($row['driver_status']??'Assigned'),
  'last_update'=>(string)($row['last_update']??''),
  'driver'=>[
    'id'=> isset($row['driver_id'])?(int)$row['driver_id']:null,
    'name'=>(string)($row['driver_name']??'Unassigned'),
    'online'=> (int)($row['is_online']??0)===1,
    'last_seen'=>(string)($row['last_seen']??''),
    'lat'=>$lat,'lng'=>$lng
  ],
]);







