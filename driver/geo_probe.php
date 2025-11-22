<?php
// public_html/driver/geo_probe.php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf  = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
$login = empty($_SESSION['driver_id']);
?>
<!doctype html>
<meta charset="utf-8">
<title>Driver GEO Probe</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto;padding:16px}
  button{padding:10px 14px;border:0;border-radius:8px;background:#0d6efd;color:#fff;font-weight:700;margin-right:8px}
  pre{background:#111;color:#0f0;padding:12px;border-radius:8px;white-space:pre-wrap}
  .warn{color:#b91c1c;font-weight:700}
</style>

<h2>Driver GEO Probe</h2>
<?php if ($login): ?>
  <p class="warn">You are not logged in as a driver. <a href="/driver/login.php">Log in</a> first, then reload this page.</p>
<?php else: ?>
  <p>Logged in. Tap a button and allow location.</p>
<?php endif; ?>

<button id="once">Send one location</button>
<button id="watch">Start watchPosition</button>
<button id="stop">Stop watch</button>

<pre id="out">Waiting…</pre>

<script>
const CSRF = '<?= $csrf ?>';
const out  = document.getElementById('out');
let watchId = null;

function log(msg){ out.textContent = (new Date()).toISOString() + '  ' + msg + '\n' + out.textContent; }

function postLatLng(lat,lng){
  const body = new URLSearchParams({lat:String(lat), lng:String(lng), csrf: CSRF});
  fetch('/driver/ping.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.text()).then(t=>log('POST /driver/ping.php => ' + t))
    .catch(e=>log('ERR: ' + e));
}

document.getElementById('once').onclick = () => {
  if(!navigator.geolocation){ log('Geolocation not supported'); return; }
  navigator.geolocation.getCurrentPosition(
    pos => { const c=pos.coords; log(`getCurrentPosition lat=${c.latitude} lng=${c.longitude}`); postLatLng(c.latitude,c.longitude); },
    err => log('getCurrentPosition error: ' + err.message),
    {enableHighAccuracy:true, maximumAge:15000, timeout:15000}
  );
};

document.getElementById('watch').onclick = () => {
  if(!navigator.geolocation){ log('Geolocation not supported'); return; }
  if(watchId!==null){ log('watch already running'); return; }
  watchId = navigator.geolocation.watchPosition(
    pos => { const c=pos.coords; log(`watchPosition lat=${c.latitude} lng=${c.longitude}`); postLatLng(c.latitude,c.longitude); },
    err => log('watchPosition error: ' + err.message),
    {enableHighAccuracy:true, maximumAge:15000, timeout:15000}
  );
  log('watchPosition started…');
};

document.getElementById('stop').onclick = () => {
  if(watchId!==null){ navigator.geolocation.clearWatch(watchId); watchId=null; log('watchPosition stopped'); }
};
</script>
