<?php
// /admin/accept_package_request.php — accept pickup/delivery preference; creates order for delivery
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('POST only'); }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

$pkgId  = (int)($_POST['package_id'] ?? 0);
$action = strtolower(trim((string)($_POST['action'] ?? ''))); // 'pickup'|'delivery'

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
$conn->set_charset('utf8mb4');

try{
  /* load package + user */
  $st=$conn->prepare("SELECT p.id,p.user_id,p.tracking,p.status,COALESCE(p.location,'') location
                      FROM packages p WHERE p.id=? LIMIT 1");
  $st->bind_param('i',$pkgId); $st->execute();
  $pkg=$st->get_result()->fetch_assoc(); $st->close();
  if(!$pkg) throw new RuntimeException('Package not found');

  if ($action==='pickup') {
    $sets=["status='Ready for Pickup'"];
    // optional timestamps if present
    $cols=[]; $r=$conn->query("SHOW COLUMNS FROM packages"); while($c=$r->fetch_assoc()) $cols[$c['Field']]=1; $r->close();
    if(!empty($cols['ready_for_pickup_at'])) $sets[]="ready_for_pickup_at=NOW()";
    if(!empty($cols['updated_at']))          $sets[]="updated_at=NOW()";

    $sql="UPDATE packages SET ".implode(',',$sets)." WHERE id=? LIMIT 1";
    $u=$conn->prepare($sql); $u->bind_param('i',$pkgId); $u->execute(); $u->close();

    header('Location: /admin/dashboard.php?ok=pickup_ok'); exit;
  }

  if ($action==='delivery') {
    /* build addresses */
    $facility = 'Our Facility';
    try{
      $res=$conn->query("SELECT value FROM settings WHERE `key` IN ('facility_address','warehouse_address') LIMIT 1");
      if($row=$res->fetch_assoc()) $facility=(string)$row['value'];
      if(isset($res)) $res->close();
    }catch(Throwable $e){ /* fallback to default */ }

    $uid=(int)$pkg['user_id'];
    $addr='';
    $st=$conn->prepare("SELECT COALESCE(CONCAT_WS(' ',street,city,state,zipcode), address, '') AS full FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i',$uid); $st->execute();
    if($u=$st->get_result()->fetch_assoc()) $addr=(string)$u['full'];
    $st->close();

    if ($addr==='') $addr='Customer Address (missing)';

    /* create order (minimal) */
    $packageSize = 'Small';
    $st=$conn->prepare("INSERT INTO orders (user_id,pickup_address,delivery_address,package_size,status,created_at)
                        VALUES (?,?,?,?, 'Pending', NOW())");
    $st->bind_param('isss',$uid,$facility,$addr,$packageSize);
    $st->execute(); $orderId = (int)$st->insert_id; $st->close();

    /* meta: link back to package */
    try{
      $m=$conn->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value) VALUES
                        (?,?,?),(?,?,?)");
      $k1='package_id'; $v1=(string)$pkgId;
      $k2='tracking';   $v2=(string)$pkg['tracking'];
      $m->bind_param('ississ',$orderId,$k1,$v1,$orderId,$k2,$v2);
      $m->execute(); $m->close();
    }catch(Throwable $e){}

    /* update package status */
    $sets=["status='Delivery Scheduled'"];
    $cols=[]; $r=$conn->query("SHOW COLUMNS FROM packages"); while($c=$r->fetch_assoc()) $cols[$c['Field']]=1; $r->close();
    if(!empty($cols['updated_at'])) $sets[]="updated_at=NOW()";
    $sql="UPDATE packages SET ".implode(',',$sets)." WHERE id=? LIMIT 1";
    $u=$conn->prepare($sql); $u->bind_param('i',$pkgId); $u->execute(); $u->close();

    // If you have /admin/order_edit.php, redirect there instead:
    $edit = is_file($_SERVER['DOCUMENT_ROOT'].'/admin/order_edit.php')
      ? '/admin/order_edit.php?id='.$orderId
      : '/admin/dashboard.php?ok=delivery_ok';
    header('Location: '.$edit); exit;
  }

  throw new RuntimeException('Bad action');
}catch(Throwable $e){
  error_log('accept_package_request: '.$e->getMessage());
  header('Location: /admin/dashboard.php?ok=err'); exit;
}
