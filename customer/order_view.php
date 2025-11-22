<?php
// /customer/order_view.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

/* db */
$paths=[dirname(__DIR__).'/db.php', $_SERVER['DOCUMENT_ROOT'].'/db.php'];
$ok=false; foreach($paths as $p){ if(is_file($p)){ require $p; $ok=true; break; } }
if(!$ok) die('db.php not found');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if(!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
if($conn instanceof mysqli) $conn->set_charset('utf8mb4');

if (empty($_SESSION['user_id'])) { header('Location:/customer/login.php'); exit; }
$userId  = (int)$_SESSION['user_id'];
$orderId = max(1,(int)($_GET['order_id'] ?? 0));

function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }

/* latest driver status row */
$hasUpdatedAt=false;
if($r=$conn->query("SHOW COLUMNS FROM order_driver LIKE 'updated_at'")){ $hasUpdatedAt=(bool)$r->num_rows; $r->close(); }
$driverJoin = $hasUpdatedAt
  ? "LEFT JOIN (
       SELECT s.*
       FROM order_driver s
       JOIN (SELECT order_id, MAX(updated_at) mx FROM order_driver GROUP BY order_id) x
         ON x.order_id=s.order_id AND s.updated_at=x.mx
     ) od ON od.order_id=o.order_id"
  : "LEFT JOIN order_driver od ON od.order_id=o.order_id";

/* order + people */
$sql = "
  SELECT
    o.order_id, o.user_id, o.driver_id,
    o.status AS order_status,
    o.created_at, o.package_size, o.pickup_address, o.delivery_address,
    od.status AS driver_status,
    u.name AS customer_name, u.email AS customer_email,
    d.name AS driver_name, d.email AS driver_email
  FROM orders o
  $driverJoin
  LEFT JOIN users   u ON u.id=o.user_id
  LEFT JOIN drivers d ON d.id=o.driver_id
  WHERE o.order_id=? AND o.user_id=?
  LIMIT 1";
$st=$conn->prepare($sql); $st->bind_param('ii',$orderId,$userId); $st->execute();
$row=$st->get_result()->fetch_assoc(); $st->close();
if(!$row){ http_response_code(404); echo "Order not found."; exit; }

/* unified status */
$ord=(string)($row['order_status']??''); if($ord==='') $ord='Pending';
$drv=(string)($row['driver_status']??'');
$seq=['Pending'=>0,'Assigned'=>1,'Accepted'=>2,'PickedUp'=>3,'In Transit'=>4,'Delivered'=>5,'Cancelled'=>99];
$curr = ($drv!=='' && ($seq[$drv]??-1) > ($seq[$ord]??-1)) ? $drv : $ord;

