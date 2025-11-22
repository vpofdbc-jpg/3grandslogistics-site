<?php
// dbtest.php

$host = "localhost";        
$dbname = "hearmysm_delivery_app";   // check this matches in cPanel
$username = "hearmysm_delivery_user"; 
$password = "YOUR_NEW_PASSWORD";      // use the new one you reset in cPanel

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2 style='color:green'>✅ Database connection successful!</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red'>❌ Database connection failed:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
