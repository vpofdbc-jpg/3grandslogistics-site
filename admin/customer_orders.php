<?php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');
// Use the reliable, absolute path with require_once
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

$uid = max(1,(int)($_GET['user_id']??0));

// header info
$u = $conn->prepare("SELECT id, email, username, CONCAT(first_name,' ',last_name) AS name, created_at FROM users WHERE id=?");
$u->bind_param('i',$uid); $u->execute(); $user = $u->get_result()->fetch_assoc();

// totals
$totals = $conn->prepare("
  SELECT 
    COUNT(*)                           AS orders_count,
    SUM(status='Pending')              AS t_pending,
    SUM(status='In Transit')           AS t_transit,
    SUM(status='Delivered')            AS t_delivered
  FROM orders WHERE user_id=?");
$totals->bind_param('i',$uid); $totals->execute(); $T = $totals->get_result()->fetch_assoc();

// orders
$orders = $conn->prepare("
  SELECT order_id, package_size, pickup_address, delivery_address, status, created_at
  FROM orders WHERE user_id=? ORDER BY order_id DESC LIMIT 200");
$orders->bind_param('i',$uid); $orders->execute(); $rows = $orders->get_result()->fetch_all(MYSQLI_ASSOC);
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><meta charset="utf-8"><title>Customer Orders</title>
<link rel="stylesheet" href="data:text/css,body{font-family:Arial,sans-serif;background:#f6f7fb;margin:0} .top{padding:14px 20px;background:#fff;border-bottom:1px solid #e9edf3} .nav a{display:inline-block;margin-right:10px;padding:8px 12px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;font-weight:700} .wrap{max-width:1100px;margin:24px auto;padding:0 16px} .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:16px} table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #eef1f6;text-align:left} th{background:#fafbfe}">
<div class="top"><div class="nav">
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="customers.php">👥 Customers</a>
  <a href="orders.php">📁 Orders</a>
</div></div>

<div class="wrap">
  <div class="card">
    <h3 style="margin:0 0 8px">Customer</h3>
    <div><strong><?=h($user['name']?:$user['username']?:('User #'.$user['id']))?></strong> — <?=h($user['email'])?></div>
    <div style="color:#666">Since <?=h($user['created_at'])?></div>
  </div>

  <div class="card" style="margin-top:12px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
    <div><small>Total Orders</small><div style="font-size:20px;font-weight:800"><?= (int)$T['orders_count']?></div></div>
    <div><small>Pending</small><div style="font-size:20px;font-weight:800"><?= (int)$T['t_pending']?></div></div>
    <div><small>In Transit</small><div style="font-size:20px;font-weight:800"><?= (int)$T['t_transit']?></div></div>
    <div><small>Delivered</small><div style="font-size:20px;font-weight:800"><?= (int)$T['t_delivered']?></div></div>
  </div>

  <div class="card" style="margin-top:12px;">
    <h3 style="margin:0 0 8px">Orders</h3>
    <div style="overflow-x:auto">
      <table>
        <tr><th>#</th><th>Package</th><th>Pickup</th><th>Destination</th><th>Status</th><th>Created</th></tr>
        <?php if(!$rows): ?><tr><td colspan="6">No orders.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= (int)$r['order_id']?></td>
            <td><?= h($r['package_size'])?></td>
            <td><?= h($r['pickup_address'])?></td>
            <td><?= h($r['delivery_address'])?></td>
            <td><?= h($r['status'])?></td>
            <td><?= h($r['created_at'])?></td>
          </tr>
        <?php endforeach; endif;?>
      </table>
    </div>
  </div>
</div>
<!-- Tawk.to (Customers) -->
<div id="tawk-fallback" style="position:fixed;right:16px;bottom:16px;display:none;z-index:999999">
  <a href="https://tawk.to/chat/68be0071f58c911925a74404/1j4j33eos" target="_blank" rel="noopener"
     style="background:#28a745;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;text-decoration:none;">
    Open Chat
  </a>
</div>
<script>
  var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
  Tawk_API.onLoad=function(){
    try{
      Tawk_API.setAttributes({
        role: 'customer',
        customer_id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        name: <?= json_encode($_SESSION['user_name'] ?? 'Guest') ?>,
        email: <?= json_encode($_SESSION['user_email'] ?? '') ?>
      }, function(){});
      if (Tawk_API.addTags) Tawk_API.addTags(['customer']);
    }catch(e){}
  };
  (function(){
    var s1=document.createElement("script"), s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/68be0071f58c911925a74404/1j4j33eos';
    s1.charset='UTF-8'; s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
  })();
  // show fallback button if widget is blocked
  setTimeout(function(){
    if(!window.Tawk_API || !document.getElementById('tawkchat-container')){
      var fb=document.getElementById('tawk-fallback'); if(fb) fb.style.display='block';
    }
  }, 6000);
</script>

