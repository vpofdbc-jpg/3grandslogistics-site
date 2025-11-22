<?php
// /admin/packages.php — normalized counts + simple list
declare(strict_types=1);
session_start();

ini_set('display_errors','1');
error_reporting(E_ALL);

if (empty($_SESSION['admin_id'])) { header('Location: /admin/login.php'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
$conn->set_charset('utf8mb4');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $c,string $t,string $col): bool {
  try { $r=$c->query("SHOW COLUMNS FROM `$t` LIKE '".$c->real_escape_string($col)."'"); $ok=(bool)$r->num_rows; if($r)$r->close(); return $ok; }
  catch(Throwable){ return false; }
}

/* ---- counts (normalized) ---- */
$counts = ['At Facility'=>0,'Stowed'=>0,'Ready for Pickup'=>0,'Out for Delivery'=>0,'Delivered'=>0];
try{
  $rs = $conn->query("
    SELECT LOWER(TRIM(`status`)) AS k, COUNT(*) c
    FROM packages
    GROUP BY LOWER(TRIM(`status`))
  ");
  $map = [
    'at facility'=>'At Facility','at_facility'=>'At Facility','arrived'=>'At Facility',
    'stowed'=>'Stowed',
    'ready for pickup'=>'Ready for Pickup','ready_for_pickup'=>'Ready for Pickup','ready'=>'Ready for Pickup',
    'out for delivery'=>'Out for Delivery','out_for_delivery'=>'Out for Delivery','out'=>'Out for Delivery',
    'delivered'=>'Delivered'
  ];
  while($row=$rs->fetch_assoc()){
    $canon = $map[$row['k']] ?? null;
    if ($canon && isset($counts[$canon])) $counts[$canon] += (int)$row['c'];
  }
  if($rs) $rs->close();
}catch(Throwable $e){}

/* ---- list (no hidden filters) ---- */
$cols = ['id','user_id','tracking','status','created_at','updated_at'];
if (has_col($conn,'packages','carrier')) $cols[]='carrier';
if (has_col($conn,'packages','bin'))     $cols[]='bin';
if (has_col($conn,'packages','ready_at'))      $cols[]='ready_at';
if (has_col($conn,'packages','out_at'))        $cols[]='out_at';
if (has_col($conn,'packages','delivered_at'))  $cols[]='delivered_at';

$rows=[];
$sql = "SELECT ".implode(',',$cols)." FROM packages ORDER BY id DESC LIMIT 200";
try{ $rs=$conn->query($sql); if($rs){ $rows=$rs->fetch_all(MYSQLI_ASSOC); $rs->close(); } }catch(Throwable $e){}

?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin • Packages</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f4f5fb;margin:0}
.top{padding:14px 20px;background:#fff;border-bottom:1px solid #e9edf3}
.nav a{display:inline-flex;gap:8px;margin-right:12px;padding:8px 12px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;font-weight:700}
.nav a.gray{background:#6b7280}
.wrap{max-width:1150px;margin:26px auto;padding:0 16px}
.tiles{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
.tile{background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:14px;text-align:center}
.tile b{font-size:26px}
table{width:100%;border-collapse:collapse;margin-top:14px;background:#fff;border:1px solid #e9edf3;border-radius:12px}
th,td{padding:10px;border-bottom:1px solid #eef1f6;text-align:left;font-size:14px}
th{background:#fafbfe}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#eef2ff;color:#27336b}
</style>

<div class="top">
  <div class="nav">
    <a href="/admin/dashboard.php">🏠 Dashboard</a>
    <a href="/admin/intake.php">➕ Intake</a>
    <a href="/admin/stow.php">🗃️ Stow</a>
    <a class="gray" href="/admin/logout.php">Logout</a>
  </div>
</div>

<div class="wrap">
  <h2 style="margin:6px 0 10px">Packages</h2>
  <div class="tiles">
    <div class="tile">At Facility<br><b><?= (int)$counts['At Facility'] ?></b></div>
    <div class="tile">Stowed<br><b><?= (int)$counts['Stowed'] ?></b></div>
    <div class="tile">Ready<br><b><?= (int)$counts['Ready for Pickup'] ?></b></div>
    <div class="tile">Out for Delivery<br><b><?= (int)$counts['Out for Delivery'] ?></b></div>
    <div class="tile">Delivered<br><b><?= (int)$counts['Delivered'] ?></b></div>
  </div>

  <div style="overflow-x:auto">
    <table>
      <tr>
        <th>ID</th><th>User</th><th>Tracking</th><th>Status</th>
        <?php if(in_array('carrier',$cols,true)): ?><th>Carrier</th><?php endif; ?>
        <?php if(in_array('bin',$cols,true)): ?><th>Bin</th><?php endif; ?>
        <th>Created</th><th>Updated</th>
        <?php if(in_array('ready_at',$cols,true)): ?><th>Ready</th><?php endif; ?>
        <?php if(in_array('out_at',$cols,true)): ?><th>Out</th><?php endif; ?>
        <?php if(in_array('delivered_at',$cols,true)): ?><th>Delivered</th><?php endif; ?>
      </tr>
      <?php if(empty($rows)): ?>
        <tr><td colspan="12">No packages yet.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= (int)$r['user_id'] ?></td>
          <td><?= h($r['tracking']??'') ?></td>
          <td><span class="badge"><?= h($r['status']??'') ?></span></td>
          <?php if(isset($r['carrier'])): ?><td><?= h($r['carrier']??'') ?></td><?php endif; ?>
          <?php if(isset($r['bin'])): ?><td><?= h($r['bin']??'') ?></td><?php endif; ?>
          <td><?= h($r['created_at']??'') ?></td>
          <td><?= h($r['updated_at']??'') ?></td>
          <?php if(isset($r['ready_at'])): ?><td><?= h($r['ready_at']??'') ?></td><?php endif; ?>
          <?php if(isset($r['out_at'])): ?><td><?= h($r['out_at']??'') ?></td><?php endif; ?>
          <?php if(isset($r['delivered_at'])): ?><td><?= h($r['delivered_at']??'') ?></td><?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</div>
