<?php
// /admin/change_password.php
declare(strict_types=1);
session_start();
// Use the reliable, absolute path with require_once
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Redirect if not logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        // Fetch current hash
        $stmt = $conn->prepare("SELECT pass_hash FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current, $row['pass_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET pass_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $newHash, $_SESSION['admin_id']);
            $stmt->execute();
            $success = 'Password updated successfully!';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Change Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f9;margin:0}
    .box{max-width:420px;margin:60px auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
    h2{margin:0 0 12px}
    label{font-weight:700}
    input{width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:8px;margin:8px 0 14px}
    button{width:100%;padding:12px;border:none;border-radius:8px;background:#007bff;color:#fff;font-weight:700;cursor:pointer}
    .msg{padding:10px;border-radius:8px;margin-bottom:12px}
    .err{background:#fee;border:1px solid #f99;color:#900}
    .ok{background:#efe;border:1px solid #9c9;color:#060}
  </style>
</head>
<body>
  <div class="box">
    <h2>Change Password</h2>
    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post">
      <label>Current Password</label>
      <input type="password" name="current_password" required>

      <label>New Password</label>
      <input type="password" name="new_password" required>

      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required>

      <button type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>
