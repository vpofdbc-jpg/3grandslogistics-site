<?php
declare(strict_types=1);

/** Ensure orders.tracking_token exists (safe to run repeatedly) */
function _ensure_tracking_column(mysqli $conn): void {
  $exists = false;
  if ($r = $conn->query("SHOW COLUMNS FROM orders LIKE 'tracking_token'")) {
    $exists = $r->num_rows > 0; $r->close();
  }
  if (!$exists) {
    $conn->query("ALTER TABLE orders ADD COLUMN tracking_token CHAR(32) NOT NULL DEFAULT '' AFTER order_id");
  }
}

/** Return existing token for the order, or create one and return it */
function tracking_token_for_order(mysqli $conn, int $orderId): string {
  _ensure_tracking_column($conn);

  $st = $conn->prepare("SELECT tracking_token FROM orders WHERE order_id=?");
  $st->bind_param('i',$orderId); $st->execute();
  $tok = (string)($st->get_result()->fetch_column() ?? '');
  $st->close();

  if ($tok === '') {
    $st = $conn->prepare("UPDATE orders SET tracking_token=MD5(CONCAT(order_id,RAND())) WHERE order_id=?");
    $st->bind_param('i',$orderId); $st->execute(); $st->close();

    $st = $conn->prepare("SELECT tracking_token FROM orders WHERE order_id=?");
    $st->bind_param('i',$orderId); $st->execute();
    $tok = (string)($st->get_result()->fetch_column() ?? '');
    $st->close();
  }
  return $tok;
}

function tracking_link_rel(mysqli $conn, int $orderId): string {
  return '/t/'.$orderId.'-'.tracking_token_for_order($conn,$orderId);
}
function tracking_link_abs(mysqli $conn, int $orderId, string $base='https://3grandslogistics.com'): string {
  return rtrim($base,'/').tracking_link_rel($conn,$orderId);
}
