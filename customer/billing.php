<?php
declare(strict_types=1);
session_start(); if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
require __DIR__.'/../db.php'; require __DIR__.'/stripe_config.php';
$uid=(int)$_SESSION['user_id']; $conn->set_charset('utf8mb4');
$u=$conn->prepare("SELECT stripe_customer_id FROM users WHERE id=?"); $u->bind_param('i',$uid);
$u->execute(); $row=$u->get_result()->fetch_assoc(); $u->close();
$sc=(string)($row['stripe_customer_id']??'');
if($sc===''){ header('Location:/customer/subscribe.php'); exit; }

$portal = stripe_request('post','/billing_portal/sessions',[
  'customer'=>$sc,
  'return_url'=>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/customer/dashboard.php'
]);
header('Location: '.$portal['url'], true, 302); exit;
