<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

require __DIR__.'/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

/* email helpers */
require_once dirname(__DIR__) . '/emails.php'; // send_profile_updated($userId)

if (empty($_SESSION['user_id'])) { header('Location:/customer/login.php'); exit; }
$uid=(int)$_SESSION['user_id'];
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }
function has_col(mysqli $c,string $t,string $col):bool{
  $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '{$c->real_escape_string($col)}'"); $ok=(bool)$r->num_rows; $r->close(); return $ok;
}
function has_table(mysqli $c,string $t):bool{
  $r=$c->query("SHOW TABLES LIKE '{$c->real_escape_string($t)}'"); $ok=(bool)$r->num_rows; $r->close(); return $ok;
}

/* users schema */
$u_has_first = has_col($conn,'users','first_name');
$u_has_last  = has_col($conn,'users','last_name');
$u_has_name  = has_col($conn,'users','name');
$u_has_phone = has_col($conn,'users','phone');
$u_has_addr  = has_col($conn,'users','address');
$u_has_phash = has_col($conn,'users','pass_hash');
$u_has_pass  = has_col($conn,'users','password');

/* addresses schema */
$ua_exists = has_table($conn,'user_addresses');
$ua_single = $ua_exists && has_col($conn,'user_addresses','address');
$ua_line1  = $ua_exists && has_col($conn,'user_addresses','line1');
$ua_line2  = $ua_exists && has_col($conn,'user_addresses','line2');
$ua_city   = $ua_exists && has_col($conn,'user_addresses','city');
$ua_state  = $ua_exists && has_col($conn,'user_addresses','state');
$ua_zip    = $ua_exists && has_col($conn,'user_addresses','zip');
$ua_phone  = $ua_exists && has_col($conn,'user_addresses','phone');

/* load current */
$first=''; $last=''; $name=''; $email=''; $phone='';
$addr=''; $a1=''; $a2=''; $city=''; $state=''; $zip='';
$pw_field = $u_has_phash ? 'pass_hash' : ($u_has_pass?'password':null); $pw_hash=null;

$sel = "SELECT id,email".
       ($u_has_first?",first_name":"").($u_has_last?",last_name":"").
       ($u_has_name?",name":"").($u_has_phone?",phone":"").
       ($u_has_addr?",address":"").($pw_field?",$pw_field":"").
       " FROM users WHERE id=?";
$s=$conn->prepare($sel); $s->bind_param('i',$uid); $s->execute();
if($u=$s->get_result()->fetch_assoc()){
  $email=(string)($u['email']??'');
  if($u_has_first) $first=(string)($u['first_name']??'');
  if($u_has_last)  $last =(string)($u['last_name']??'');
  if($u_has_name)  $name =(string)($u['name']??'');
  if(!$first && !$last && $name){ [$first,$last]=array_pad(preg_split('/\s+/', $name, 2)?:[],2,''); }
  if($u_has_phone) $phone=(string)($u['phone']??'');
  if($u_has_addr)  $addr =(string)($u['address']??'');
  if($pw_field)    $pw_hash=(string)($u[$pw_field]??'');
}
$s->close();

/* fetch address from user_addresses if users.address absent */
if($ua_exists && !$u_has_addr){
  $cols=[]; if($ua_single) $cols[]='address';
  if($ua_line1) $cols[]='line1'; if($ua_line2) $cols[]='line2';
  if($ua_city)  $cols[]='city';  if($ua_state) $cols[]='state';
  if($ua_zip)   $cols[]='zip';   if($ua_phone) $cols[]='phone';
  if($cols){
    $sql="SELECT ".implode(',',$cols)." FROM user_addresses WHERE user_id=? LIMIT 1";
    $st=$conn->prepare($sql); $st->bind_param('i',$uid); $st->execute();
    if($a=$st->get_result()->fetch_assoc()){
      if($ua_single) $addr=(string)($a['address']??'');
      if($ua_line1) $a1  =(string)($a['line1']??'');
      if($ua_line2) $a2  =(string)($a['line2']??'');
      if($ua_city)  $city=(string)($a['city']??'');
      if($ua_state) $state=(string)($a['state']??'');
      if($ua_zip)   $zip =(string)($a['zip']??'');
      if($ua_phone && !$u_has_phone) $phone=(string)($a['phone']??$phone);
    }
    $st->close();
  }
}

