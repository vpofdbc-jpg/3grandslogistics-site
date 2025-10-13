<?php
// /cron/driver_presence_sweep.php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) { $conn = $mysqli; }

$cols=[]; if ($res=$conn->query("SHOW COLUMNS FROM drivers")) { while($c=$res->fetch_assoc()) $cols[$c['Field']]=true; $res->close(); }

if (!empty($cols['last_seen'])) {
  $conn->query("UPDATE drivers SET is_online=0 WHERE is_online=1 AND (last_seen IS NULL OR last_seen < NOW() - INTERVAL 15 MINUTE)");
} elseif (!empty($cols['last_online'])) {
  $conn->query("UPDATE drivers SET is_online=0 WHERE is_online=1 AND (last_online IS NULL OR last_online < NOW() - INTERVAL 15 MINUTE)");
} else {
  // nothing to sweep, leave as-is
}

echo "ok\n";
