<?php
// This file is purely for testing if the HTTP 500 error persists
// when including the bootstrap file in a clean environment.

require_once('../bootstrap.php');

// If you see this, the server did not return a 500 error.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Minimal Test Success</title>
</head>
<body style="padding: 50px; text-align: center; font-family: sans-serif;">
    <h1 style="color: #10B981;">SUCCESS!</h1>
    <p>The minimal `bootstrap.php` file was included without causing an HTTP 500 error.</p>
    <p>The problem is likely within the logic of <code>login.php</code> after the include.</p>
</body>
</html>
