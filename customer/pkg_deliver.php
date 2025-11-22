<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('POST only'); }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

$uid=(int)$_SESSION['user_id']; $pkgId=(int)($_POST['pkg_id'] ?? 0);

require __DIR__.'/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli)) $conn=$mysqli;
$conn->set_charset('utf8mb4');

$has=function(string $col)use($conn){$r=$conn->query("SHOW COLUMNS FROM packages LIKE '{$conn->real_escape_string($col)}'");$ok=(bool)$r->num_rows;$r->close();return $ok;};

$st=$conn->prepare("SELECT id FROM packages WHERE id=? AND user_id=? LIMIT 1");
$st->bind_param('ii',$pkgId,$uid); $st->execute();
if(!$st->get_result()->fetch_row()){ http_response_code(404); exit('Not found'); }
$st->close();

$sets=[]; if($has('delivery_requested_at')) $sets[]="delivery_requested_at=NOW()";
if($has('status')) $sets[]="status='Delivery Requested'";
$sql="UPDATE packages SET ".implode(',', $sets)." WHERE id=? LIMIT 1";
$u=$conn->prepare($sql); $u->bind_param('i',$pkgId); $u->execute(); $u->close();

header('Location: /customer/dashboard.php?ok=delivery_requested');
