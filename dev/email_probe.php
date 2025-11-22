<?php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);
session_start();

$root = $_SERVER['DOCUMENT_ROOT'] ?? getcwd();
echo "ROOT: $root<br>";

require $root.'/db.php';
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

$uid = (int)($_SESSION['user_id'] ?? 0);
echo "UID: $uid<br>";
if ($uid<=0) { exit('❌ Not logged in'); }

$emails_php = $root.'/emails.php';
echo "emails.php: ".(is_file($emails_php)?'FOUND':'MISSING')." ($emails_php)<br>";
if (is_file($emails_php)) require_once $emails_php;

if (!function_exists('send_profile_updated')) {
  exit('❌ send_profile_updated() not found in emails.php');
}

$ok = false;
try {
  $ok = send_profile_updated($uid, $conn);   // should return true/false
} catch (Throwable $e) {
  echo '❌ Exception: '.$e->getMessage();
  exit;
}

echo $ok ? '✅ SENT' : '❌ send_profile_updated() returned false (check error_log)';

