<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id']))     { header('Location:/login.php');   exit; }
if (empty($_SESSION['saw_welcome'])) { header('Location:/welcome.php'); exit; }

$name   = $_SESSION['name'] ?? 'Customer';
$userId = (int)($_SESSION['user_id'] ?? 0);

/* --- Recent orders (safe) --- */
$orders = [];
try {
  // NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
  if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
  if (isset($conn) && $conn instanceof mysqli) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $sql = "
      SELECT
        o.order_id, o.status AS order_status, o.created_at, o.package_size,
        o.pickup_address, o.delivery_address,
        od.status AS driver_status, od.pod_photo
      FROM orders o
      LEFT JOIN order_driver od ON od.order_id = o.order_id
      WHERE o.user_id = ?
      ORDER BY o.created_at DESC
      LIMIT 10
    ";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $userId);
    $st->execute();
    $orders = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
  }
} catch (Throwable $e) {
  // Swallow errors: keep homepage working even if DB/query breaks.
  $orders = [];
}

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function driver_badge($s){
  $s = trim((string)$s);
  $class = 'badge ';
  if     ($s==='Accepted')   $class.='b-assigned';
  elseif ($s==='PickedUp')   $class.='b-picked';
  elseif ($s==='In Transit') $class.='b-transit';
  elseif ($s==='Delivered')  $class.='b-done';
  elseif ($s==='Cancelled')  $class.='b-cancel';
  else                       $class.='b-assigned';
  return '<span class="'.$class.'">'.h($s !== '' ? $s : 'Assigned').'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
.top{background:#0d6efd;color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
.wrap{max-width:1100px;margin:22px auto;padding:0 14px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:18px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;font-weight:700;text-decoration:none;color:#fff;background:#0d6efd}
.btn.green{background:#198754}.btn.orange{background:#ff7a00}
.small{color:#555}

h2{margin:22px 0 12px}
.tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e9edf3;border-radius:12px;overflow:hidden}
.tbl th,.tbl td{padding:10px;border-bottom:1px solid #e9edf3;text-align:left;vertical-align:top}
.tbl th{background:#f8fafc}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700}
.b-assigned{background:#e7f1ff;color:#0a58ca}
.b-picked{background:#fff3cd;color:#7a5e00}
.b-transit{background:#cff4fc;color:#055160}
.b-done{background:#d1e7dd;color:#0f5132}
.b-cancel{background:#f8d7da;color:#842029}
</style>
</head>
<body>
  <div class="top">
    <div>Welcome, <strong><?= h($name) ?></strong></div>
    <div><a href="/logout.php" style="color:#fff;text-decoration:none;font-weight:700">Logout</a></div>
  </div>

  <div class="wrap">
    <div class="grid">
      <div class="card">
        <h3>Schedule a Pickup</h3>
        <p class="small">Create a new order in seconds.</p>
        <a class="btn green" href="/schedule_pickup.php">➕ New Pickup</a>
      </div>
      <div class="card">
        <h3>Track Shipment</h3>
        <p class="small">See live driver status & POD.</p>
        <a class="btn" href="/customer/dashboard.php#recent-orders">Recent Orders</a>
</div>
      <div class="card">
        <h3>Edit Profile</h3>
        <p class="small">Update your name, email, and password.</p>
        <a class="btn orange" href="/profile.php">✏️ Edit Profile</a>
      </div>
      <div class="card">
        <h3>Support</h3>
        <p class="small">Questions? We’re here to help.</p>
        <a class="btn" href="/support.php">✉️ Email Support</a>
      </div>
    </div>

    <h2>Recent Orders</h2>
    <table class="tbl">
      <tr>
        <th>Order #</th>
        <th>Pickup</th>
        <th>Destination</th>
        <th>Package</th>
        <th>Created</th>
        <th>Status</th>
        <th>POD</th>
      </tr>
      <?php if(!$orders): ?>
        <tr><td colspan="7" style="text-align:center;color:#666">No orders yet.</td></tr>
      <?php else: foreach($orders as $o): ?>
        <tr>
          <td>#<?= (int)$o['order_id'] ?></td>
          <td><?= h($o['pickup_address']) ?></td>
          <td><?= h($o['delivery_address']) ?></td>
          <td><?= h($o['package_size']) ?></td>
          <td><?= h($o['created_at'] ? date('Y-m-d H:i', strtotime($o['created_at'])) : '') ?></td>
          <td>
            <?= driver_badge($o['driver_status']) ?>
            <div class="small">Order: <?= h($o['order_status']) ?></div>
          </td>
          <td>
            <?php if(!empty($o['pod_photo']) && filter_var($o['pod_photo'], FILTER_VALIDATE_URL)): ?>
              <a href="<?= h($o['pod_photo']) ?>" target="_blank" rel="noopener">View POD</a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
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

</body>
</html>