/* save */
$msg=''; $err='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!hash_equals($_SESSION['csrf'], (string)($_POST['csrf']??''))){ http_response_code(400); exit('Bad CSRF'); }

  $p_first=trim((string)($_POST['first_name']??''));
  $p_last =trim((string)($_POST['last_name']??''));
  $p_email=trim((string)($_POST['email']??''));
  $p_phone=trim((string)($_POST['phone']??''));
  $addr_single=trim((string)($_POST['address']??''));
  $p_a1  =trim((string)($_POST['a1']??''));  $p_a2=trim((string)($_POST['a2']??''));
  $p_city=trim((string)($_POST['city']??'')); $p_state=trim((string)($_POST['state']??''));
  $p_zip =trim((string)($_POST['zip']??''));

  if($p_email===''){ $err='Email required.'; }
  else{
    $q=$conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $q->bind_param('si',$p_email,$uid); $q->execute();
    if($q->get_result()->fetch_row()) $err='Email already in use.'; $q->close();
  }

  /* password change */
  $cur=(string)($_POST['current_password']??'');
  $n1 =(string)($_POST['new_password']??'');
  $n2 =(string)($_POST['confirm_password']??'');
  if(!$err && ($n1!=='' || $n2!=='')){
    if($n1!==$n2) $err='Passwords do not match.';
    else{
      $ok=false;
      if($pw_hash!==null){
        if (str_starts_with((string)$pw_hash,'$')) $ok=password_verify($cur,(string)$pw_hash);
        else $ok=hash_equals($pw_hash,$cur);
      }
      if(!$ok) $err='Current password incorrect.';
      else{
        $new_hash=password_hash($n1,PASSWORD_DEFAULT);
        $upd=$conn->prepare("UPDATE users SET ".($u_has_phash?'pass_hash=?':'password=?')." WHERE id=?");
        $upd->bind_param('si',$new_hash,$uid); $upd->execute(); $upd->close();
        $pw_hash=$new_hash;
      }
    }
  }

  if(!$err){
    /* update names + email + phone/address in users */
    if($u_has_first || $u_has_last){
      $sql="UPDATE users SET email=?, ".($u_has_first?'first_name=?,':'').($u_has_last?'last_name=?,':'').
           ($u_has_phone?'phone=?,':'').($u_has_addr?'address=?,':'')." id=id WHERE id=?";
      $types=''; $args=[];
      $types.='s'; $args[]=$p_email;
      if($u_has_first){ $types.='s'; $args[]=$p_first; }
      if($u_has_last ){ $types.='s'; $args[]=$p_last; }
      if($u_has_phone){ $types.='s'; $args[]=$p_phone; }
      if($u_has_addr ){
        $addr_to_store=$addr_single ?: trim("$p_a1 ".($p_a2!==''?"$p_a2 ":'')."$p_city $p_state $p_zip");
        $types.='s'; $args[]=$addr_to_store;
      }
      $types.='i'; $args[]=$uid;
      $u=$conn->prepare($sql); $u->bind_param($types, ...$args); $u->execute(); $u->close();
    } elseif ($u_has_name){
      $full=trim($p_first.' '.$p_last);
      $sql="UPDATE users SET email=?, ".($u_has_phone?'phone=?,':'').($u_has_addr?'address=?,':'')." name=? WHERE id=?";
      $types=''; $args=[];
      $types.='s'; $args[]=$p_email;
      if($u_has_phone){ $types.='s'; $args[]=$p_phone; }
      if($u_has_addr ){
        $addr_to_store=$addr_single ?: trim("$p_a1 ".($p_a2!==''?"$p_a2 ":'')."$p_city $p_state $p_zip");
        $types.='s'; $args[]=$addr_to_store;
      }
      $types.='s'; $args[]=$full;
      $types.='i'; $args[]=$uid;
      $u=$conn->prepare($sql); $u->bind_param($types, ...$args); $u->execute(); $u->close();
    } else {
      $u=$conn->prepare("UPDATE users SET email=? ".($u_has_phone?', phone=?':'')." WHERE id=?");
      if($u_has_phone){ $u->bind_param('ssi',$p_email,$p_phone,$uid); }
      else            { $u->bind_param('si' ,$p_email,$uid); }
      $u->execute(); $u->close();
    }

    /* upsert user_addresses if present */
    if($ua_exists){
      $ex=$conn->prepare("SELECT 1 FROM user_addresses WHERE user_id=?");
      $ex->bind_param('i',$uid); $ex->execute(); $exists=(bool)$ex->get_result()->fetch_row(); $ex->close();

      if($ua_single){
        $addr_to_store=$addr_single ?: trim("$p_a1 ".($p_a2!==''?"$p_a2 ":'')."$p_city $p_state $p_zip");
        if($exists){
          $q=$conn->prepare("UPDATE user_addresses SET address=?".($ua_phone?", phone=?":"")." WHERE user_id=?");
          if($ua_phone) $q->bind_param('ssi',$addr_to_store,$p_phone,$uid);
          else          $q->bind_param('si' ,$addr_to_store,$uid);
        }else{
          $cols="user_id,address"; $vals="?,?"; $types='is'; $args=[$uid,$addr_to_store];
          if($ua_phone){ $cols.=",phone"; $vals.=",?"; $types.='s'; $args[]=$p_phone; }
          $q=$conn->prepare("INSERT INTO user_addresses ($cols) VALUES ($vals)");
          $q->bind_param($types, ...$args);
        }
        $q->execute(); $q->close();
      } else {
        $parts=[]; $types=''; $args=[];
        if($ua_line1){ $parts[]='line1=?'; $types.='s'; $args[]=$p_a1; }
        if($ua_line2){ $parts[]='line2=?'; $types.='s'; $args[]=$p_a2; }
        if($ua_city ){ $parts[]='city=?';  $types.='s'; $args[]=$p_city; }
        if($ua_state){ $parts[]='state=?'; $types.='s'; $args[]=$p_state; }
        if($ua_zip  ){ $parts[]='zip=?';   $types.='s'; $args[]=$p_zip; }
        if($ua_phone){ $parts[]='phone=?'; $types.='s'; $args[]=$p_phone; }
        if($parts){
          if($exists){
            $sql="UPDATE user_addresses SET ".implode(',',$parts)." WHERE user_id=?";
            $types.='i'; $args[]=$uid; $q=$conn->prepare($sql); $q->bind_param($types, ...$args);
          } else {
            $cols=['user_id']; $vals=['?']; $t='i'; $a=[$uid];
            if($ua_line1){ $cols[]='line1'; $vals[]='?'; $t.='s'; $a[]=$p_a1; }
            if($ua_line2){ $cols[]='line2'; $vals[]='?'; $t.='s'; $a[]=$p_a2; }
            if($ua_city ){ $cols[]='city';  $vals[]='?'; $t.='s'; $a[]=$p_city; }
            if($ua_state){ $cols[]='state'; $vals[]='?'; $t.='s'; $a[]=$p_state; }
            if($ua_zip  ){ $cols[]='zip';   $vals[]='?'; $t.='s'; $a[]=$p_zip; }
            if($ua_phone){ $cols[]='phone'; $vals[]='?'; $t.='s'; $a[]=$p_phone; }
            $q=$conn->prepare("INSERT INTO user_addresses (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
            $q->bind_param($t, ...$a);
          }
          $q->execute(); $q->close();
        }
      }
    }

    /* fire profile-updated email */
    if (function_exists('send_profile_updated')) { @send_profile_updated($uid); }

    $msg='Saved. We emailed a confirmation of your profile update.';
    $first=$p_first; $last=$p_last; $email=$p_email; $phone=$p_phone;
    $addr=$addr_single; $a1=$p_a1; $a2=$p_a2; $city=$p_city; $state=$p_state; $zip=$p_zip;
  }
}

