<?php
// /driver/scan_label.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['driver_id'])) { header('Location:/driver/login.php'); exit; }
$driverId = (int)$_SESSION['driver_id'];

/* DB bootstrap */
$paths=[dirname(__DIR__).'/db.php', $_SERVER['DOCUMENT_ROOT'].'/db.php'];
$ok=false; foreach($paths as $p){ if(is_file($p)){ require $p; $ok=true; break; } }
if(!$ok) die('db.php not found');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

/* helpers */
function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }
function good(string $m){ return ['ok'=>true,'msg'=>$m]; }
function bad (string $m){ return ['ok'=>false,'msg'=>$m]; }

/* tiny meta upsert for synonyms */
function meta_set(mysqli $c, int $orderId, string $k, string $v): void {
  try{
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
  }catch(Throwable $e){
    try{ $st=$c->prepare("DELETE FROM order_meta WHERE order_id=? AND meta_key=?");
         $st->bind_param('is',$orderId,$k); $st->execute(); $st->close(); }catch(Throwable $e2){}
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value) VALUES (?,?,?)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
  }
}

/* phase tabs */
$phase = strtolower((string)($_GET['phase'] ?? $_POST['phase'] ?? 'office'));
$PHASES = ['office','pickup','delivery'];
if(!in_array($phase,$PHASES,true)) $phase='office';
$SELF = '/driver/scan_label.php';

/* -------- QR helpers -------- */
function fetch_saved_code(mysqli $c, int $orderId): string {
  // Prefer newest by meta_id/id/created_at; fall back to meta_value (legacy)
  $orderBy = 'meta_value DESC';
  if ($r=$c->query("SHOW COLUMNS FROM order_meta LIKE 'meta_id'"))     { if($r->num_rows){ $orderBy='meta_id DESC'; } $r->close(); }
  if ($r=$c->query("SHOW COLUMNS FROM order_meta LIKE 'id'"))          { if($r->num_rows){ $orderBy='id DESC'; } $r->close(); }
  if ($r=$c->query("SHOW COLUMNS FROM order_meta LIKE 'created_at'"))  { if($r->num_rows){ $orderBy='created_at DESC'; } $r->close(); }

  $sql = "SELECT meta_value FROM order_meta
          WHERE order_id=? AND meta_key IN ('label_code','labelId','label_code_v1')
          ORDER BY $orderBy LIMIT 1";
  $st=$c->prepare($sql); $st->bind_param('i',$orderId); $st->execute();
  $val = (string)($st->get_result()->fetch_column() ?? '');
  $st->close();
  return trim($val);
}

function save_scan_event(mysqli $c, int $orderId, int $driverId, string $phase, string $code): void {
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM order_scan_events")){ while($cc=$r->fetch_assoc()) $cols[$cc['Field']]=true; $r->close(); }
  if(!$cols) return;
  $fields=[]; $ph=[]; $types=''; $vals=[];
  $nowCol = isset($cols['scanned_at']) ? 'scanned_at' : (isset($cols['created_at'])?'created_at':null);

  if(isset($cols['order_id']))  {$fields[]='order_id';  $ph[]='?'; $types.='i'; $vals[]=$orderId;}
  if(isset($cols['driver_id'])) {$fields[]='driver_id'; $ph[]='?'; $types.='i'; $vals[]=$driverId;}

  if(isset($cols['phase']))       {$fields[]='phase';     $ph[]='?'; $types.='s'; $vals[]=$phase;}
  elseif(isset($cols['location'])){$fields[]='location';  $ph[]='?'; $types.='s'; $vals[]=$phase;}
  elseif(isset($cols['type']))    {$fields[]='type';      $ph[]='?'; $types.='s'; $vals[]=$phase;}

  if(isset($cols['code']))           {$fields[]='code';        $ph[]='?'; $types.='s'; $vals[]=$code;}
  elseif(isset($cols['label_code'])) {$fields[]='label_code';  $ph[]='?'; $types.='s'; $vals[]=$code;}
  elseif(isset($cols['token']))      {$fields[]='token';       $ph[]='?'; $types.='s'; $vals[]=$code;}

  if($nowCol){ $fields[]=$nowCol; $ph[]='NOW()'; }

  if($fields){
    $sql="INSERT INTO order_scan_events (".implode(',',$fields).") VALUES (".implode(',',$ph).")";
    $st=$c->prepare($sql);
    if (strpos($sql,'?')!==false) $st->bind_param($types, ...$vals);
    $st->execute(); $st->close();
  }
}

