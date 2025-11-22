<?php
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
$token = $_SESSION['csrf'] ?? '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Test Message Driver</title>
</head>
<body>
  <h2>Send Test Message to Driver</h2>

  <form method="POST" action="/admin/api/message_driver.php">
    <label>Driver ID:</label>
    <input type="number" name="driver_id" value="1" required><br><br>

    <label>Message:</label><br>
    <textarea name="message" rows="4" cols="40" required>Hello driver, please confirm pickup.</textarea><br><br>

    <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
    <button type="submit">Send Message</button>
  </form>
</body>
</html>