$show_single = $u_has_addr || $ua_single || (!($ua_line1||$ua_city||$ua_state||$ua_zip));
?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profile</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
.wrap{max-width:760px;margin:24px auto;background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:18px}
label{display:block;margin:10px 0 4px;color:#555;font-size:14px}
input,textarea{width:100%;padding:10px;border:1px solid #dfe3ea;border-radius:8px}
button{margin-top:12px;background:#0d6efd;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer}
.note{font-size:12px;color:#666}
.msg{margin-bottom:10px;padding:8px;border-radius:8px}
.ok{background:#e7f5ff;border:1px solid #a5d8ff}
.err{background:#fde2e1;border:1px solid #f5a097}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
</style>
<div class="wrap">
  <h2>Customer Profile</h2>
  <?php if($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
  <?php if($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">

    <div class="grid">
      <div>
        <label>First name</label>
        <input name="first_name" value="<?=h($first)?>">
      </div>
      <div>
        <label>Last name</label>
        <input name="last_name" value="<?=h($last)?>">
      </div>
    </div>

    <label>Email</label>
    <input name="email" required value="<?=h($email)?>">

    <label>Phone</label>
    <input name="phone" value="<?=h($phone)?>">

    <?php if($show_single): ?>
      <label>Address</label>
      <textarea name="address" rows="3"><?=h($addr)?></textarea>
    <?php else: ?>
      <div class="grid">
        <div><label>Address line 1</label><input name="a1" value="<?=h($a1)?>"></div>
        <div><label>Address line 2</label><input name="a2" value="<?=h($a2)?>"></div>
        <div><label>City</label><input name="city" value="<?=h($city)?>"></div>
        <div><label>State</label><input name="state" value="<?=h($state)?>"></div>
        <div><label>Zip</label><input name="zip" value="<?=h($zip)?>"></div>
      </div>
    <?php endif; ?>

    <h3 style="margin-top:18px">Change Password</h3>
    <label>Current password</label>
    <input type="password" name="current_password" autocomplete="current-password">
    <div class="grid">
      <div><label>New password</label><input type="password" name="new_password" autocomplete="new-password"></div>
      <div><label>Confirm new password</label><input type="password" name="confirm_password" autocomplete="new-password"></div>
    </div>
    <div class="note">Leave password fields blank to keep your current password.</div>

    <button type="submit">Save changes</button>
  </form>
</div>