function set_phase_meta(mysqli $c, int $orderId, string $phase): void {
  $now = date('Y-m-d H:i:s');
  $key='scan_'.$phase;
  meta_set($c,$orderId,$key,$now);
  if ($phase==='office') {
    meta_set($c,$orderId,'label_checked_out_at',$now);
  } elseif ($phase==='delivery') {
    meta_set($c,$orderId,'delivered_at',$now);
  }
}

/* -------- STATUS PROPAGATION -------- */
function status_rank(string $s): int {
  $map=['Pending'=>0,'Assigned'=>1,'Accepted'=>2,'PickedUp'=>3,'In Transit'=>4,'Delivered'=>5,'Cancelled'=>99];
  return $map[$s] ?? -1;
}
function target_status_for_phase(string $phase): ?string {
  if ($phase==='office')   return 'Accepted';
  if ($phase==='pickup')   return 'PickedUp';
  if ($phase==='delivery') return 'Delivered';
  return null;
}

/* SAFE upsert into order_driver: UPDATE existing row for order_id, else INSERT */
function append_order_driver_status(mysqli $c, int $orderId, int $driverId, string $status): void {
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM order_driver")){ while($cc=$r->fetch_assoc()) $cols[$cc['Field']]=true; $r->close(); }
  if(!$cols) return;

  $orderCol  = isset($cols['order_id'])  ? 'order_id'  : (isset($cols['orderid'])  ? 'orderid'  : null);
  if(!$orderCol) return;
  $driverCol = isset($cols['driver_id']) ? 'driver_id' : (isset($cols['driverid']) ? 'driverid' : null);
  $statusCol = isset($cols['status'])    ? 'status'    : null;
  $updCol    = isset($cols['updated_at'])? 'updated_at': (isset($cols['updated']) ? 'updated' : null);
  $crtCol    = isset($cols['created_at'])? 'created_at': (isset($cols['created']) ? 'created' : null);

  // UPDATE
  $set=[]; $types=''; $vals=[];
  if($driverCol){ $set[]="$driverCol=?"; $types.='i'; $vals[]=$driverId; }
  if($statusCol){ $set[]="$statusCol=?"; $types.='s'; $vals[]=$status; }
  if($updCol){ $set[]="$updCol=NOW()"; }
  $updated=0;
  if($set){
    $sql="UPDATE order_driver SET ".implode(',',$set)." WHERE $orderCol=? LIMIT 1";
    $types.='i'; $vals[]=$orderId;
    $st=$c->prepare($sql); $st->bind_param($types, ...$vals); $st->execute();
    $updated=$st->affected_rows; $st->close();
  }
  // INSERT if nothing updated
  if($updated===0){
    $fields=[$orderCol]; $ph=['?']; $types='i'; $vals=[$orderId];
    if($driverCol){ $fields[]=$driverCol; $ph[]='?'; $types.='i'; $vals[]=$driverId; }
    if($statusCol){ $fields[]=$statusCol; $ph[]='?'; $types.='s'; $vals[]=$status; }
    if($updCol){ $fields[]=$updCol; $ph[]='NOW()'; }
    if($crtCol){ $fields[]=$crtCol; $ph[]='NOW()'; }
    $sql="INSERT INTO order_driver (".implode(',',$fields).") VALUES (".implode(',',$ph).")";
    $st=$c->prepare($sql);
    if (strpos($sql,'?')!==false) $st->bind_param($types, ...$vals);
    $st->execute(); $st->close();
  }
}

