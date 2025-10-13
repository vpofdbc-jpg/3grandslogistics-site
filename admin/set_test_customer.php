<?php
// robust test customer setup
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

$email='cust1@example.com'; $pass='TestPass123';
$name='Test Customer'; $phone='555-0101';
$addr1='123 Test St'; $addr2=''; $city='Testville'; $state='TS'; $zip='00000';

function has_col(mysqli $c,string $t,string $col):bool{
  $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '{$c->real_escape_string($col)}'");
  $ok=(bool)$r->num_rows; $r->close(); return $ok;
}
function has_table(mysqli $c,string $t):bool{
  $r=$c->query("SHOW TABLES LIKE '{$c->real_escape_string($t)}'");
  $ok=(bool)$r->num_rows; $r->close(); return $ok;
}

$hasPassHash = has_col($conn,'users','pass_hash');
$hasPassword = has_col($conn,'users','password');
$hasPhoneU   = has_col($conn,'users','phone');
$hasAddrU    = has_col($conn,'users','address');

$hasUA       = has_table($conn,'user_addresses');
$ua_line1    = $hasUA && has_col($conn,'user_addresses','line1');
$ua_line2    = $hasUA && has_col($conn,'user_addresses','line2');
$ua_city     = $hasUA && has_col($conn,'user_addresses','city');
$ua_state    = $hasUA && has_col($conn,'user_addresses','state');
$ua_zip      = $hasUA && has_col($conn,'user_addresses','zip');
$ua_phone    = $hasUA && has_col($conn,'user_addresses','phone');
$ua_address  = $hasUA && has_col($conn,'user_addresses','address'); // some schemas use single column

$hash = password_hash($pass, PASSWORD_DEFAULT);

/* upsert users */
$s=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$s->bind_param('s',$email); $s->execute(); $row=$s->get_result()->fetch_assoc(); $s->close();

if ($row) {
  $uid=(int)$row['id'];
  if ($hasPhoneU && $hasAddrU) {
    $u=$conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=? WHERE id=?");
    $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip";
    $u->bind_param('ssssi',$name,$email,$phone,$addr_full,$uid);
  } elseif ($hasPhoneU) {
    $u=$conn->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=?");
    $u->bind_param('sssi',$name,$email,$phone,$uid);
  } elseif ($hasAddrU) {
    $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip";
    $u=$conn->prepare("UPDATE users SET name=?, email=?, address=? WHERE id=?");
    $u->bind_param('sssi',$name,$email,$addr_full,$uid);
  } else {
    $u=$conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
    $u->bind_param('ssi',$name,$email,$uid);
  }
  $u->execute(); $u->close();
} else {
  if ($hasPhoneU && $hasAddrU) {
    $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip";
    $i=$conn->prepare("INSERT INTO users (name,email,phone,address,".($hasPassHash?'pass_hash':'password').") VALUES (?,?,?,?,?)");
    $i->bind_param('sssss',$name,$email,$phone,$addr_full,$hash);
  } elseif ($hasPhoneU) {
    $i=$conn->prepare("INSERT INTO users (name,email,phone,".($hasPassHash?'pass_hash':'password').") VALUES (?,?,?,?)");
    $i->bind_param('ssss',$name,$email,$phone,$hash);
  } elseif ($hasAddrU) {
    $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip";
    $i=$conn->prepare("INSERT INTO users (name,email,address,".($hasPassHash?'pass_hash':'password').") VALUES (?,?,?,?)");
    $i->bind_param('ssss',$name,$email,$addr_full,$hash);
  } else {
    $i=$conn->prepare("INSERT INTO users (name,email,".($hasPassHash?'pass_hash':'password').") VALUES (?,?,?)");
    $i->bind_param('sss',$name,$email,$hash);
  }
  $i->execute(); $uid=$i->insert_id; $i->close();
}

/* set password */
if     ($hasPassHash){ $p=$conn->prepare("UPDATE users SET pass_hash=? WHERE id=?"); }
elseif ($hasPassword){ $p=$conn->prepare("UPDATE users SET password=? WHERE id=?"); }
else                 { $p=$conn->prepare("UPDATE users SET password=? WHERE id=?"); }
$p->bind_param('si',$hash,$uid); $p->execute(); $p->close();

/* upsert user_addresses only if columns exist */
if ($hasUA) {
  $ex=$conn->prepare("SELECT 1 FROM user_addresses WHERE user_id=?");
  $ex->bind_param('i',$uid); $ex->execute(); $exists=(bool)$ex->get_result()->fetch_row(); $ex->close();

  if ($ua_address) { // single text column schema
    if ($exists) {
      $q=$conn->prepare("UPDATE user_addresses SET address=?".($ua_phone?", phone=?":"")." WHERE user_id=?");
      $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip";
      if ($ua_phone) $q->bind_param('ssi',$addr_full,$phone,$uid);
      else           $q->bind_param('si' ,$addr_full,$uid);
    } else {
      $cols="user_id,address"; $vals="?,?"; $types="is";
      if ($ua_phone){ $cols.=",phone"; $vals.=",?"; $types.="s"; }
      $q=$conn->prepare("INSERT INTO user_addresses ($cols) VALUES ($vals)");
      if ($ua_phone){ $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip"; $q->bind_param($types,$uid,$addr_full,$phone); }
      else          { $addr_full="$addr1 ".($addr2? $addr2.' ' : '')."$city $state $zip"; $q->bind_param($types,$uid,$addr_full); }
    }
    $q->execute(); $q->close();

  } elseif ($ua_line1 || $ua_city || $ua_state || $ua_zip || $ua_phone) { // multi-field schema
    if ($exists) {
      $sql="UPDATE user_addresses SET ";
      $parts=[]; $types=''; $args=[];
      if ($ua_line1){ $parts[]="line1=?"; $types.='s'; $args[]=$addr1; }
      if ($ua_line2){ $parts[]="line2=?"; $types.='s'; $args[]=$addr2; }
      if ($ua_city ){ $parts[]="city=?";  $types.='s'; $args[]=$city; }
      if ($ua_state){ $parts[]="state=?"; $types.='s'; $args[]=$state; }
      if ($ua_zip  ){ $parts[]="zip=?";   $types.='s'; $args[]=$zip; }
      if ($ua_phone){ $parts[]="phone=?"; $types.='s'; $args[]=$phone; }
      $sql.=implode(', ',$parts)." WHERE user_id=?";
      $types.='i'; $args[]=$uid;
      $q=$conn->prepare($sql);
      $q->bind_param($types, ...$args);
    } else {
      $cols=['user_id']; $vals=['?']; $types='i'; $args=[$uid];
      if ($ua_line1){ $cols[]='line1'; $vals[]='?'; $types.='s'; $args[]=$addr1; }
      if ($ua_line2){ $cols[]='line2'; $vals[]='?'; $types.='s'; $args[]=$addr2; }
      if ($ua_city ){ $cols[]='city';  $vals[]='?'; $types.='s'; $args[]=$city; }
      if ($ua_state){ $cols[]='state'; $vals[]='?'; $types.='s'; $args[]=$state; }
      if ($ua_zip  ){ $cols[]='zip';   $vals[]='?'; $types.='s'; $args[]=$zip; }
      if ($ua_phone){ $cols[]='phone'; $vals[]='?'; $types.='s'; $args[]=$phone; }
      $q=$conn->prepare("INSERT INTO user_addresses (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
      $q->bind_param($types, ...$args);
    }
    $q->execute(); $q->close();
  }
}

echo "OK: test customer ready — $email / $pass";

