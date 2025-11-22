<?php
declare(strict_types=1);
session_start();
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

$oid = (int)($_GET['order_id'] ?? 0);
if ($oid<=0) { http_response_code(400); exit('Bad order'); }

$stmt = $conn->prepare("SELECT o.order_id, o.scan_token, o.pickup_address, o.delivery_address
                        FROM orders o WHERE o.order_id=? LIMIT 1");
$stmt->bind_param('i',$oid);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
if(!$o){ http_response_code(404); exit('Order not found'); }

$scanBase = 'https://3grandslogistics.com/scan.php';
$qrUrl    = $scanBase.'?oid='.$o['order_id'].'&t='.$o['scan_token'];
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!doctype html>
<meta charset="utf-8">
<title>Label • Order #<?= (int)$o['order_id']?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif}
  .label{width:4in;height:6in;border:1px solid #000;padding:.25in;position:relative}
  .row{margin:6px 0}
  .qr{position:absolute;right:.25in;bottom:.25in;text-align:center}
  .big{font-weight:800;font-size:20px}
  .small{font-size:12px;color:#333}
  .box{border:1px dashed #888;padding:8px;border-radius:6px}
  .hint{font-size:12px;color:#666;margin-top:6px}
  @media print {.print-hide{display:none}}
</style>
<div class="label">
  <div class="row big">3 Grands Logistics</div>
  <div class="row">Order # <strong><?= (int)$o['order_id']?></strong></div>

  <div class="row box"><strong>Pickup:</strong><br><?= h($o['pickup_address'] ?? '—') ?></div>
  <div class="row box"><strong>Deliver To:</strong><br><?= h($o['delivery_address'] ?? '—') ?></div>

  <div class="qr">
    <!-- Zero-dependency QR image using qrserver.com -->
    <img src="<?= 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($qrUrl) ?>"
         width="180" height="180" alt="QR">
    <div class="small">Scan to update status</div>
  </div>

  <div class="hint print-hide">
    <p><a href="<?=h($qrUrl)?>" target="_blank">Test scan link</a></p>
    <button onclick="window.print()">Print Label</button>
  </div>
</div>