function bump_status_if_needed(mysqli $c, int $orderId, int $driverId, string $phase): ?string {
  $st=$c->prepare("SELECT status, COALESCE(driver_id,0) AS driver_id FROM orders WHERE order_id=? LIMIT 1");
  $st->bind_param('i',$orderId); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();
  if(!$row) return null;

  $curr = trim((string)($row['status'] ?? ''));
  if ($curr==='') $curr='Pending';
  if ($curr==='Delivered' || $curr==='Cancelled') return null;

  $target = target_status_for_phase($phase);
  if (!$target) return null;
  if (status_rank($target) <= status_rank($curr)) return null;

  // discover orders columns
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM orders")){ while($cc=$r->fetch_assoc()) $cols[$cc['Field']]=true; $r->close(); }

  $set = ["status=?"]; $types='s'; $vals=[$target];
  if(isset($cols['updated_at'])) $set[]="updated_at=NOW()";
  if($target==='Accepted'){
    foreach(['accepted_at','accepted_time'] as $k){ if(isset($cols[$k])) { $set[]="$k=NOW()"; break; } }
  }
  if($target==='PickedUp'){
    foreach(['picked_at','picked_up_at','pickup_at','pickedup_at'] as $k){ if(isset($cols[$k])) { $set[]="$k=NOW()"; break; } }
  }
  if($target==='Delivered'){
    foreach(['delivered_at','delivered_time'] as $k){ if(isset($cols[$k])) { $set[]="$k=NOW()"; break; } }
  }
  $curDriver = (int)$row['driver_id'];
  if ($curDriver===0 && isset($cols['driver_id'])) { $set[]="driver_id=?"; $types.='i'; $vals[]=$driverId; }

  $sql="UPDATE orders SET ".implode(',', $set)." WHERE order_id=? LIMIT 1";
  $types.='i'; $vals[]=$orderId;
  $st=$c->prepare($sql); $st->bind_param($types, ...$vals); $st->execute(); $st->close();

  append_order_driver_status($c, $orderId, $driverId, $target);

  return $target;
}

/* -------- Summary blocks -------- */
function scan_event_cols(mysqli $c): array {
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM order_scan_events")){
    while($x=$r->fetch_assoc()) $cols[$x['Field']]=true; $r->close();
  }
  $time = null; foreach (['scanned_at','created_at','timestamp','ts'] as $k) if(isset($cols[$k])) { $time=$k; break; }
  $code = null; foreach (['code','label_code','token'] as $k) if(isset($cols[$k])) { $code=$k; break; }
  $phase= null; foreach (['phase','location','type'] as $k) if(isset($cols[$k])) { $phase=$k; break; }
  return [$time,$code,$phase];
}
function fetch_recent_scans(mysqli $c, int $orderId): array {
  [$tCol,$cCol,$pCol] = scan_event_cols($c);
  $selPhase = $pCol ? "$pCol AS phase" : "'' AS phase";
  $selCode  = $cCol ? "$cCol AS code"  : "'' AS code";
  $selTime  = $tCol ? "$tCol AS t"     : "NOW() AS t";
  $orderBy  = $tCol ?: 't';

  $sql = "SELECT $selPhase, $selCode, $selTime
          FROM order_scan_events WHERE order_id=?
          ORDER BY $orderBy DESC LIMIT 10";
  $st=$c->prepare($sql); $st->bind_param('i',$orderId); $st->execute();
  $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close(); return $rows;
}
function fetch_order_summary(mysqli $c, int $orderId): array {
  $sql = "SELECT o.order_id, o.pickup_address, o.delivery_address, o.created_at,
                 COALESCE(o.status,'') AS order_status,
                 u.name  AS customer_name, u.email AS customer_email,
                 d.name  AS driver_name,   d.email AS driver_email
          FROM orders o
          LEFT JOIN users   u ON u.id=o.user_id
          LEFT JOIN drivers d ON d.id=o.driver_id
          WHERE o.order_id=? LIMIT 1";
  $st=$c->prepare($sql); $st->bind_param('i',$orderId);
  $st->execute(); $row=$st->get_result()->fetch_assoc() ?: [];
  $st->close(); return $row;
}

