<?php
// /admin/new_order.php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

if (empty($_SESSION['csrf_admin'])) { $_SESSION['csrf_admin'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_admin'];

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($_SESSION['csrf_admin'], $_POST['csrf'] ?? '')) { http_response_code(403); exit('Bad CSRF'); }
  $user_id = (int)($_POST['user_id'] ?? 0);
  $pickup = trim($_POST['pickup'] ?? '');
  $delivery = trim($_POST['delivery'] ?? '');
  $package = trim($_POST['package_size'] ?? 'Small');
  $miles = (float)($_POST['miles'] ?? 0);
  $vehicle = trim($_POST['vehicle'] ?? 'Car');
  $price = (float)($_POST['price'] ?? 0);
  $notes = trim($_POST['notes'] ?? '');

  if ($user_id>0 && $pickup!=='' && $delivery!=='') {
    $stmt = $conn->prepare("INSERT INTO orders (user_id, package_size, pickup_address, delivery_address, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
    $stmt->bind_param('isss', $user_id, $package, $pickup, $delivery);
    $stmt->execute();
    $oid = $conn->insert_id;

    // store extras in order_meta (if you created it earlier)
    if ($m = $conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES
      (?, 'vehicle', ?), (?, 'miles', ?), (?, 'price', ?), (?, 'admin_note', ?)")) {
      $miles_s = (string)$miles;
      $price_s = (string)$price;
      $m->bind_param('isisssss', $oid, $vehicle, $oid, $miles_s, $oid, $price_s, $oid, $notes);
      $m->execute();
    }

    header("Location: orders.php?made=$oid"); exit;
  } else {
    $msg = 'Please fill the required fields.';
  }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin • New Order</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;margin:0}
 .top{padding:14px 20px;background:#fff;border-bottom:1px solid #e9edf3}
 .nav a{display:inline-block;margin-right:10px;padding:8px 12px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;font-weight:700}
 .wrap{max-width:860px;margin:24px auto;padding:0 16px}
 .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:18px}
 label{font-weight:700}
 input,select,textarea{width:100%;padding:10px;border:1px solid #dfe3ea;border-radius:8px;margin:6px 0 14px}
 button{padding:12px 16px;border:none;border-radius:8px;background:#198754;color:#fff;font-weight:700;cursor:pointer}
 .msg{background:#fff3cd;border:1px solid #ffe69c;padding:10px;border-radius:8px;margin-bottom:12px}
</style>
</head><body>
  <div class="top">
    <div class="nav">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="orders.php">📁 Orders</a>
      <a href="customers.php">👥 Customers</a>
      <a href="change_password.php">🔑 Change Password</a>
      <a href="logout.php" style="background:#6c757d">🚪 Logout</a>
    </div>
  </div>

  <div class="wrap">
    <div class="card">
      <h3>Place Order for Customer</h3>
      <?php if ($msg): ?><div class="msg"><?=h($msg)?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <label>Customer (User ID)</label>
        <input type="number" name="user_id" value="<?=$user_id?:''?>" placeholder="e.g., 6" required>

        <label>Pickup Address</label>
        <input name="pickup" placeholder="123 Main St, City, ST" required>

        <label>Destination Address</label>
        <input name="delivery" placeholder="456 Market St, City, ST" required>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>Package Size</label>
            <select name="package_size">
              <option>Small</option><option>Medium</option><option>Large</option><option>Oversized</option>
            </select>
          </div>
          <div>
            <label>Vehicle</label>
            <select name="vehicle">
              <option>Car</option><option>Van</option><option>Truck</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>Miles (optional)</label>
            <input type="number" step="0.1" name="miles" placeholder="e.g., 22.5">
          </div>
          <div>
            <label>Price (optional)</label>
            <input type="number" step="0.01" name="price" placeholder="e.g., 74.80">
          </div>
        </div>

        <label>Notes (optional)</label>
        <textarea name="notes" placeholder="Special instructions"></textarea>

        <button type="submit">Create Order</button>
        <a href="customers.php" style="margin-left:10px">Cancel</a>
      </form>
    </div>
  </div>
</body></html>
