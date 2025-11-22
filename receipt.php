<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

$uid = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id=? AND user_id=?");
$stmt->bind_param('ii',$order_id,$uid);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
if (!$o) { http_response_code(404); exit('Order not found'); }

$stmt = $conn->prepare("SELECT meta_key, meta_value FROM order_meta WHERE order_id=?");
$stmt->bind_param('i',$order_id);
$stmt->execute();
$meta = [];
$r = $stmt->get_result();
while($m=$r->fetch_assoc()){ $meta[$m['meta_key']]=$m['meta_value']; }

$amount = isset($meta['final_cost']) ? (float)$meta['final_cost'] : 0.00;
?>
<!doctype html>
<meta charset="utf-8">
<title>Receipt • Order #<?= (int)$order_id ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f9;margin:0}
  .wrap{max-width:680px;margin:30px auto;background:#fff;border:1px solid #eee;border-radius:12px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,.06)}
  .btn{display:inline-block;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:700;border:1px solid #ccc;color:#222;background:#fafafa}
</style>
<div class="wrap">
  <h2>Payment Receipt</h2>
  <p><b>Order #:</b> <?= (int)$order_id ?></p>
  <p><b>Status:</b> <?= htmlspecialchars($o['status']) ?></p>
  <p><b>Paid:</b> $<?= number_format($amount,2) ?></p>
  <p><b>Paid At:</b> <?= htmlspecialchars($meta['paid_at'] ?? '-') ?></p>
  <a class="btn" href="dashboard.php">Back to Dashboard</a>
</div>