/* POD meta + URL normalize */
$pod=['photo'=>'','time'=>'','receiver'=>''];
$st=$conn->prepare("SELECT meta_key,meta_value
                    FROM order_meta
                    WHERE order_id=? AND meta_key IN('pod_photo','pod_time','pod_receiver')");
$st->bind_param('i',$orderId); $st->execute();
$res=$st->get_result();
while($m=$res->fetch_assoc()){
  if($m['meta_key']==='pod_photo')   $pod['photo']   =(string)$m['meta_value'];
  if($m['meta_key']==='pod_time')    $pod['time']    =(string)$m['meta_value'];
  if($m['meta_key']==='pod_receiver')$pod['receiver']=(string)$m['meta_value'];
}
$st->close();

/* normalize to a web URL if needed */
$photoUrl='';
if($pod['photo']!==''){
  $v=trim($pod['photo']);
  $doc=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
  if($doc && str_starts_with($v,$doc)) $v=substr($v,strlen($doc));      // strip filesystem prefix
  if($v!=='' && !preg_match('~^https?://~i',$v)) $v='/'.ltrim($v,'/');  // ensure leading slash
  $photoUrl=$v;
}

/* steps */
$ORDER=['Assigned'=>1,'Accepted'=>2,'PickedUp'=>3,'In Transit'=>4,'Delivered'=>5];
$pos=$ORDER[$curr]??0;
function step_class(string $name,array $ORDER,int $pos):string{
  $s=$ORDER[$name]??999; if($pos>$s) return 'on'; if($pos===$s) return 'active'; return 'future';
}

/* back link */
$DASH = is_file(__DIR__.'/dashboard.php') ? '/customer/dashboard.php' : '/dashboard.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Order #<?= (int)$row['order_id'] ?> • Status</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bd:#e9edf3;}
  body{font-family:Arial,Helvetica,sans-serif;background:#f5f6fb;margin:0;color:#111827}
  .wrap{max-width:920px;margin:26px auto;padding:0 16px}
  .card{background:#fff;border:1px solid var(--bd);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);margin-bottom:16px}
  .head{padding:14px 16px;border-bottom:1px solid #f0f2f7;font-weight:700}
  .body{padding:16px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .row{display:flex;gap:8px;margin:6px 0}.label{width:120px;color:#555}
  .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700}
  .b-assigned{background:#e7f1ff;color:#0a58ca}
  .b-picked{background:#fff3cd;color:#7a5e00}
  .b-transit{background:#cff4fc;color:#055160}
  .b-done{background:#d1e7dd;color:#0f5132}
  .b-cancel{background:#f8d7da;color:#842029}
  .steps{display:flex;flex-direction:column;gap:6px;margin-top:6px}
  .step{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#eef1f6;color:#555;border:1px solid #dfe3ea;min-width:120px;text-align:center}
  .step.on{background:#d1e7dd;color:#0f5132;border-color:#bcdcc5}
  .step.active{background:#0d6efd;color:#fff;border-color:#0a58ca}
  .pod a{color:#0d6efd;text-decoration:none;font-weight:700}
  .pod img{max-width:100%;height:auto;border:1px solid #ddd;border-radius:8px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="head">Order #<?= (int)$row['order_id'] ?></div>
      <div class="body grid">
        <div>
          <div class="row"><div class="label">Created</div><div><?= h($row['created_at']) ?></div></div>
          <div class="row"><div class="label">Package</div><div><?= h($row['package_size']) ?></div></div>
          <div class="row"><div class="label">Order Status</div><div><?= h($ord) ?></div></div>
          <div class="row"><div class="label">Customer</div><div><?= h($row['customer_name'] ?: '—') ?><?php if(!empty($row['customer_email'])): ?> &lt;<?= h($row['customer_email']) ?>&gt;<?php endif; ?></div></div>
        </div>
        <div>
          <div class="row"><div class="label">Pickup</div><div><?= h($row['pickup_address']) ?></div></div>
          <div class="row"><div class="label">Destination</div><div><?= h($row['delivery_address']) ?></div></div>
          <div class="row">
            <div class="label">Driver</div>
            <div>
              <?php if (!empty($row['driver_name'])): ?>
                <?= h($row['driver_name']) ?> &lt;<?= h($row['driver_email']) ?>&gt;
              <?php else: ?>
                <span style="color:#777">Not yet assigned</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="head">Driver Progress</div>
      <div class="body">
        <?php
          $ds = $curr==='Pending' ? 'Assigned' : $curr;
          $cls = 'badge '.(
            $ds==='PickedUp' ? 'b-picked' :
            ($ds==='In Transit' ? 'b-transit' :
             ($ds==='Delivered' ? 'b-done' :
              ($ds==='Cancelled' ? 'b-cancel' : 'b-assigned'))));
        ?>
        <div class="<?= $cls ?>" style="margin-bottom:10px;display:inline-block;"><?= h($ds) ?></div>

        <div class="steps">
          <span class="step <?= step_class('Accepted',   $ORDER, $pos) ?>">Accepted</span>
          <span class="step <?= step_class('PickedUp',   $ORDER, $pos) ?>">PickedUp</span>
          <span class="step <?= step_class('In Transit', $ORDER, $pos) ?>">In Transit</span>
          <span class="step <?= step_class('Delivered',  $ORDER, $pos) ?>">Delivered</span>
        </div>

        <?php if ($photoUrl): ?>
          <div class="pod" style="margin-top:14px;">
            POD: <a href="<?= h($photoUrl) ?>" target="_blank" rel="noopener">View</a>
            <?php if (!empty($pod['time']) || !empty($pod['receiver'])): ?>
              <span style="color:#666;font-size:12px;">
                (<?= h(trim(($pod['receiver']??'').' '.$pod['time'])) ?>)
              </span>
            <?php endif; ?>
            <div style="margin-top:8px;">
              <img src="<?= h($photoUrl) ?>" alt="Proof of Delivery">
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div style="text-align:right;max-width:920px;margin:8px auto;">
      <a href="<?= $DASH ?>" style="text-decoration:none;">← Back to Dashboard</a>
    </div>
  </div>
</body>
</html>





