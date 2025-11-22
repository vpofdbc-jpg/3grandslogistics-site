<?php
// /driver/process_login.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
if ($conn instanceof mysqli) { $conn->set_charset('utf8mb4'); }

function redirect_err(string $m){ header('Location:/driver/login.php?err='.urlencode($m)); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect_err('Invalid request.');

$email = trim((string)($_POST['email'] ?? ''));
$pass  = (string)($_POST['password'] ?? '');
if ($email==='' || $pass==='') redirect_err('Enter email and password.');

$cols = [];
if ($r=$conn->query("SHOW COLUMNS FROM drivers")) { while($c=$r->fetch_assoc()){ $cols[$c['Field']]=true; } $r->close(); }
$pwdCols = array_values(array_intersect(array_keys($cols), ['password_hash','password','pwd','pass']));
if (!$pwdCols) redirect_err('No password column in drivers table.');

$selPwd = implode(', ', array_map(fn($c)=>"$c AS `$c`",$pwdCols));
$hasUser = isset($cols['username']);
$sql = "SELECT id, COALESCE(NULLIF(name,''),'Driver') name, COALESCE(email,'') email, $selPwd
        FROM drivers
        WHERE ".($hasUser ? "(LOWER(email)=LOWER(?) OR LOWER(username)=LOWER(?))" : "LOWER(email)=LOWER(?)")."
        LIMIT 1";
$st = $hasUser ? $conn->prepare($sql) : $conn->prepare(str_replace(' OR LOWER(username)=LOWER(?)','',$sql));
$hasUser ? $st->bind_param('ss',$email,$email) : $st->bind_param('s',$email);
$st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
if(!$row) redirect_err('Invalid credentials');

$ok = false;
foreach ($pwdCols as $c) {
  $v = (string)($row[$c] ?? '');
  if ($v==='') continue;
  if (str_starts_with($v,'$2y$') || str_starts_with($v,'$argon2')) { $ok = password_verify($pass,$v); }
  elseif (preg_match('/^[a-f0-9]{32}$/i',$v)) { $ok = (strtolower($v)===md5($pass)); }
  elseif (preg_match('/^[a-f0-9]{40}$/i',$v)) { $ok = (strtolower($v)===sha1($pass)); }
  else { $ok = hash_equals($v,$pass) || hash_equals(trim($v),$pass); }
  if ($ok) break;
}
if(!$ok) redirect_err('Invalid credentials');

$_SESSION['driver_id']    = (int)$row['id'];
$_SESSION['driver_name']  = (string)$row['name'];
$_SESSION['driver_email'] = (string)$row['email'];
header('Location:/driver/dashboard.php'); exit;