/* -------- Process scan -------- */
$result=null; $order=null; $scans=[]; $statusBumped=null;
$incoming=(string)($_GET['code'] ?? $_POST['code'] ?? '');
if($incoming!==''){
  if (stripos($incoming,'code=')!==false){
    parse_str(parse_url($incoming, PHP_URL_QUERY) ?? '', $q);
    $incoming=(string)($q['code'] ?? $incoming);
  }
  $code=trim($incoming);
  $orderId=0;
  if (preg_match('~^(\d+)-~',$code,$m)) $orderId=(int)$m[1];
  elseif(ctype_digit($code)) $orderId=(int)$code;

  if ($orderId<=0) $result=bad('Could not detect Order ID in the code.');
  else{
    $st=$conn->prepare("SELECT order_id FROM orders WHERE order_id=? LIMIT 1");
    $st->bind_param('i',$orderId); $st->execute();
    $row=$st->get_result()->fetch_assoc(); $st->close();
    if(!$row) $result=bad('Order not found.');
    else{
      $saved=fetch_saved_code($conn,$orderId);
      if ($saved==='' || strcasecmp($saved,$code)!==0) $result=bad('Label code is not recognized for this order.');
      else{
        save_scan_event($conn,$orderId,$driverId,$phase,$code);
        set_phase_meta($conn,$orderId,$phase);
        $statusBumped = bump_status_if_needed($conn,$orderId,$driverId,$phase);

        $msg='Scan recorded for Order #'.$orderId.' ('.$phase.').';
        if ($statusBumped) $msg .= ' Status updated to '.$statusBumped.'.';
        $result=good($msg);

        $order = fetch_order_summary($conn, $orderId);
        $scans = fetch_recent_scans($conn, $orderId);
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Scan Label — <?= h(ucfirst($phase)) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f6f7fb;margin:0}
.wrap{max-width:900px;margin:20px auto;padding:0 14px}
.card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);padding:16px}
.tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.tab{padding:8px 12px;border-radius:999px;border:1px solid #dfe3ea;text-decoration:none;color:#111;background:#fff}
.tab.active{background:#0d6efd;color:#fff;border-color:#0b5ed7}
.row{display:flex;gap:10px;align-items:center;margin:8px 0;flex-wrap:wrap}
.label{width:120px;color:#555}
input[type=text]{flex:1;min-width:240px;padding:10px;border:1px solid #ced4da;border-radius:8px}
button{padding:10px 14px;border:0;border-radius:8px;background:#0d6efd;color:#fff;font-weight:700;cursor:pointer}
.msg{padding:10px 12px;border-radius:8px;margin-bottom:12px}
.msg.ok{background:#d1e7dd;color:#0f5132;border:1px solid #bcdcc5}
.msg.err{background:#f8d7da;color:#842029;border:1px solid #f1aeb5}
.small{color:#666;font-size:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:6px 4px;border-bottom:1px solid #eef2f7;text-align:left}
#reader{width:100%;max-width:560px}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="tabs">
        <?php foreach($PHASES as $ph): ?>
          <a class="tab <?= $phase===$ph?'active':'' ?>" href="<?= h($SELF.'?phase='.$ph) ?>"><?= h(ucfirst($ph)) ?></a>
        <?php endforeach; ?>
      </div>

      <h3 style="margin:0 0 10px">Scan Label — <?= h(ucfirst($phase)) ?></h3>

      <?php if($result!==null): ?>
        <div class="msg <?= $result['ok']?'ok':'err' ?>"><?= h($result['msg']) ?></div>
      <?php endif; ?>

      <?php if (!empty($order)): ?>
        <div class="card" style="margin:10px 0">
          <div style="font-weight:700;margin-bottom:6px">Order #<?= (int)$order['order_id'] ?> Summary</div>
          <div class="row"><div class="label">Customer</div>
            <div><?= h($order['customer_name'] ?: '—') ?><?= $order['customer_email'] ? ' &lt;'.h($order['customer_email']).'&gt;' : '' ?></div>
          </div>
          <div class="row"><div class="label">Driver</div>
            <div><?= h($order['driver_name'] ?: '—') ?><?= $order['driver_email'] ? ' &lt;'.h($order['driver_email']).'&gt;' : '' ?></div>
          </div>
          <div class="row"><div class="label">Pickup</div><div><?= h($order['pickup_address'] ?: '—') ?></div></div>
          <div class="row"><div class="label">Destination</div><div><?= h($order['delivery_address'] ?: '—') ?></div></div>
          <div class="row"><div class="label">Order Status</div><div><?= h($order['order_status'] ?: 'Pending') ?></div></div>
        </div>

        <?php if (!empty($scans)): ?>
          <div class="card" style="margin:10px 0">
            <div style="font-weight:700;margin-bottom:6px">Recent Scans</div>
            <table>
              <tr><th>When</th><th>Phase</th><th>Code</th></tr>
              <?php foreach($scans as $s): ?>
                <tr>
                  <td><?= h(date('Y-m-d H:i', strtotime((string)$s['t']))) ?></td>
                  <td><?= h(ucfirst((string)($s['phase'] ?? ''))) ?></td>
                  <td><code><?= h((string)($s['code'] ?? '')) ?></code></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <form class="row" method="post" action="<?= h($SELF) ?>" style="margin-top:10px">
        <input type="hidden" name="phase" value="<?= h($phase) ?>">
        <input type="text" name="code" placeholder="Paste or type the label code (e.g. 74-xxxxxxxx...)" value="">
        <button type="submit">Submit</button>
      </form>

      <details style="margin-top:10px" open>
        <summary><strong>Scan with camera</strong></summary>
        <div class="small" id="libStatus">Scanner ready. Tap “Start camera”.</div>
        <div style="margin:8px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap">
          <select id="camSelect" style="min-width:220px;padding:8px;border:1px solid #ced4da;border-radius:8px">
            <option value="">(Pick camera…)</option>
          </select>
          <button id="startBtn" type="button">Start camera</button>
          <button id="stopBtn"  type="button" style="background:#6c757d;margin-left:6px">Stop</button>
          <button id="flashBtn" type="button" style="background:#6c757d;display:none;margin-left:6px">Flash</button>
        </div>
        <div id="reader"></div>
      </details>

      <div style="margin-top:14px" class="small">
        Tip: Labels encode a URL like <code>/driver/scan_label.php?code=&lt;orderId-token&gt;</code>.
        You can also paste that full URL into the box above.
      </div>
    </div>

    <div class="small" style="text-align:right;margin-top:8px">
      <a href="/driver/dashboard.php">← Back to Dashboard</a>
    </div>
  </div>

  <!-- Local scanner library -->
  <script src="/tools/html5-qrcode.min.js?v=2.3.8"></script>
  <script>
  (() => {
    const SELF  = <?= json_encode($SELF) ?>;
    const PHASE = <?= json_encode($phase) ?>;

    const el = id => document.getElementById(id);
    const status = m => el('libStatus').textContent = m;

    const startBtn = el('startBtn'), stopBtn = el('stopBtn'), camSel = el('camSelect'), flashBtn = el('flashBtn');
    let qr = null, running = false, chosenId = null, wakeLock=null, torchOn=false;

    // --- Keep screen awake while scanning ---
    async function keepAwake(on){
      try{
        if(on){ wakeLock = await navigator.wakeLock?.request('screen'); }
        else  { await wakeLock?.release(); wakeLock=null; }
      }catch(_){}
    }
    document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState==='visible' && running) keepAwake(true); });

    // --- Torch toggle (if supported) ---
    async function toggleTorch(on){
      try{
        const v = document.querySelector('#reader video');
        if(!v?.srcObject){ return; }
        const track = v.srcObject.getVideoTracks?.()[0];
        const caps = track?.getCapabilities?.();
        if(!caps?.torch){ flashBtn.style.display='none'; return; }
        await track.applyConstraints({advanced:[{torch: !!on}]});
        torchOn = !!on;
        flashBtn.textContent = torchOn ? 'Flash Off' : 'Flash';
      }catch(_){}
    }
    flashBtn.addEventListener('click', ()=>toggleTorch(!torchOn));

    // Camera list
    async function loadCameras(){
      if (!window.Html5Qrcode) { status('Scanner script not loaded.'); return; }
      try{
        const cams = await Html5Qrcode.getCameras();
        if (!cams?.length) { status('No cameras found.'); return; }
        cams.sort((a,b) => (/(back|rear|environment)/i.test(b.label) - /(back|rear|environment)/i.test(a.label)));
        camSel.innerHTML = '<option value="">(Pick camera…)</option>' +
          cams.map(c=>`<option value="${c.id}">${c.label || c.id}</option>`).join('');
        camSel.value = cams[0].id; chosenId = cams[0].id;
      }catch(e){ status('Could not enumerate cameras. Grant camera permission first.'); }
    }
    camSel.addEventListener('change', () => { chosenId = camSel.value || null; });

    function onScanSuccess(txt){
      try{
        try{ navigator.vibrate?.(40); new Audio('/assets/beep.mp3').play().catch(()=>{}); }catch(_){}
        let code = '';
        if (/\bcode=/.test(txt)) {
          const u = new URL(txt, location.origin);
          code = (u.searchParams.get('code') || '').trim();
        } else {
          code = txt.trim();
        }
        if (!code) { alert('Scanned but no code found.\nRaw: ' + txt); return; }
        const url = new URL(SELF, location.origin);
        url.searchParams.set('phase', PHASE);
        url.searchParams.set('code', code);
        location.href = url.href;
      }catch(e){
        alert('Parse error: ' + (e?.message || e) + '\nRaw: ' + txt);
      }
    }
    function onScanFailure(){}

    function qrboxCalc(viewW, viewH){
      const side = Math.floor(Math.min(viewW, viewH) * 0.82);
      return { width: Math.max(260, Math.min(side, 420)), height: Math.max(260, Math.min(side, 420)) };
    }

    startBtn.addEventListener('click', async () => {
      if (running) return;
      if (!window.Html5Qrcode) { alert('Scanner script missing.'); return; }
      if (location.protocol !== 'https:' && location.hostname !== 'localhost'){
        alert('Camera requires HTTPS.'); return;
      }
      try{
        status('Starting camera…');
        if (!qr) qr = new Html5Qrcode('reader', { verbose: false });
        const config = {
          fps: 12,
          qrbox: qrboxCalc(window.innerWidth, window.innerHeight),
          aspectRatio: window.innerWidth / Math.max(1, window.innerHeight),
          disableFlip: true,
          experimentalFeatures: { useBarCodeDetectorIfSupported: true },
          formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ]
        };
        const camera = chosenId ? { deviceId: { exact: chosenId } } : { facingMode: 'environment' };
        await qr.start(camera, config, onScanSuccess, onScanFailure);
        running = true;
        startBtn.textContent = 'Camera running';
        status('Point the square at the QR code until it detects.');
        await keepAwake(true);

        // Check capability then initialize torch state
        const v = document.querySelector('#reader video');
        const caps = v?.srcObject?.getVideoTracks?.()[0]?.getCapabilities?.();
        flashBtn.style.display = (caps && 'torch' in caps) ? 'inline-block' : 'none';
        if (caps && 'torch' in caps) { toggleTorch(false); }
      }catch(e){
        status(e?.message || 'Could not start camera.');
        alert(el('libStatus').textContent);
      }
    });

    stopBtn.addEventListener('click', async () => {
      try{
        if (qr && running){ await qr.stop(); await qr.clear(); }
        running = false;
        await keepAwake(false);
        torchOn=false; flashBtn.style.display='none';
        startBtn.textContent = 'Start camera';
        status('Camera stopped.');
      }catch(_){}
    });

    // Offline/online heads-up
    window.addEventListener('offline',()=>status('You’re offline. Scans will fail until back online.'));
    window.addEventListener('online', ()=>status('Back online. Ready to scan.'));

    // Clean up on unload
    window.addEventListener('beforeunload', ()=>{ try{ if(running && qr){ qr.stop(); } }catch(_){} keepAwake(false); });

    loadCameras();
  })();
  </script>

  <!-- Single keep-alive (every 2 minutes) -->
  <script>
  (function keepAlive(){
    const ping = () => fetch('/driver/keepalive.php', {cache:'no-store'}).catch(()=>{});
    ping();
    setInterval(ping, 120000);
  })();
  </script>
</body>
</html>


