<?php
// /admin/order_edit.php — simple admin edit form with same editability rule
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($mysqli) && $mysqli instanceof mysqli && !isset($conn)) { $conn = $mysqli; }
if ($conn instanceof mysqli) { $conn->set_charset('utf8mb4'); }

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$id = (int)($_GET['id'] ?? 0);

/* load order + meta needed for the editability rule & package counts */
$st = $conn->prepare("
  SELECT o.*
       , (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key IN ('label_checked_out_at','scan_office')) AS scan_office
       , (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key='scan_pickup') AS scan_pickup
       , (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key IN ('pkgs_pickup','pickup_packages','packages_pickup','packages')) AS pkgs_pickup
       , (SELECT MAX(meta_value) FROM order_meta WHERE order_id=o.order_id AND meta_key IN ('pkgs_delivery','delivery_packages','packages_delivery')) AS pkgs_delivery
  FROM orders o
  WHERE o.order_id=?
  LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$order = $st->get_result()->fetch_assoc();
$st->close();

if (!$order) { http_response_code(404); die('Order not found.'); }

/* same rule used everywhere */
function order_is_editable(array $o): bool {
  $status = (string)($o['status'] ?? 'Pending');
  $scan_office = (string)($o['scan_office'] ?? '');
  $scan_pickup = (string)($o['scan_pickup'] ?? '');
  if ($scan_office !== '' || $scan_pickup !== '') return false;
  return in_array($status, ['Pending','Assigned','Accepted'], true);
}
$editable = order_is_editable($order);

/* save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editable) {
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); die('Bad CSRF'); }

  $pickup   = trim((string)($_POST['pickup_address'] ?? ''));
  $delivery = trim((string)($_POST['delivery_address'] ?? ''));
  $pk_up    = max(0, (int)($_POST['pkgs_pickup'] ?? 0));
  $pk_del   = max(0, (int)($_POST['pkgs_delivery'] ?? 0));

  $st = $conn->prepare("UPDATE orders SET pickup_address=?, delivery_address=? WHERE order_id=? LIMIT 1");
  $st->bind_param('ssi', $pickup, $delivery, $id);
  $st->execute(); $st->close();

  // upsert package counts into order_meta (normalize to pkgs_pickup / pkgs_delivery)
  $keys_pickup   = ['pkgs_pickup','pickup_packages','packages_pickup','packages'];
  $keys_delivery = ['pkgs_delivery','delivery_packages','packages_delivery'];

  $in1 = implode(",", array_fill(0, count($keys_pickup), "?"));
  $in2 = implode(",", array_fill(0, count($keys_delivery), "?"));
  $types1 = str_repeat('s', count($keys_pickup));
  $types2 = str_repeat('s', count($keys_delivery));

  $stmt = $conn->prepare("DELETE FROM order_meta WHERE order_id=? AND meta_key IN ($in1)");
  $stmt->bind_param('i'.$types1, $id, ...$keys_pickup); $stmt->execute(); $stmt->close();

  $stmt = $conn->prepare("DELETE FROM order_meta WHERE order_id=? AND meta_key IN ($in2)");
  $stmt->bind_param('i'.$types2, $id, ...$keys_delivery); $stmt->execute(); $stmt->close();

  if ($pk_up > 0) {
    $stmt = $conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
    $key='pkgs_pickup'; $val=(string)$pk_up;
    $stmt->bind_param('iss', $id, $key, $val); $stmt->execute(); $stmt->close();
  }
  if ($pk_del > 0) {
    $stmt = $conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
    $key='pkgs_delivery'; $val=(string)$pk_del;
    $stmt->bind_param('iss', $id, $key, $val); $stmt->execute(); $stmt->close();
  }

  header('Location: /admin/dashboard.php'); exit;
}

/* defaults */
$pickup_val   = (string)($order['pickup_address']   ?? '');
$delivery_val = (string)($order['delivery_address'] ?? '');
$pk_up_val    = (int)($order['pkgs_pickup']   ?? 0);
$pk_del_val   = (int)($order['pkgs_delivery'] ?? 0);
?>
<!doctype html>
<meta charset="utf-8">
<title>Admin • Edit Order #<?= (int)$id ?></title>
<style>
  body{font:14px system-ui,Arial;margin:24px}
  .note{background:#f8fafc;border:1px solid #e5e7eb;padding:10px;border-radius:8px;margin-bottom:14px}
  label{display:block;margin:10px 0 4px}
  input[type=text],input[type=number]{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn{display:inline-block;margin-top:12px;background:#0d6efd;color:#fff;border:0;border-radius:8px;padding:10px 14px;font-weight:700;text-decoration:none}
  .btn.gray{background:#6b7280}
</style>

<h2>Admin • Edit Order #<?= (int)$id ?></h2>

<p class="note">
  Editable: while status is <b>Pending</b>, <b>Assigned</b>, or <b>Accepted</b> and <b>no</b> label checkout and <b>no</b> pickup scan.
</p>

<?php if (!$editable): ?>
  <p><b>This order can’t be edited anymore according to the rule above.</b></p>
  <p><a class="btn gray" href="/admin/dashboard.php">← Back to Dashboard</a></p>
<?php else: ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <label for="pickup_address">Pickup address</label>
    <input id="pickup_address" name="pickup_address" type="text" required value="<?= h($pickup_val) ?>">

    <label for="delivery_address">Delivery address</label>
    <input id="delivery_address" name="delivery_address" type="text" required value="<?= h($delivery_val) ?>">

    <div class="row">
      <div>
        <label for="pkgs_pickup"># of packages at pickup</label>
        <input id="pkgs_pickup" name="pkgs_pickup" type="number" min="0" step="1" value="<?= h((string)$pk_up_val) ?>">
      </div>
      <div>
        <label for="pkgs_delivery"># of packages at delivery</label>
        <input id="pkgs_delivery" name="pkgs_delivery" type="number" min="0" step="1" value="<?= h((string)$pk_del_val) ?>">
      </div>
    </div>

    <button class="btn" type="submit">Save changes</button>
    <a class="btn gray" href="/admin/dashboard.php">Cancel</a>
  </form>
<?php endif; ?>

