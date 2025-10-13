<?php
// /driver/pod.php — capture POD photo; store meta (with synonyms); mark Delivered; mobile-friendly extras
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
function bad($m,$code=400){ http_response_code($code); echo '<p style="color:#b02a37">'.h($m).'</p>'; exit; }

/* meta upsert */
function meta_set(mysqli $c, int $orderId, string $k, string $v): void {
  try{
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close(); return;
  }catch(Throwable $e){
    try{ $st=$c->prepare("DELETE FROM order_meta WHERE order_id=? AND meta_key=?");
         $st->bind_param('is',$orderId,$k); $st->execute(); $st->close(); }catch(Throwable $e2){}
    $st=$c->prepare("INSERT INTO order_meta (order_id,meta_key,meta_value) VALUES (?,?,?)");
    $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
  }
}

/* reflect status in order_driver safely */
function append_order_driver_status(mysqli $c, int $orderId, int $driverId, string $status): void {
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM order_driver")){ while($cc=$r->fetch_assoc()) $cols[$cc['Field']]=true; $r->close(); }
  $where="order_id=?"; $wt='i'; $wv=[$orderId];
  if(isset($cols['driver_id'])){ $where.=" AND driver_id=?"; $wt.='i'; $wv[]=$driverId; }
  $st=$c->prepare("UPDATE order_driver SET status=?, updated_at=NOW() WHERE $where LIMIT 1");
  $st->bind_param('s'.$wt, $status, ...$wv); $st->execute();
  if($st->affected_rows===0){
    $st->close();
    $sql="INSERT INTO order_driver (order_id".(isset($cols['driver_id'])?',driver_id':'').",status,created_at,updated_at)
          VALUES (?".(isset($cols['driver_id'])?',?':'').",?,NOW(),NOW())";
    if(isset($cols['driver_id'])){
      $st=$c->prepare($sql); $st->bind_param('iis',$orderId,$driverId,$status);
    }else{
      $st=$c->prepare($sql); $st->bind_param('is',$orderId,$status);
    }
    $st->execute();
  } else { $st->close(); }
}

/* mark Delivered on orders + mirror to order_driver */
function mark_delivered(mysqli $c, int $orderId, int $driverId): void {
  $cols=[]; if($r=$c->query("SHOW COLUMNS FROM orders")){ while($cc=$r->fetch_assoc()) $cols[$cc['Field']]=true; $r->close(); }
  $set=["status=?"]; $types='s'; $vals=['Delivered'];
  if(isset($cols['delivered_at']))      $set[]="delivered_at=NOW()";
  elseif(isset($cols['delivered_time']))$set[]="delivered_time=NOW()";
  if(isset($cols['updated_at']))        $set[]="updated_at=NOW()";
  if(isset($cols['driver_id'])){ $set[]="driver_id=COALESCE(driver_id, ?)"; $types.='i'; $vals[]=$driverId; }
  $sql="UPDATE orders SET ".implode(',', $set)." WHERE order_id=? LIMIT 1";
  $types.='i'; $vals[]=$orderId;
  $st=$c->prepare($sql); $st->bind_param($types, ...$vals); $st->execute(); $st->close();
  append_order_driver_status($c,$orderId,$driverId,'Delivered');
}

