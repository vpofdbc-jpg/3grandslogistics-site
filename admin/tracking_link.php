<?php
// /admin/tracking_link.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) { exit('Pass ?order_id=123'); }

// ensure column only if missing
try {
  $res = $conn->query("SHOW COLUMNS FROM orders LIKE 'tracking_token'");
  if ($res && $res->num_rows === 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN tracking_token CHAR(32) NOT NULL DEFAULT '' AFTER order_id");
  }
  if ($res) $res->close();
} catch (Throwable $e) { /* ignore */ }

// fetch/mint token
$st = $conn->prepare("SELECT tracking_token FROM orders WHERE order_id=? LIMIT 1");
$st->bind_param('i',$orderId); $st->execute();
$row = $st->get_result()->fetch_assoc(); $st->close();
if (!$row) { exit('Order not found'); }

$tok = (string)($row['tracking_token'] ?? '');
if ($tok === '') {
  $tok = bin2hex(random_bytes(16)); // 32 hex
  $st = $conn->prepare("UPDATE orders SET tracking_token=? WHERE order_id=?");
  $st->bind_param('si',$tok,$orderId); $st->execute(); $st->close();
}

$full  = "https://3grandslogistics.com/track.php?order_id={$orderId}&token={$tok}";
$short = "/t/{$orderId}-{$tok}";
?>
<!doctype html><meta charset="utf-8">
<p><b>Full URL:</b> <a href="<?=h($full)?>" target="_blank"><?=h($full)?></a></p>
<p><b>Short URL:</b> <?=h($short)?></p>

