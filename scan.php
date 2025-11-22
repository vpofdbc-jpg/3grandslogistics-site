<?php
declare(strict_types=1);
session_start();
error_reporting(E_ALL); ini_set('display_errors','1');

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$oid = (int)($_GET['oid'] ?? 0);
$tok = $_GET['t'] ?? '';
if ($oid<=0 || $tok==='') { http_response_code(400); exit('Invalid link'); }

// verify token belongs to order
$stmt = $conn->prepare("SELECT order_id, status, scan_token FROM orders WHERE order_id=? LIMIT 1");
$stmt->bind_param('i',$oid);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
if(!$o || !hash_equals($o['scan_token'] ?? '', $tok)){
  http_response_code(403); exit('Invalid or expired QR link');
}

// if a POST happens, update status
$updated = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $next = $_POST['status'] ?? '';
  $allowed = ['Pickup'=>'In Transit','In Transit'=>'Delivered','Delivered'=>'Delivered']; // guard rails
  // free-form choices allowed too:
  if (in_array($next, ['Pickup','In Transit','Delivered'], true)) {
    // translate "Pickup" to "In Transit" if you prefer Pickup as an event log
    $newStatus = ($next==='Pickup') ? 'In Transit' : $next;

    $conn->begin_transaction();
    try{
      $u = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
      $u->bind_param('si', $newStatus, $oid);
      $u->execute(); $u->close();

      $e = $conn->prepare("INSERT INTO order_events (order_id, event, meta) VALUES (?,?,?)");
      $meta = 'scanned via QR';
      $e->bind_param('iss', $oid, $next, $meta);
      $e->execute(); $e->close();

      $conn->commit();
      $updated = $newStatus;
    }catch(Throwable $ex){
      $conn->rollback();
      http_response_code(500);
      exit('Update failed');
    }
  }
}
$current = $updated ?: ($o['status'] ?? 'Pending');
?>
<!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scan • Order #<?= (int)$oid ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;margin:0;padding:16px}
  .card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:18px;
        box-shadow:0 4px 12px rgba(0,0,0,.06)}
  h1{margin:0 0 8px}
  .status{margin:8px 0 16px;font-weight:800}
  form{display:flex;gap:8px;flex-wrap:wrap}
  button{padding:10px 12px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
  .b1{background:#0d6efd;color:#fff}
  .b2{background:#0dcaf0}
  .b3{background:#198754;color:#fff}
  .note{margin-top:12px;color:#666}
</style>
<div class="card">
  <h1>Order #<?= (int)$oid ?></h1>
  <div class="status">Current Status: <?= h($current) ?></div>

  <form method="post">
    <input type="hidden" name="token" value="<?= h($tok) ?>">
    <button class="b1" name="status" value="Pickup">Scan: Pickup</button>
    <button class="b2" name="status" value="In Transit">Scan: In Transit</button>
    <button class="b3" name="status" value="Delivered">Scan: Delivered</button>
  </form>

  <div class="note">Driver: tap the appropriate button after scanning.</div>
</div>
