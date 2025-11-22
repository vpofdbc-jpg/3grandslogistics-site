<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }

$sent = false; $err = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $msg   = trim($_POST['message'] ?? '');

    if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || $msg==='') {
        $err = 'All fields are required and email must be valid.';
    } else {
        $to      = 'support@3grandslogistics.com';   // <-- change to your real support inbox
        $subject = 'Support request from '.$name;
        $body    = "From: $name <$email>\nUser ID: ".($_SESSION['user_id'] ?? 'n/a')."\n\n$msg";
        $headers = "From: noreply@3grandslogistics.com\r\nReply-To: $email\r\n";
        $sent    = @mail($to,$subject,$body,$headers);

        if (!$sent) $err = 'Could not send email from server. Configure SMTP if mail() is disabled.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Support</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
.wrap{max-width:720px;margin:32px auto;background:#fff;padding:20px;border-radius:12px;border:1px solid #e9edf3}
label{display:block;margin:10px 0 4px}
input,textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
.btn{margin-top:14px;background:#0d6efd;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;font-weight:700}
.msg{margin-bottom:10px;color:#198754}
.err{margin-bottom:10px;color:#c00}
</style>
</head>
<body>
<div class="wrap">
  <h2>Contact Support</h2>
  <?php if($sent): ?><div class="msg">Message sent. We’ll reply to <?php echo htmlspecialchars($email); ?>.</div><?php endif; ?>
  <?php if($err): ?><div class="err"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

  <form method="post" novalidate>
    <label>Name</label>
    <input name="name" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>

    <label>Message</label>
    <textarea name="message" rows="6" required></textarea>

    <button class="btn" type="submit">Send</button>
    <a class="btn" style="background:#6c757d;text-decoration:none;display:inline-block" href="/home.php">Back</a>
  </form>
</div>
</body>
</html>
