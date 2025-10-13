// /driver/scan.php
declare(strict_types=1);
session_start();// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';t(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

if (empty($_SESSION['driver_id'])) { header('Location:/driver/login.php'); exit; }
$driverId = (int)$_SESSION['driver_id'];

$code = (string)($_GET['code'] ?? $_POST['code'] ?? '');
$phase = (string)($_GET['phase'] ?? $_POST['phase'] ?? 'pickup'); // 'pickup' or 'delivery'
[$orderIdStr, $token] = array_pad(explode('-', $code, 2), 2, '');
$orderId = (int)$orderIdStr;

// fetch order + created_at to recompute token
$st=$conn->prepare("SELECT created_at, driver_id FROM orders WHERE order_id=? LIMIT 1");
$st->bind_param('i',$orderId); $st->execute();
$o=$st->get_result()->fetch_assoc(); $st->close();
if(!$o) exit('bad order');
if((int)$o['driver_id'] !== $driverId) exit('not your order');

$expected = substr(hash_hmac('sha256', $orderId.'|'.$o['created_at'], $_ENV['QR_SECRET'] ?? 'change-me'), 0, 32);
if (!hash_equals($expected, $token)) { http_response_code(403); exit('bad code'); }

// optional GPS
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lon = isset($_POST['lon']) ? (float)$_POST['lon'] : null;

// photo only for delivery (handled like your POD upload)
$photoUrl = null;
if ($phase === 'delivery' && !empty($_FILES['photo']['tmp_name'])) {
  $dir = dirname(__DIR__).'/uploads/pod';
  if (!is_dir($dir)) mkdir($dir,0755,true);
  $ext = 'jpg';
  $name = 'pod_'.$orderId.'_'.time().'.'.$ext;
  move_uploaded_file($_FILES['photo']['tmp_name'], $dir.'/'.$name);
  $photoUrl = '/uploads/pod/'.$name;

  // also store for customer/Admin views
  $k='pod_photo';
  $up=$conn->prepare("INSERT INTO order_meta(order_id,meta_key,meta_value)
                      VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
  $up->bind_param('iss',$orderId,$k,$photoUrl); $up->execute(); $up->close();
}

// write audit event
$ins=$conn->prepare("INSERT INTO order_scan_events(order_id,driver_id,phase,lat,lon,photo_url)
                     VALUES (?,?,?,?,?,?)");
$ins->bind_param('iisdds',$orderId,$driverId,$phase,$lat,$lon,$photoUrl);
$ins->execute(); $ins->close();

// advance status
if ($phase==='pickup') {
  $conn->prepare("UPDATE orders SET status='PickedUp' WHERE order_id=? AND driver_id=?")
       ->bind_param('ii',$orderId,$driverId)->execute();
  $conn->prepare("UPDATE order_driver SET status='PickedUp' WHERE order_id=? AND driver_id=?")
       ->bind_param('ii',$orderId,$driverId)->execute();
} else { // delivery
  $conn->prepare("UPDATE orders SET status='Delivered' WHERE order_id=? AND driver_id=?")
       ->bind_param('ii',$orderId,$driverId)->execute();
  $conn->prepare("UPDATE order_driver SET status='Delivered' WHERE order_id=? AND driver_id=?")
       ->bind_param('ii',$orderId,$driverId)->execute();
}

header('Location:/driver/dashboard.php');
