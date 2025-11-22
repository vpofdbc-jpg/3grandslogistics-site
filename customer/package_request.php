<?php
// Handles "I'll pick it up" / "Deliver to me" requests
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }

$uid = (int)$_SESSION['user_id'];
$pkgId = (int)($_POST['package_id'] ?? 0);
$act = (string)($_POST['action'] ?? '');

require __DIR__.'/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

if ($pkgId<=0 || !in_array($act,['pickup','delivery'],true)) { header('Location:/customer/dashboard.php'); exit; }

/* verify ownership */
$st=$conn->prepare("SELECT id,user_id,status FROM packages WHERE id=? LIMIT 1");
$st->bind_param('i',$pkgId); $st->execute();
$p=$st->get_result()->fetch_assoc(); $st->close();
if(!$p || (int)$p['user_id'] !== $uid){ http_response_code(403); exit('Not allowed'); }

/* change status */
$new = $act==='pickup' ? 'Pickup Requested' : 'Delivery Requested';
$st=$conn->prepare("UPDATE packages SET status=?, updated_at=NOW() WHERE id=?");
$st->bind_param('si',$new,$pkgId); $st->execute(); $st->close();

/* optional email to ops */
$emails = $_SERVER['DOCUMENT_ROOT'].'/emails.php';
if (is_file($emails)) {
  try{
    require_once $emails;
    if (function_exists('send_package_request')) { @send_package_request($pkgId, $uid, $new); }
  }catch(Throwable $e){ error_log('package_request mail: '.$e->getMessage()); }
}

header('Location:/customer/dashboard.php');
