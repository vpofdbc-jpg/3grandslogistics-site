<?php
// /tools/make_label.php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);             // turn off after you confirm
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// DB
require dirname(__DIR__).'/db.php';
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

$orderId = max(1,(int)($_GET['order_id'] ?? 0));
if ($orderId <= 0) { http_response_code(400); exit('Missing order_id'); }

// detect columns we have in orders
$cols=[]; $r=$conn->query("SHOW COLUMNS FROM orders"); while($c=$r->fetch_assoc()) $cols[$c['Field']]=1; $r->close();
$pickupCol = isset($cols['pickup_address']) ? 'pickup_address' : (isset($cols['pickup']) ? 'pickup' : 'pickup_address');
$dropCol   = isset($cols['delivery_address']) ? 'delivery_address' : (isset($cols['destination']) ? 'destination' : 'delivery_address');

// fetch order
$st=$conn->prepare("SELECT $pickupCol AS pickup, $dropCol AS dropoff, created_at FROM orders WHERE order_id=? LIMIT 1");
$st->bind_param('i',$orderId); $st->execute(); $order=$st->get_result()->fetch_assoc(); $st->close();
if (!$order) { http_response_code(404); exit('Order not found'); }

// get/create label_code in order_meta
$code='';
$st=$conn->prepare("SELECT meta_value FROM order_meta WHERE order_id=? AND meta_key='label_code' LIMIT 1");
$st->bind_param('i',$orderId); $st->execute(); if($m=$st->get_result()->fetch_assoc()) $code=$m['meta_value']; $st->close();
if ($code==='') {
  $code = $orderId.'-'.bin2hex(random_bytes(12));
  $st=$conn->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value)
                      VALUES(?, 'label_code', ?)
                      ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
  $st->bind_param('is',$orderId,$code); $st->execute(); $st->close();
}

// build scan URL + QR providers
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https://' : 'http://';
$scanUrl = $scheme.$_SERVER['HTTP_HOST'].'/driver/scan_label.php?code='.rawurlencode($code);
$qr1 = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.rawurlencode($scanUrl);
$qr2 = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chld=L|0&chl='.rawurlencode($scanUrl);
?>
<!doctype html>
<meta charset="utf-8">
<title>Label #<?= (int)$orderId ?></title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#fff;margin:24px}
.box{border:1px solid #ddd;border-radius:8px;padding:12px;margin:10px 0;max-width:520px}
.row{display:flex;gap:16px;align-items:flex-start}
.brand{font-weight:800;font-size:20px}
.qr img{border:1px solid #ddd;border-radius:6px}
.print{margin-top:12px}
.small{color:#666;font-size:12px}
</style>

<div class="brand">3 Grands Logistics</div>
<div class="box">
  <div>Label for Order <strong>#<?= (int)$orderId ?></strong></div>
  <div class="small"><?= h($order['created_at'] ?? date('Y-m-d H:i:s')) ?></div>

  <div class="row" style="margin-top:10px">
    <div class="qr">
      <img src="<?= h($qr1) ?>" alt="QR" width="180" height="180"
           onerror="this.onerror=null;this.src='<?= h($qr2) ?>'">
    </div>
    <div style="min-width:300px">
      <div class="box"><div class="small">From</div><div><?= h($order['pickup'] ?? '') ?></div></div>
      <div class="box"><div class="small">To</div><div><?= h($order['dropoff'] ?? '') ?></div></div>
      <div class="box">
        <div class="small">Scan Code</div>
        <div style="word-break:break-all"><strong><?= h($code) ?></strong></div>
        <div class="small" style="margin-top:6px"><?= h($scanUrl) ?></div>
      </div>
    </div>
  </div>

  <button class="print" onclick="window.print()">Print</button>
</div>