/* load order */
function must_load_order(mysqli $c, int $orderId): array {
  $st=$c->prepare("SELECT order_id, driver_id, pickup_address, delivery_address, COALESCE(status,'') status
                   FROM orders WHERE order_id=? LIMIT 1");
  $st->bind_param('i',$orderId); $st->execute();
  $row=$st->get_result()->fetch_assoc(); $st->close();
  if(!$row) bad('Order not found',404);
  return $row;
}

/* guard */
$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
if ($orderId<=0) bad('Missing order_id');

$order  = must_load_order($conn,$orderId);
$doneMsg = '';

/* map upload errors to clear text */
function upload_err_msg(int $err): string {
  return [
    UPLOAD_ERR_INI_SIZE   => 'Server limit hit (upload_max_filesize).',
    UPLOAD_ERR_FORM_SIZE  => 'Form limit hit (MAX_FILE_SIZE).',
    UPLOAD_ERR_PARTIAL    => 'Upload incomplete.',
    UPLOAD_ERR_NO_FILE    => 'No file selected.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server temp dir missing.',
    UPLOAD_ERR_CANT_WRITE => 'Server cannot write to disk.',
    UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension.',
  ][$err] ?? ("Error code $err");
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  /* receiver (optional) */
  $receiver = trim((string)($_POST['receiver'] ?? ''));
  if ($receiver!==''){ meta_set($conn,$orderId,'pod_receiver',$receiver); meta_set($conn,$orderId,'received_by',$receiver); }

  /* file presence + explicit error mapping */
  if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
    bad('No file field received (name=photo).');
  }
  $err = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err !== UPLOAD_ERR_OK) {
    bad('Upload failed: '.upload_err_msg($err).' Check limits and /uploads/pod permissions.');
  }

  $tmp  = $_FILES['photo']['tmp_name'];
  $size = (int)($_FILES['photo']['size'] ?? 0);
  if ($size<=0) bad('Empty photo upload.');
  if ($size>10485760) bad('Photo too large (max 10 MB).');

  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = (string)$fi->file($tmp);
  $ext = match($mime){ 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heif', default=>'jpg' };

  /* robust doc-root + dir creation */
  $root = rtrim($_SERVER['DOCUMENT_ROOT']??'', '/');
  if ($root === '' || !is_dir($root)) { $root = realpath(dirname(__DIR__)); } // fallback for some hosts
  $dir  = $root.'/uploads/pod';
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  if (!is_dir($dir) || !is_writable($dir)) bad('Upload directory not writable.');

  $name = 'pod_'.$orderId.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$ext;
  $dst  = $dir.'/'.$name;
  if (!move_uploaded_file($tmp,$dst)) bad('Could not save photo to disk.');

  $url = '/uploads/pod/'.$name;

  /* normalize -> JPEG + autorotate + downscale + thumbnail (if Imagick available) */
  if (class_exists('Imagick')) {
    try {
      $im = new Imagick($dst);
      $im->autoOrient();
      $im->setImageFormat('jpeg');
      $im->setImageCompressionQuality(82);
      $w=$im->getImageWidth(); $h=$im->getImageHeight();
      if (max($w,$h) > 1600) $im->thumbnailImage(1600, 1600, true);
      $newDst = preg_replace('~\.(heic|heif|webp|png)$~i','.jpg',$dst);
      if ($newDst !== $dst) { $dst = $newDst; }
      $im->writeImage($dst);
      $im->clear(); $im->destroy();
      $url = preg_replace('~\.(heic|heif|webp|png)$~i','.jpg',$url);

      $im2 = new Imagick($dst);
      $im2->thumbnailImage(320,320,true);
      $thumb = $dst.'_sm.jpg';
      $im2->writeImage($thumb);
      $im2->clear(); $im2->destroy();
      $thumbUrl = $url.'_sm.jpg';
      meta_set($conn,$orderId,'pod_photo_thumb',$thumbUrl);
    } catch (Throwable $e) { /* keep original if convert fails */ }
  }

  /* write ALL common meta keys */
  meta_set($conn,$orderId,'pod_photo',$url);
  meta_set($conn,$orderId,'pod_image',$url);
  meta_set($conn,$orderId,'pod_url',$url);

  $now = date('Y-m-d H:i:s');
  meta_set($conn,$orderId,'pod_time',$now);
  meta_set($conn,$orderId,'delivered_at',$now);

  /* auto-mark Delivered */
  $AUTO_MARK_DELIVERED = true;
  if ($AUTO_MARK_DELIVERED) mark_delivered($conn,$orderId,$driverId);

  $doneMsg = 'POD saved. '.($AUTO_MARK_DELIVERED?'Order marked Delivered.':'');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>POD — Order #<?= (int)$orderId ?></title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f6f7fb;margin:0}
.wrap{max-width:720px;margin:24px auto;padding:0 14px}
.card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);padding:16px}
.row{margin:10px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
label{min-width:110px;color:#555}
input[type=text]{flex:1;min-width:220px;padding:10px;border:1px solid #ced4da;border-radius:8px}
input[type=file]{padding:8px;border:1px dashed #a8b3c2;border-radius:8px;background:#f8fafc}
.btn{background:#0d6efd;color:#fff;border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
.small{font-size:12px;color:#666}
.preview{margin-top:8px;max-width:100%;border:1px solid #e5e7eb;border-radius:8px}
.msg{padding:10px 12px;border-radius:8px;margin-bottom:12px}
.ok{background:#d1e7dd;color:#0f5132;border:1px solid #bcdcc5}
.err{background:#f8d7da;color:#842029;border:1px solid #f1aeb5}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h3 style="margin:0 0 8px">Proof of Delivery — Order #<?= (int)$orderId ?></h3>
      <div class="small" style="margin-bottom:8px">Destination: <?= h($order['delivery_address'] ?? '') ?></div>

      <?php if($doneMsg!==''): ?>
        <div class="msg ok"><?= h($doneMsg) ?></div>
        <div class="row"><a class="btn" href="/driver/dashboard.php">← Back to Dashboard</a></div>
      <?php else: ?>
        <form method="post" action="/driver/pod.php" enctype="multipart/form-data">
          <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
          <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
          <div class="row">
            <label for="receiver">Receiver name</label>
            <input type="text" id="receiver" name="receiver" placeholder="Who received it?">
          </div>
          <div class="row">
            <label for="photo">POD photo</label>
            <!-- Opens back camera on most phones -->
            <input id="photo" name="photo" type="file" accept="image/*" capture="environment" required>
          </div>
          <img id="prev" class="preview" alt="" style="display:none">
          <div class="row">
            <button class="btn" id="saveBtn" type="submit">Save POD</button>
            <a class="btn" style="background:#6c757d" href="/driver/dashboard.php">Cancel</a>
          </div>
          <div class="small">Tip: On mobile, tapping the photo field opens the camera. If it shows your gallery, choose “Camera”.</div>
        </form>
      <?php endif; ?>
    </div>
  </div>

<script>
// show a preview of the chosen photo
document.getElementById('photo')?.addEventListener('change', e=>{
  const f = e.target.files && e.target.files[0];
  const img = document.getElementById('prev');
  if (!f) { img.style.display='none'; img.src=''; return; }
  img.src = URL.createObjectURL(f);
  img.style.display='block';
});
// disable double submit
document.querySelector('form')?.addEventListener('submit', e=>{
  const b=document.getElementById('saveBtn'); if(b){ b.disabled=true; b.textContent='Saving…'; }
});
// keep session alive during routes (5 min)
setInterval(()=>fetch('/ping.php',{cache:'no-store'}).catch(()=>{}), 5*60*1000);
</script>
</body>
</html>


