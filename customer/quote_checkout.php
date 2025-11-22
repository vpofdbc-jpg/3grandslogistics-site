<?php
// /customer/quote_checkout.php — turn a saved quote into an order, email confirmation, then go to checkout
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$userId = (int)$_SESSION['user_id'];

/* DB */
$paths=[dirname(__DIR__).'/db.php', $_SERVER['DOCUMENT_ROOT'].'/db.php'];
$ok=false; foreach($paths as $p){ if(is_file($p)){ require $p; $ok=true; break; } }
if(!$ok) die('db.php not found');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

/* Emails */
require_once dirname(__DIR__).'/emails.php'; // uses send_order_confirmation($orderId)

/* helpers */
function meta_set(mysqli $c, int $orderId, string $k, string $v): void {
  try{
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
  }catch(Throwable $e){
    try{ $st=$c->prepare("DELETE FROM order_meta WHERE order_id=? AND meta_key=?");
         $st->bind_param('is',$orderId,$k); $st->execute(); $st->close(); }catch(Throwable $e2){}
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value) VALUES (?,?,?)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
  }
}
function norm_price($v): string {
  if ($v===null) return '';
  $s=preg_replace('~[^0-9.]~','',(string)$v);
  return $s!=='' ? $s : (string)$v;
}

$quoteId = (int)($_GET['quote_id'] ?? 0);
$orderId = (int)($_GET['order_id'] ?? 0);
$newlyCreated = false;

/* If order already exists (draft), just validate ownership */
if ($orderId > 0) {
  $st=$conn->prepare("SELECT order_id FROM orders WHERE order_id=? AND user_id=? LIMIT 1");
  $st->bind_param('ii',$orderId,$userId); $st->execute();
  $ok = (bool)$st->get_result()->fetch_row(); $st->close();
  if (!$ok) { http_response_code(403); exit('Not your quote.'); }
} else {
  if ($quoteId <= 0) { http_response_code(400); exit('Missing quote.'); }

  // Load quote
  $st=$conn->prepare("SELECT id, user_id, pickup_address, delivery_address, package_size, price
                      FROM quotes WHERE id=? LIMIT 1");
  $st->bind_param('i',$quoteId); $st->execute();
  $q=$st->get_result()->fetch_assoc(); $st->close();
  if (!$q || (int)$q['user_id'] !== $userId) { http_response_code(403); exit('Not your quote.'); }

  // Create order from quote
  $st=$conn->prepare("INSERT INTO orders (user_id, pickup_address, delivery_address, package_size, status, created_at)
                      VALUES (?,?,?,?, 'Pending', NOW())");
  $st->bind_param('isss',$userId,$q['pickup_address'],$q['delivery_address'],$q['package_size']);
  $st->execute(); $orderId = (int)$st->insert_id; $st->close();
  $newlyCreated = true;

  // Carry price to meta (common keys)
  if (isset($q['price']) && $q['price']!=='') {
    $price = norm_price($q['price']);
    meta_set($conn,$orderId,'price',$price);
    meta_set($conn,$orderId,'final_cost',$price);
    meta_set($conn,$orderId,'finalCost',$price);
  }

  // Link quote -> order and mark converted (best effort)
  meta_set($conn,$orderId,'quote_id',(string)$quoteId);
  try{
    $conn->query("UPDATE quotes SET status='converted', converted_at=NOW() WHERE id=".(int)$quoteId." LIMIT 1");
    try{ $conn->query("UPDATE quotes SET order_id=".(int)$orderId." WHERE id=".(int)$quoteId." LIMIT 1"); }catch(Throwable $e){}
  }catch(Throwable $e){}
}

/* Ensure order is at least Pending */
$st=$conn->prepare("UPDATE orders SET status='Pending'
                    WHERE order_id=? AND (status IS NULL OR status='' OR status LIKE 'Quote%')");
$st->bind_param('i',$orderId); $st->execute(); $st->close();

/* Email confirmation only when the order was newly created here */
if ($newlyCreated && function_exists('send_order_confirmation')) {
  @send_order_confirmation($orderId);
}

/* Redirect to checkout */
$CHECKOUT = '/customer/checkout.php';
header('Location: '.$CHECKOUT.'?order_id='.$orderId);
exit;

