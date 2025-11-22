<?php
// /admin/logout.php — Admin sign-out
declare(strict_types=1);
session_start();

/* Wipe session safely */
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Signed Out • Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="3;url=/admin/login.php">
  <style>
    :root{--primary:#0d6efd;--muted:#6c757d}
    *{box-sizing:border-box}
    body{margin:0;background:#f4f5fb;font-family:Arial,Helvetica,sans-serif;color:#333}
    .wrap{max-width:640px;margin:80px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.06);padding:28px;text-align:center}
    h1{margin:0 0 8px 0;font-size:24px}
    p{margin:6px 0;color:#555}
    .actions{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;text-decoration:none;font-weight:700;color:#fff;background:var(--primary)}
    .btn.alt{background:#fff;color:var(--primary);border:2px solid var(--primary)}
    .hint{margin-top:8px;font-size:13px;color:var(--muted)}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>You’re signed out</h1>
      <p>Thanks for keeping things running smoothly.</p>
      <p class="hint">Redirecting to the admin login…</p>
      <div class="actions">
        <a class="btn" href="/admin/login.php">Admin Login</a>
        <a class="btn alt" href="/index.html">Home</a>
      </div>
    </div>
  </div>
</body>
</html>
