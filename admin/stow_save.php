<?php
// Assigns a storage location to an existing package
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

$tracking = trim((string)($_POST['tracking'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
if ($tracking === '' || $location === '') { header('Location:/admin/stow.php?e=bad'); exit; }

$st = $conn->prepare("UPDATE packages SET location=?, status='Stowed' WHERE tracking=?");
$st->bind_param('ss', $location, $tracking);
$st->execute();
$rows = $st->affected_rows;
$st->close();

header('Location:/admin/stow.php?'.($rows>0?'ok=1':'e=notfound'));
