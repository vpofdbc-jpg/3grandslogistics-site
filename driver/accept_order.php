<?php
// /driver/accept_order.php — update status; send Accepted email
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['driver_id'])) { header('Location:/driver/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($mysqli) && $mysqli instanceof mysqli && !isset($conn)) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

/* CSRF */
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) { http_response_code(403); exit('Bad CSRF'); }

/* Input */
$orderId   = (int)($_POST['order_id'] ?? 0);
$newStatus = (string)($_POST['new_status'] ?? '');
$driverId  = (int)$_SESSION['driver_id'];
if ($orderId <= 0 || $newStatus === '') { http_response_code(422); exit('Bad input'); }

/* Update status (both tables if applicable) */
try {
  // orders
  $st = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
  $st->bind_param('si', $newStatus, $orderId); $st->execute(); $st->close();

  // order_driver (latest row for this driver if present)
  try {
    $st = $conn->prepare("UPDATE order_driver SET status=?, updated_at=NOW() WHERE order_id=? AND driver_id=?");
    $st->bind_param('sii', $newStatus, $orderId, $driverId); $st->execute(); $st->close();
  } catch (Throwable $__) {}

  if ($newStatus === 'Accepted') { @send_order_accepted($orderId); }

  header('Location: /driver/dashboard.php');
  exit;
} catch (Throwable $e) {
  http_response_code(500); echo 'Error'; exit;
}


