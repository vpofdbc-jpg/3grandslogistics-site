<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location: /login.php'); exit; }

$step = max(1, (int)($_GET['step'] ?? 1));

if (isset($_GET['done'])) {
  $_SESSION['saw_welcome'] = 1;
  header('Location: /home.php');
  exit;
}
$name = $_SESSION['name'] ?? 'Customer';
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center}
.box{background:#fff;padding:36px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,.08);max-width:560px;width:92%}
h1{margin:0 0 10px} .muted{color:#555}
.row{display:flex;gap:10px;margin-top:18px}
.btn{display:inline-block;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:700;border:0;cursor:pointer}
.btn.primary{background:#198754;color:#fff}.btn.ghost{background:#eef1f6;color:#333}
</style>
</head><body><div class="box">
<?php if ($step === 1): ?>
  <h1>Welcome back 👋</h1>
  <p class="muted">You’re signed in as <strong><?= htmlspecialchars($name) ?></strong>.</p>
  <p class="muted">We’ll show you around in two quick screens.</p>
  <div class="row">
    <a class="btn primary" href="/welcome.php?step=2">Next</a>
    <a class="btn ghost" href="/welcome.php?done=1">Skip tour</a>
  </div>
<?php else: ?>
  <h1>How things work</h1>
  <ul class="muted" style="line-height:1.7">
    <li>Create a pickup → get assigned to a driver.</li>
    <li>Watch live status: <em>Accepted → PickedUp → In Transit → Delivered</em>.</li>
    <li>When delivered, your POD photo appears on your order.</li>
  </ul>
  <div class="row">
    <a class="btn ghost" href="/welcome.php?step=1">Back</a>
    <a class="btn primary" href="/welcome.php?done=1">Continue to Dashboard</a>
  </div>
<?php endif; ?>
</div></body></html>








