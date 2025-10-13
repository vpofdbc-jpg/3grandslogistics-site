<?php
// /success.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); exit('Missing order_id.'); }

/* Pull order + (optional) driver status/POD */
$sql = "
  SELECT
    o.order_id,
    o.user_id,
    o.status           AS order_status,
    o.package_size,
    o.pickup_address,
    o.delivery_address,
    o.miles,
    o.total_price,
    o.created_at,
    o.vehicle_type,
    o.notes,
    od.status          AS driver_status,
    od.pod_photo
  FROM orders o
  LEFT JOIN order_driver od ON od.order_id = o.order_id
  WHERE o.order_id = ?
  LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param('i', $orderId);
$st->execute();
$order = $st->get_result()->fetch_assoc();
$st->close();

if (!$order) { http_response_code(404); exit('Order not found.'); }

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Order #<?= (int)$order['order_id'] ?> • Success</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
  .wrap{max-width:900px;margin:40px auto;padding:0 14px}
  .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:18px}
  h1{margin:0 0 12px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .row{margin:6px 0}
  .muted{color:#555}
  .ok{color:#2d6a4f;font-weight:700}
  .btn{display:inline-block;margin-top:14px;padding:10px 14px;border-radius:10px;font-weight:700;
       text-decoration:none;color:#fff;background:#0d6efd}
  .pod{margin-top:10px}
  .pod img{max-width:260px;border:1px solid #e9edf3;border-radius:8px}
  @media (max-width:780px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>✅ Order Placed Successfully</h1>
      <div class="muted">Order #: <strong><?= (int)$order['order_id'] ?></strong> • Created: <?= h($order['created_at']) ?></div>

      <div class="grid" style="margin-top:12px">
        <div>
          <h3>Details</h3>
          <div class="row"><strong>Pickup:</strong> <?= h($order['pickup_address']) ?></div>
          <div class="row"><strong>Destination:</strong> <?= h($order['delivery_address']) ?></div>
          <div class="row"><strong>Package:</strong> <?= h($order['package_size']) ?></div>
          <?php if (!empty($order['vehicle_type'])): ?>
            <div class="row"><strong>Vehicle:</strong> <?= h(ucfirst((string)$order['vehicle_type'])) ?></div>
          <?php endif; ?>
          <?php if (isset($order['miles'])): ?>
            <div class="row"><strong>Miles:</strong> <?= h((string)$order['miles']) ?></div>
          <?php endif; ?>
          <?php if (isset($order['total_price'])): ?>
            <div class="row"><strong>Total:</strong> $<?= number_format((float)$order['total_price'],2) ?></div>
          <?php endif; ?>
          <?php if (!empty($order['notes'])): ?>
            <div class="row"><strong>Notes:</strong> <?= h($order['notes']) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <h3>Status</h3>
          <div class="row"><strong>Payment:</strong>
            <span class="ok"><?= h($order['order_status'] ?? 'Paid') ?></span>
          </div>
          <div class="row"><strong>Driver:</strong>
            <?= h($order['driver_status'] ?: 'Assigned') ?>
          </div>
          <?php if (!empty($order['pod_photo'])): ?>
            <div class="pod">
              <div><strong>POD:</strong> <a href="<?= h($order['pod_photo']) ?>" target="_blank">Open photo</a></div>
              <div style="margin-top:6px">
                <a href="<?= h($order['pod_photo']) ?>" target="_blank">
                  <img src="<?= h($order['pod_photo']) ?>" alt="Proof of Delivery">
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <a class="btn" href="/home.php">↩︎ Go to Dashboard</a>
    </div>
  </div>
</body>
</html>



