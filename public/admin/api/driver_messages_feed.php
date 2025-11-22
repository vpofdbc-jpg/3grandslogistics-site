<?php
// /admin/api/driver_messages_feed.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$ADMIN_OK = (!empty($_SESSION['admin_id']) || (!empty($_SESSION['role']) && $_SESSION['role']==='admin') || !empty($_SESSION['is_admin']));
if (!$ADMIN_OK) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }

/* Schema prep (tolerant) */
try {
  $cols = [];
  if ($r=$conn->query("SHOW COLUMNS FROM driver_messages")) {
    while($c=$r->fetch_assoc()){ $cols[$c['Field']] = true; }
    $r->close();
  }
  if (empty($cols['seen_by_admin_at'])) { $conn->query("ALTER TABLE driver_messages ADD COLUMN seen_by_admin_at DATETIME NULL"); }
  if (empty($cols['seen_by_driver_at'])) { $conn->query("ALTER TABLE driver_messages ADD COLUMN seen_by_driver_at DATETIME NULL"); }
} catch(Throwable $e) {}

try {
  $conn->query("CREATE TABLE IF NOT EXISTS typing_status (
    driver_id INT NOT NULL,
    actor ENUM('admin','driver') NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (driver_id, actor)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Throwable $e) {}

$driver_id = (int)($_GET['driver_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$since_id = (int)($_GET['since_id'] ?? 0);

$w = "1=1";
$bind = [];
$types = '';

if ($driver_id > 0) { $w .= " AND dm.driver_id=?"; $types.='i'; $bind[]=$driver_id; }
if ($q !== '')      { $w .= " AND dm.message LIKE CONCAT('%',?,'%')"; $types.='s'; $bind[]=$q; }

$tail = $since_id>0 ? "AND dm.id > ? ORDER BY dm.id ASC" : "ORDER BY dm.id DESC LIMIT 100";
if ($since_id>0){ $types.='i'; $bind[]=$since_id; }

$sql = "SELECT dm.id, dm.driver_id, COALESCE(d.name, CONCAT('Driver #',dm.driver_id)) AS driver_name,
               dm.sender, dm.message, dm.created_at, dm.seen_by_admin_at, dm.seen_by_driver_at
        FROM driver_messages dm
        LEFT JOIN drivers d ON d.id=dm.driver_id
        WHERE $w
        $tail";

$st = $conn->prepare($sql);
if ($types) $st->bind_param($types, ...$bind);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* unread per driver for badges (messages from driver unseen by admin) */
$unread = [];
try {
  $q2 = "SELECT driver_id, COUNT(*) c
         FROM driver_messages
         WHERE sender='driver' AND seen_by_admin_at IS NULL
         GROUP BY driver_id";
  $r2 = $conn->query($q2);
  while($u=$r2->fetch_assoc()) $unread[(string)$u['driver_id']] = (int)$u['c'];
} catch(Throwable $e){}

/* typing map (last 6s) */
$typing = [];
try {
  $q3 = "SELECT driver_id, actor FROM typing_status
         WHERE updated_at >= (NOW() - INTERVAL 6 SECOND)";
  $r3 = $conn->query($q3);
  while($t=$r3->fetch_assoc()){
    $k = (string)$t['driver_id'];
    if (!isset($typing[$k])) $typing[$k] = ['admin'=>false,'driver'=>false];
    $typing[$k][$t['actor']] = true;
  }
} catch(Throwable $e){}

echo json_encode(['ok'=>true,'rows'=>$rows,'unread'=>$unread,'typing'=>$typing,'now'=>date('Y-m-d H:i:s')]);


