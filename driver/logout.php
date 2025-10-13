<?php
// /driver/logout.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

if (!empty($_SESSION['driver_id']) && isset($conn) && $conn instanceof mysqli) {
  $id = (int)$_SESSION['driver_id'];
  // Try to flip online off and touch any presence column that exists
  $cols=[]; if ($res=$conn->query("SHOW COLUMNS FROM drivers")) { while($c=$res->fetch_assoc()) $cols[$c['Field']]=true; $res->close(); }
  if (!empty($cols['last_seen']) && !empty($cols['last_online'])) {
    $st=$conn->prepare("UPDATE drivers SET is_online=0, last_seen=NOW(), last_online=NOW() WHERE id=?");
  } elseif (!empty($cols['last_seen'])) {
    $st=$conn->prepare("UPDATE drivers SET is_online=0, last_seen=NOW() WHERE id=?");
  } elseif (!empty($cols['last_online'])) {
    $st=$conn->prepare("UPDATE drivers SET is_online=0, last_online=NOW() WHERE id=?");
  } else {
    $st=$conn->prepare("UPDATE drivers SET is_online=0 WHERE id=?");
  }
  $st->bind_param('i',$id); $st->execute(); $st->close();
}

// destroy session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: /driver/login.php');
exit;


