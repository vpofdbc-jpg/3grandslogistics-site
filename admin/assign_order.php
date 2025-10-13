<?php
// /admin/assign_order.php
declare(strict_types=1);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf'];

// Load order list (show most recent 200)
$orders = $conn->query("
  SELECT o.order_id,
         CONCAT('#',o.order_id,' — ', o.pickup_address, ' ➜ ', o.delivery_address) AS label
  FROM orders o
  ORDER BY o.order_id DESC
  LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

// Load drivers
$drivers = $conn->query("
  SELECT id, CONCAT(name,' (',email,')') AS label
  FROM drivers
  ORDER BY name
")->fetch_all(MYSQLI_ASSOC);

// Default selection via query string
$selected_order = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (int)($_GET['order'] ?? 0);

// Handle POST (assign)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    exit('Bad CSRF');
  }
  $order_id  = (int)($_POST['order_id'] ?? 0);
  $driver_id = (int)($_POST['driver_id'] ?? 0);
  if ($order_id && $driver_id) {
    // upsert into order_driver
    $stmt = $conn->prepare("
      INSERT INTO order_driver (order_id, driver_id, status, assigned_at)
      VALUES (?, ?, 'Assigned', NOW())
      ON DUPLICATE KEY UPDATE driver_id = VALUES(driver_id), status='Assigned', assigned_at=NOW()
    ");
    $stmt->bind_param('ii', $order_id, $driver_id);
    $stmt->execute();
    $stmt->close();

    // optional: set order status to Pending if empty
    $conn->query("UPDATE orders SET status = IF(status IN ('','Pending') OR status IS NULL,'Pending',status) WHERE order_id = ".(int)$order_id);

    header('Location: /admin/orders.php?msg=assigned');
    exit;
  }
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<meta charset="utf-8">
<title>Assign Order to Driver</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
 body{font-family:Arial,Helvetica,sans-serif;background:#f4f5fb;margin:0}
 .wrap{max-width:720px;margin:30px auto;background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:16px}
 .btn{background:#0d6efd;color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}
 select{width:100%;padding:10px;border:1px solid #dfe3ea;border-radius:8px}
 .row{margin:12px 0}
 a{color:#0d6efd;text-decoration:none}
</style>

<div class="wrap">
  <div style="margin-bottom:10px;"><a href="/admin/orders.php">← Back to Orders</a></div>
  <h2>Assign Order to Driver</h2>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <div class="row">
      <label><strong>Order</strong></label><br>
      <select name="order_id" required>
        <?php foreach($orders as $o): ?>
          <option value="<?= (int)$o['order_id'] ?>" <?= $selected_order===(int)$o['order_id']?'selected':'' ?>>
            <?= h($o['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <label><strong>Driver</strong></label><br>
      <select name="driver_id" required>
        <?php foreach($drivers as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= h($d['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row">
      <button class="btn">Assign</button>
    </div>
  </form>
</div>

