<?php
// /admin/assign_driver.php — upsert assignment + unify status
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
if ($conn instanceof mysqli) { $conn->set_charset('utf8mb4'); }

$orderId  = (int)($_POST['order_id']  ?? 0);
$driverId = (int)($_POST['driver_id'] ?? 0);
if ($orderId <= 0 || $driverId <= 0) {
  header('Location:/admin/orders.php?msg=' . urlencode('Missing order/driver.')); exit;
}

$conn->begin_transaction();
try {
  // 1) Update the order itself
  $st = $conn->prepare("UPDATE orders SET driver_id=?, status='Assigned' WHERE order_id=?");
  $st->bind_param('ii', $driverId, $orderId);
  $st->execute(); $st->close();

  // 2) Upsert into order_driver (works even if row already exists)
  $hasUpdatedAt = false;
  if ($r = $conn->query("SHOW COLUMNS FROM order_driver LIKE 'updated_at'")) {
    $hasUpdatedAt = (bool)$r->num_rows; $r->close();
  }

  if ($hasUpdatedAt) {
    $sql = "INSERT INTO order_driver (order_id, driver_id, status, updated_at)
            VALUES (?,?, 'Assigned', NOW())
            ON DUPLICATE KEY UPDATE
              driver_id=VALUES(driver_id),
              status=VALUES(status),
              updated_at=VALUES(updated_at)";
  } else {
    $sql = "INSERT INTO order_driver (order_id, driver_id, status)
            VALUES (?,?, 'Assigned')
            ON DUPLICATE KEY UPDATE
              driver_id=VALUES(driver_id),
              status=VALUES(status)";
  }
  $st = $conn->prepare($sql);
  $st->bind_param('ii', $orderId, $driverId);
  $st->execute(); $st->close();

  $conn->commit();
  header('Location:/admin/orders.php?msg=' . urlencode("Order #$orderId assigned."));
} catch (Throwable $e) {
  $conn->rollback();
  header('Location:/admin/orders.php?msg=' . urlencode('Failed: ' . $e->getMessage()));
}
exit;



