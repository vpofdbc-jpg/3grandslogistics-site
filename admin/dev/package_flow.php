<?php
// admin/dev/package_flow.php — quick package lifecycle simulator
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_id'])) {
  header('Location:/admin/login.php?next='.rawurlencode($_SERVER['REQUEST_URI']));
  exit;
}
ini_set('display_errors','1'); error_reporting(E_ALL);

require dirname(__DIR__,2).'/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }
function has_col(mysqli $c,string $t,string $col):bool{
  $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '{$c->real_escape_string($col)}'");
  $ok=(bool)$r->num_rows; if($r)$r->close(); return $ok;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));

$email = trim($_GET['email'] ?? 'cust1@example.com');
$msg = '';

/* ensure user exists (very safe insert) */
$st=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$st->bind_param('s',$email); $st->execute();
$uid = ($st->get_result()->fetch_assoc()['id']??0); $st->close();
if (!$uid){
  $name="Dev User (".$email.")";
  $sql="INSERT INTO users (name,email".(has_col($conn,'users','pass_hash')?',pass_hash':'').") VALUES (?,? ".(has_col($conn,'users','pass_hash')?',?':'').")";
  $ins=$conn->prepare($sql);
  if (has_col($conn,'users','pass_hash')){
    $hash=password_hash('TestPass123', PASSWORD_DEFAULT);
    $ins->bind_param('sss',$name,$email,$hash);
  }else{
    $ins->bind_param('ss',$name,$email);
  }
  $ins->execute(); $uid=$ins->insert_id; $ins->close();
  $msg.="Created test user #$uid. ";
}

/* latest package for this user */
function latest_pkg(mysqli $c,int $uid):?array{
  try{
    $st=$c->prepare("SELECT id,tracking,status,location,created_at,updated_at FROM packages WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->bind_param('i',$uid); $st->execute();
    $r=$st->get_result()->fetch_assoc(); $st->close();
    return $r?:null;
  }catch(Throwable){ return null; }
}
$pkg = latest_pkg($conn,$uid);

/* actions */
if ($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['csrf'], $_POST['csrf']??'')){
  $act = $_POST['action'] ?? '';
  if ($act==='create'){
    // dynamic insert based on available columns
    $cols=['user_id']; $vals=['?']; $types='i'; $args=[&$uid];
    $tracking = 'DEV'.mt_rand(100000,999999);
    if (has_col($conn,'packages','tracking')){ $cols[]='tracking'; $vals[]='?'; $types.='s'; $args[]=&$tracking; }
    $status = 'At Facility';
    if (has_col($conn,'packages','status')){ $cols[]='status'; $vals[]='?'; $types.='s'; $args[]=&$status; }
    $loc = 'Warehouse';
    if (has_col($conn,'packages','location')){ $cols[]='location'; $vals[]='?'; $types.='s'; $args[]=&$loc; }
    if (has_col($conn,'packages','created_at')){ $cols[]='created_at'; $vals[]='NOW()'; }
    if (has_col($conn,'packages','updated_at')){ $cols[]='updated_at'; $vals[]='NOW()'; }
    $sql="INSERT INTO packages (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    $st=$conn->prepare($sql);
    $st->bind_param($types, ...$args); $st->execute(); $st->close();
    $msg.="Package created.";
  } elseif ($pkg) {
    $new = '';
    $loc = null;
    if ($act==='stow')            { $new='Stowed'; $loc='Bin A1'; }
    elseif ($act==='ready')       { $new='Ready for Pickup'; }
    elseif ($act==='pickup_req')  { $new='Pickup Requested'; }
    elseif ($act==='delivery_req'){ $new='Delivery Requested'; }
    elseif ($act==='out')         { $new='Out for Delivery'; }
    elseif ($act==='delivered')   { $new='Delivered'; }
    elseif ($act==='reset')       { $new='At Facility'; $loc='Warehouse'; }
    if ($new!==''){
      $parts=[]; $types=''; $args=[];
      if (has_col($conn,'packages','status')){ $parts[]='status=?'; $types.='s'; $args[]=$new; }
      if ($loc!==null && has_col($conn,'packages','location')){ $parts[]='location=?'; $types.='s'; $args[]=$loc; }
      if (has_col($conn,'packages','updated_at')){ $parts[]='updated_at=NOW()'; }
      if ($parts){
        $sql="UPDATE packages SET ".implode(', ',$parts)." WHERE id=?";
        $types.='i'; $args[]=$pkg['id'];
        $st=$conn->prepare($sql); $st->bind_param($types, ...$args); $st->execute(); $st->close();
        $msg.="Status → $new.";
      }
    }
  }
  $pkg = latest_pkg($conn,$uid);
}

?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dev • Package Flow</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto;background:#f6f7fb;margin:0;padding:20px}
h1{margin:0 0 10px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-width:860px}
.row{margin:8px 0}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#27336b;font-weight:700}
.btn{display:inline-block;margin:4px 6px 0 0;padding:8px 12px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;border:0;cursor:pointer}
.btn.gray{background:#6b7280}.btn.orange{background:#f97316}.btn.green{background:#16a34a}
.note{background:#e7f5ff;border:1px solid #a5d8ff;color:#0b5394;padding:10px;border-radius:8px;margin:12px 0}
</style>

<div class="card">
  <h1>Package Flow (Dev)</h1>
  <div class="row"><b>User email:</b> <?= h($email) ?> &nbsp; | &nbsp; <b>User ID:</b> <?= (int)$uid ?></div>

  <?php if ($msg): ?><div class="note"><?= h($msg) ?></div><?php endif; ?>

  <div class="row">
    <?php if (!$pkg): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <button class="btn green" name="action" value="create">Create test package</button>
      </form>
    <?php else: ?>
      <div class="row">
        <b>Current package:</b> #<?= (int)$pkg['id'] ?>
        <?php if (!empty($pkg['tracking'])): ?> (Tracking: <?= h($pkg['tracking']) ?>)<?php endif; ?>
        &nbsp;•&nbsp;<span class="badge"><?= h($pkg['status'] ?? '') ?></span>
        <?php if (!empty($pkg['location'])): ?>&nbsp; @ <?= h($pkg['location']) ?><?php endif; ?>
      </div>
      <form method="post" style="margin-top:8px">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <button class="btn"        name="action" value="stow">Stow</button>
        <button class="btn"        name="action" value="ready">Ready for Pickup</button>
        <button class="btn gray"   name="action" value="pickup_req">Pickup Requested</button>
        <button class="btn orange" name="action" value="delivery_req">Delivery Requested</button>
        <button class="btn orange" name="action" value="out">Out for Delivery</button>
        <button class="btn green"  name="action" value="delivered">Delivered</button>
        <button class="btn gray"   name="action" value="reset">Reset to At Facility</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="row" style="margin-top:12px">
    <a class="btn gray" href="/customer/dashboard.php" target="_blank">Open customer dashboard</a>
  </div>
</div>

