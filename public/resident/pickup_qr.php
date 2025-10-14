<?php
// /resident/pickup_qr.php — show pickup QR for counter scan
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
require __DIR__.'/../db.php'; if(!isset($conn)&&isset($mysqli)&&$mysqli instanceof mysqli)$conn=$mysqli; $conn->set_charset('utf8mb4');

$pid=(int)($_GET['pid']??0); $t=(string)($_GET['t']??'');
$st=$conn->prepare("SELECT id,pickup_token FROM packages WHERE id=? AND user_id=? LIMIT 1");
$st->bind_param('ii',$pid,$_SESSION['user_id']); $st->execute(); $p=$st->get_result()->fetch_assoc(); $st->close();
if(!$p || !hash_equals((string)$p['pickup_token'], $t)){ http_response_code(403); exit('Invalid token'); }

$payload = json_encode(['pid'=>$pid,'t'=>$t], JSON_UNESCAPED_SLASHES);
$qr = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl='.rawurlencode($payload);
?><!doctype html><meta charset="utf-8"><title>Pickup QR</title>
<div style="display:flex;min-height:100vh;align-items:center;justify-content:center;flex-direction:column;font-family:system-ui">
  <h2>Show this QR at the counter</h2>
  <img alt="QR" src="<?=$qr?>">
  <div style="margin-top:10px;color:#555">Package #<?=$pid?></div>
</div>
