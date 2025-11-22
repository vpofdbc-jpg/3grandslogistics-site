<?php
// /driver/keepalive.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$driverId = (int)($_SESSION['driver_id'] ?? 0);

// OPTIONAL: record last_seen without blocking anything if DB is available
try {
 // NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
  if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
  if ($driverId && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
    @$conn->query("UPDATE drivers SET last_seen=NOW() WHERE id=".$driverId);
  }
} catch (Throwable $e) { /* ignore */ }

session_write_close(); // don't hold the session lock
echo json_encode(['ok'=>true,'ts'=>gmdate('c')]);
