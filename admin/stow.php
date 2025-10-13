<?php
// /admin/stow.php — assign bin slot (stow) for a package
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['admin_id'])) { header('Location:/admin/login.php'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

$msg=''; $err='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  if(!hash_equals($_SESSION['csrf'], (string)($_POST['csrf']??''))){ http_response_code(400); exit('Bad CSRF'); }
  $tracking=trim((string)($_POST['tracking']??''));
  $bin     =trim((string)($_POST['bin_code']??''));
  if($tracking===''||$bin===''){ $err='Tracking and Bin are required.'; }
  else{
    $st=$conn->prepare("SELECT id FROM packages WHERE tracking=? LIMIT 1");
    $st->bind_param('s',$tracking); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
    if(!$row){ $err='Package not found.'; }
    else{
      $pid=(int)$row['id'];
      $u=$conn->prepare("UPDATE packages SET bin_code=?, status='Stowed' WHERE id=?");
      $u->bind_param('si',$bin,$pid); $u->execute(); $u->close();
      $e=$conn->prepare("INSERT INTO package_events(package_id,event,meta) VALUES(?, 'stow', ?)");
      $meta=json_encode(['by'=>'admin#'.(int)$_SESSION['admin_id'],'bin'=>$bin]); $e->bind_param('is',$pid,$meta); $e->execute(); $e->close();
      $msg="Stowed package #$pid in bin ".h($bin).".";
    }
  }
}
?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stow Package</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
.wrap{max-width:520px;margin:24px auto;background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:16px}
label{display:block;margin:8px 0 4px;color:#555}
input{width:100%;padding:10px;border:1px solid #dfe3ea;border-radius:8px}
.btn{margin-top:12px;background:#0d6efd;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer}
.note{margin-top:10px;padding:8px;border-radius:8px}
.ok{background:#e7f5ff;border:1px solid #a5d8ff}.err{background:#fde2e1;border:1px solid #f5a097}
</style>
<div class="wrap">
  <h2>Stow a Package</h2>
  <?php if($msg): ?><div class="note ok"><?= $msg ?></div><?php endif; ?>
  <?php if($err): ?><div class="note err"><?= h($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <label>Tracking *</label><input name="tracking" autofocus>
    <label>Bin Code *</label><input name="bin_code" placeholder="e.g. A3-12">
    <button class="btn" type="submit">Save Bin</button>
  </form>
</div>
