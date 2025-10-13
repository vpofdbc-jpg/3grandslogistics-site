<?php
error_reporting(E_ALL); ini_set('display_errors',1);

echo "A\n";
// Use the reliable, absolute path with require_once
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
echo "B\n";

if (!isset($conn)) { die("\nNO \$conn"); }
echo get_class($conn), "\nOK\n";
