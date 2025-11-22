<?php
// /profile.php — Customer Profile (safe htmlspecialchars + email on save)
declare(strict_types=1);
session_start();

// Show errors, but suppress deprecations so they don't leak into HTML
ini_set('display_errors','1');
error_reporting(E_ALL & ~E_DEPRECATED);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

/* helpers */
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* load current values (cast everything to string) */
$first=''; $last=''; $email=''; $phone=''; $street=''; $city=''; $state=''; $zipcode='';
try{
  $q = $conn->prepare("SELECT 
      COALESCE(first_name,'')  AS first_name,
      COALESCE(last_name,'')   AS last_name,
      COALESCE(email,'')       AS email,
      COALESCE(phone,'')       AS phone,
      COALESCE(street,'')      AS street,
      COALESCE(city,'')        AS city,
      COALESCE(state,'')       AS state,
      COALESCE(zipcode,'')     AS zipcode
    FROM users WHERE id=? LIMIT 1");
  $q->bind_param('i',$uid); $q->execute();
  if ($u = $q->get_result()->fetch_assoc()){
    $first   = (string)$u['first_name'];
    $last    = (string)$u['last_name'];
    $email   = (string)$u['email'];
    $phone   = (string)$u['phone'];
    $street  = (string)$u['street'];
    $city    = (string)$u['city'];
    $state   = (string)$u['state'];
    $zipcode = (string)$u['zipcode'];
  }
  $q->close();
}catch(Throwable $e){ /* log if needed */ }

$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }

  $p_first   = trim((string)($_POST['first_name'] ?? ''));
  $p_last    = trim((string)($_POST['last_name']  ?? ''));
  $p_email   = trim((string)($_POST['email']      ?? ''));
  $p_phone   = trim((string)($_POST['phone']      ?? ''));
  $p_street  = trim((string)($_POST['street']     ?? ''));
  $p_city    = trim((string)($_POST['city']       ?? ''));
  $p_state   = trim((string)($_POST['state']      ?? ''));
  $p_zipcode = trim((string)($_POST['zipcode']    ?? ''));

  if ($p_email === '') {
    $err = 'Email is required.';
  } else {
    $du = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $du->bind_param('si',$p_email,$uid); $du->execute();
    if ($du->get_result()->fetch_row()) $err = 'That email is already in use.';
    $du->close();
  }

  if (!$err) {
    $u = $conn->prepare("UPDATE users
                         SET first_name=?, last_name=?, email=?, phone=?, street=?, city=?, state=?, zipcode=?
                         WHERE id=? LIMIT 1");
    $u->bind_param('ssssssssi', $p_first,$p_last,$p_email,$p_phone,$p_street,$p_city,$p_state,$p_zipcode,$uid);
    $u->execute(); $u->close();

    // refresh vars for the form
    $first=$p_first; $last=$p_last; $email=$p_email; $phone=$p_phone;
    $street=$p_street; $city=$p_city; $state=$p_state; $zipcode=$p_zipcode;

    // fire profile-updated email only after a successful save
    try {
        $emails_php = $_SERVER['DOCUMENT_ROOT'].'/emails.php';
        if (is_file($emails_php)) {
            require_once $emails_php;
            if (function_exists('send_profile_updated')) {
                $ok = send_profile_updated($uid, $conn);
                if (!$ok) { error_log("send_profile_updated returned false for uid=$uid"); }
            } else {
                error_log('send_profile_updated() missing in emails.php');
            }
        } else {
            error_log("emails.php not found at $emails_php");
        }
    } catch (Throwable $e) {
        error_log('Profile mail error: '.$e->getMessage());
    }

    $msg='Profile saved.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Profile</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#eef1f6;margin:0}
.wrap{max-width:760px;margin:28px auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px}
h2{margin:0 0 12px}
label{display:block;margin:10px 0 6px;color:#374151;font-size:14px}
input{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.actions{margin-top:14px;display:flex;gap:10px}
.btn{background:#16a34a;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer}
.btn.gray{background:#6b7280}
.note{margin:10px 0;padding:8px;border-radius:8px}
.ok{background:#e7f5ff;border:1px solid #a5d8ff}
.err{background:#fde2e1;border:1px solid #f5a097}
</style>
</head>
<body>
  <div class="wrap">
    <h2>Edit Profile</h2>
    <?php if($msg): ?><div class="note ok"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="note err"><?=h($err)?></div><?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">

      <div class="row">
        <div>
          <label>First Name</label>
          <input name="first_name" value="<?=h($first)?>">
        </div>
        <div>
          <label>Last Name</label>
          <input name="last_name" value="<?=h($last)?>">
        </div>
      </div>

      <label>Email</label>
      <input name="email" required value="<?=h($email)?>">

      <label>Phone</label>
      <input name="phone" value="<?=h($phone)?>">

      <label>Street</label>
      <input name="street" value="<?=h($street)?>">

      <div class="row">
        <div><label>City</label><input name="city" value="<?=h($city)?>"></div>
        <div><label>State</label><input name="state" value="<?=h($state)?>"></div>
      </div>

      <label>Zip Code</label>
      <input name="zipcode" value="<?=h($zipcode)?>">

      <div class="actions">
        <button class="btn" type="submit">Save Profile</button>
        <a class="btn gray" href="/customer/dashboard.php">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>


