<?php
// /admin/seed_test_accounts.php — robust seeder v3
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }
if (!$conn instanceof mysqli) { die('No MySQLi connection.'); }
$conn->set_charset('utf8mb4');

function tableExists(mysqli $c, string $t): bool {
  return (bool)$c->query("SHOW TABLES LIKE '".$c->real_escape_string($t)."'")->num_rows;
}
function cols(mysqli $c, string $t): array {
  $m=[]; if ($r=$c->query("SHOW COLUMNS FROM `$t`")) { while($x=$r->fetch_assoc()) $m[$x['Field']]=1; $r->close(); } return $m;
}
function ensureCol(mysqli $c, string $t, string $col, string $def): void {
  if (!isset(cols($c,$t)[$col])) $c->query("ALTER TABLE `$t` ADD $col $def");
}
function firstId(mysqli $c, string $sql, string $s, ...$p): ?int {
  $st=$c->prepare($sql); $st->bind_param($s, ...$p); $st->execute();
  $r=$st->get_result()->fetch_row(); $st->close();
  return $r ? (int)$r[0] : null;
}

/* ---------- ADMIN ---------- */
$adminTables = ['admin_users','admins','admin'];
$adminTbl = null; foreach ($adminTables as $t) if (tableExists($conn,$t)) { $adminTbl=$t; break; }
if (!$adminTbl) {
  $adminTbl='admin_users';
  $conn->query("CREATE TABLE `$adminTbl`(
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(120) UNIQUE,
      email VARCHAR(190) UNIQUE,
      password VARCHAR(255) NULL,
      password_hash VARCHAR(255) NULL,
      name VARCHAR(190) NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$acols = cols($conn,$adminTbl);
$loginCol = null; foreach (['username','email','user','login'] as $cand) if (isset($acols[$cand])) { $loginCol=$cand; break; }
if (!$loginCol) { $conn->query("ALTER TABLE `$adminTbl` ADD `username` VARCHAR(120) UNIQUE"); $loginCol='username'; }
ensureCol($conn,$adminTbl,'email',"VARCHAR(190) UNIQUE");
ensureCol($conn,$adminTbl,'name',"VARCHAR(190) NULL");
$acols = cols($conn,$adminTbl);
$passCol = isset($acols['password_hash']) ? 'password_hash' : (isset($acols['password']) ? 'password' : null);
if (!$passCol) { $conn->query("ALTER TABLE `$adminTbl` ADD `password` VARCHAR(255)"); $passCol='password'; }

$admUser = 'admin_test';
$admEmail = 'admin.test@example.com';
$admPass = 'Passw0rd!';
$admHash = password_hash($admPass, PASSWORD_DEFAULT);
$name='Admin Test';

/* find existing either by email OR username */
$aid = firstId($conn, "SELECT id FROM `$adminTbl` WHERE email=? LIMIT 1", 's', $admEmail);
if (!$aid && $loginCol !== 'email') {
  $aid = firstId($conn, "SELECT id FROM `$adminTbl` WHERE `$loginCol`=? LIMIT 1", 's', $admUser);
}

/* upsert (avoids duplicate-key on email) */
if ($loginCol === 'email') {
  $st = $conn->prepare(
    "INSERT INTO `$adminTbl` (`email`,`$passCol`,`name`)
     VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE `$passCol`=VALUES(`$passCol`), `name`=VALUES(`name`)"
  );
  $st->bind_param('sss',$admUser,$admHash,$name);
} else {
  $st = $conn->prepare(
    "INSERT INTO `$adminTbl` (`$loginCol`,`email`,`$passCol`,`name`)
     VALUES (?,?,?,?)
     ON DUPLICATE KEY UPDATE `$passCol`=VALUES(`$passCol`), `name`=VALUES(`name`), `$loginCol`=VALUES(`$loginCol`)"
  );
  $st->bind_param('ssss',$admUser,$admEmail,$admHash,$name);
}
$st->execute(); $st->close();

/* ---------- DRIVERS ---------- */
$drvTbl='drivers';
if (!tableExists($conn,$drvTbl)) {
  $conn->query("CREATE TABLE `$drvTbl`(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NULL,
    email VARCHAR(190) UNIQUE,
    phone VARCHAR(60) NULL,
    password VARCHAR(255) NULL,
    password_hash VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$dcols = cols($conn,$drvTbl);
ensureCol($conn,$drvTbl,'email',"VARCHAR(190) UNIQUE");
ensureCol($conn,$drvTbl,'name',"VARCHAR(190) NULL");
ensureCol($conn,$drvTbl,'phone',"VARCHAR(60) NULL");
$dcols = cols($conn,$drvTbl);
$drvPassCol = isset($dcols['password_hash']) ? 'password_hash' : (isset($dcols['password'])?'password':null);
if (!$drvPassCol) { $conn->query("ALTER TABLE `$drvTbl` ADD `password` VARCHAR(255)"); $drvPassCol='password'; }
$drvPwd='Driver123!'; $drvHash=password_hash($drvPwd,PASSWORD_DEFAULT);
$drivers=[ ['Driver One','driver1@example.com','215-000-0001'], ['Driver Two','driver2@example.com','215-000-0002'] ];
$st=$conn->prepare(
  "INSERT INTO `$drvTbl` (name,email,phone,`$drvPassCol`)
   VALUES (?,?,?,?)
   ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), `$drvPassCol`=VALUES(`$drvPassCol`)"
);
foreach($drivers as [$n,$e,$p]){ $st->bind_param('ssss',$n,$e,$p,$drvHash); $st->execute(); }
$st->close();

/* ---------- CUSTOMERS (users) ---------- */
$custTbl='users';
if (!tableExists($conn,$custTbl)) {
  $conn->query("CREATE TABLE `$custTbl`(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NULL,
    email VARCHAR(190) UNIQUE,
    password VARCHAR(255) NULL,
    password_hash VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$ucols = cols($conn,$custTbl);
ensureCol($conn,$custTbl,'email',"VARCHAR(190) UNIQUE");
if (!isset($ucols['name'])) ensureCol($conn,$custTbl,'name',"VARCHAR(190) NULL");
$ucols = cols($conn,$custTbl);
$custPassCol = isset($ucols['password_hash']) ? 'password_hash' : (isset($ucols['password'])?'password':null);
if (!$custPassCol) { $conn->query("ALTER TABLE `$custTbl` ADD `password` VARCHAR(255)"); $custPassCol='password'; }
$custPwd='Customer123!'; $custHash=password_hash($custPwd,PASSWORD_DEFAULT);
$customers=[ ['Customer One','cust1@example.com'], ['Customer Two','cust2@example.com'] ];
$st=$conn->prepare(
  "INSERT INTO `$custTbl` (name,email,`$custPassCol`)
   VALUES (?,?,?)
   ON DUPLICATE KEY UPDATE name=VALUES(name), `$custPassCol`=VALUES(`$custPassCol`)"
);
foreach($customers as [$n,$e]){ $st->bind_param('sss',$n,$e,$custHash); $st->execute(); }
$st->close();

/* ---------- Output ---------- */
header('Content-Type:text/plain; charset=utf-8');
echo "OK\n\nAdmin login:\n  username: admin_test\n  password: Passw0rd!\n\n";
echo "Driver logins:\n  driver1@example.com / $drvPwd\n  driver2@example.com / $drvPwd\n\n";
echo "Customer logins:\n  cust1@example.com / $custPwd\n  cust2@example.com / $custPwd\n\n";
echo "Delete this file when done.\n";



