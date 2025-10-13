<?php
// /admin/order_accept.php — admin accepts; notifies customer
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);
if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($mysqli) && $mysqli instanceof mysqli && !isset($conn)) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) { http_response_code(403); exit('Bad CSRF'); }

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(422); exit('Bad input'); }

$st = $conn->prepare("UPDATE orders SET status='Accepted' WHERE order_id=?");
$st->bind_param('i', $orderId); $st->execute(); $st->close();

@send_order_accepted($orderId);

header('Location: /admin/dashboard.php');
