<?php
// /customer/checkout.php — Stripe checkout (GET: form, POST: charge + SCA) + payment email
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/customer/login.php'); exit; }
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$userId = (int)$_SESSION['user_id'];

/* DB */
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
if ($conn instanceof mysqli) $conn->set_charset('utf8mb4');

/* ========= STRIPE KEYS ========= */
const STRIPE_SECRET   = 'sk_test_
const CURRENCY        = 'usd';
if (!preg_match('/^sk_(test|live)_/', STRIPE_SECRET)) { http_response_code(500); exit('Bad STRIPE_SECRET configured.'); }
/* ================================= */

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function bad($msg,$code=400){ http_response_code($code); echo h($msg); exit; }

/** Prefer customer/success.{php|html}; fallback to /success.{php|html}; then dashboard */
function success_url(int $orderId): string {
  $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  $cands = ['/customer/success.php','/customer/success.html','/success.php','/success.html'];
  foreach ($cands as $p) if ($doc && is_file($doc.$p)) return $p.'?order_id='.$orderId;
  return '/customer/dashboard.php?paid=1&order_id='.$orderId;
}

/** Mark order as paid, touching only columns that exist */
function mark_order_paid(mysqli $conn, int $orderId, string $stripePiId): void {
  $cols=[]; $r=$conn->query("SHOW COLUMNS FROM orders"); while($c=$r->fetch_assoc()) $cols[$c['Field']]=true; $r->close();
  $set=["status='Paid'"]; $types=''; $vals=[];
  if(isset($cols['stripe_payment_intent_id'])){ $set[]="stripe_payment_intent_id=?"; $types.='s'; $vals[]=$stripePiId; }
  if(isset($cols['paid_at'])){ $set[]="paid_at=NOW()"; }
  $sql="UPDATE orders SET ".implode(',',$set)." WHERE order_id=? LIMIT 1";
  $st=$conn->prepare($sql); $types.='i'; $vals[]=$orderId; $st->bind_param($types, ...$vals); $st->execute(); $st->close();
}

/** Optional email: calls send_payment_received($orderId, $conn) in /emails.php if present */
function maybe_send_payment_email(int $orderId, mysqli $conn): void {
  $emails = ($_SERVER['DOCUMENT_ROOT'] ?? '').'/emails.php';
  if (!is_file($emails)) return;
  try {
    require_once $emails;
    if (function_exists('send_payment_received')) { @send_payment_received($orderId, $conn); }
  } catch (Throwable $e) { error_log('payment email: '.$e->getMessage()); }
}

function stripe_request(string $method, string $path, array $params=[]){
  $url="https://api.stripe.com/v1".$path;
  if ($method==='get' && $params) $url.=(str_contains($url,'?')?'&':'?').http_build_query($params);
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer ".STRIPE_SECRET],
    CURLOPT_CUSTOMREQUEST=>strtoupper($method),
  ]);
  if($method!=='get') curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($params));
  $res=curl_exec($ch); if($res===false) bad('Stripe connection failed: '.curl_error($ch),502);
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $json=json_decode($res,true); if($code>=400) bad(($json['error']['message']??('Stripe error '.$code)),$code);
  return $json;
}

/** Resolve a payable amount from order_meta and common columns */
function find_order_amount(mysqli $conn, int $orderId): array {
  $st=$conn->prepare("SELECT
      MAX(CASE WHEN meta_key IN ('price','final_cost','finalCost') THEN CAST(meta_value AS DECIMAL(10,2)) END) meta_price
    FROM order_meta WHERE order_id=?");
  $st->bind_param('i',$orderId); $st->execute();
  $meta=$st->get_result()->fetch_assoc(); $st->close();
  $metaPrice=(float)($meta['meta_price']??0);

  $cols=[]; $r=$conn->query("SHOW COLUMNS FROM orders"); while($c=$r->fetch_assoc()) $cols[$c['Field']]=true; $r->close();
  $want=['total_price','total','grand_total','amount','price','final_cost'];
  $existing=array_values(array_filter($want,fn($c)=>isset($cols[$c])));

  $select='order_id,user_id,status'; foreach($existing as $c) $select.=', '.$c;
  $st=$conn->prepare("SELECT $select FROM orders WHERE order_id=? LIMIT 1");
  $st->bind_param('i',$orderId); $st->execute(); $row=$st->get_result()->fetch_assoc() ?: []; $st->close();

  $candidates=[$metaPrice]; foreach($existing as $c) $candidates[]=(float)($row[$c]??0);
  $amount=0.0; foreach($candidates as $v){ if($v>0){ $amount=$v; break; } }

  return [$row,$amount];
}

/* ---------- GET: show form ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // be forgiving with parameter name
  $orderId = (int)($_GET['order_id'] ?? $_GET['id'] ?? 0);
  if ($orderId <= 0) bad('Missing order_id');

  [$row, $amount] = find_order_amount($conn, $orderId);
  if (!$row) bad('Order not found',404);
  if ((int)$row['user_id'] !== $userId) bad('This order does not belong to you.',403);

  if (strcasecmp((string)($row['status']??''),'Paid')===0) { header('Location: '.success_url($orderId)); exit; }
  if ($amount <= 0) bad('This order does not have a payable amount yet.',400);
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Checkout • Order #<?= (int)$orderId ?></title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f6f7fb;margin:0}
      .wrap{max-width:720px;margin:40px auto;background:#fff;border:1px solid #e9edf3;border-radius:12px;padding:20px}
      .row{margin:12px 0}
      .btn{background:#0d6efd;color:#fff;border:0;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
      #card-element{border:1px solid #ced4da;border-radius:8px;padding:12px}
      #msg{color:#c00;margin-top:8px}
    </style>
  </head>
  <body>
    <div class="wrap">
      <h2>Checkout</h2>
      <div class="row">Order #: <strong>#<?= (int)$orderId ?></strong></div>
      <div class="row">Amount: <strong>$<?= number_format($amount,2) ?></strong></div>

      <div class="row" id="card-element"></div>
      <div id="msg"></div>
      <div class="row"><button id="payBtn" class="btn">Pay $<?= number_format($amount,2) ?></button></div>
      <div class="row"><a href="/customer/dashboard.php">← Back to dashboard</a></div>
    </div>

    <script>
    (function(){
      const stripe = Stripe('<?= h(PUBLISHABLE_KEY) ?>');
      const card = stripe.elements().create('card'); card.mount('#card-element');
      const payBtn = document.getElementById('payBtn'), msg = document.getElementById('msg');

      async function go(){
        payBtn.disabled = true; msg.textContent = '';
        try{
          const pm = await stripe.createPaymentMethod({type:'card', card});
          if (pm.error) throw new Error(pm.error.message || 'Payment method error');

          const f = document.createElement('form');
          f.method = 'POST'; f.action = location.pathname;
          const add = (k,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=v; f.appendChild(i); };
          add('order_id','<?= (int)$orderId ?>');
          add('amount','<?= number_format($amount,2,'.','') ?>');
          add('payment_method_id', pm.paymentMethod.id);
          document.body.appendChild(f); f.submit();
        }catch(e){
          msg.textContent = e.message || 'Payment failed';
          payBtn.disabled = false;
        }
      }
      payBtn.addEventListener('click', go);
    })();
    </script>
  </body>
  </html>
  <?php
  exit;
}

/* ---------- POST: charge (may return SCA fallback) ---------- */
$orderId = (int)($_POST['order_id'] ?? 0);
$amount  = (string)($_POST['amount'] ?? '');
$pmId    = (string)($_POST['payment_method_id'] ?? '');
$piId    = (string)($_POST['payment_intent_id'] ?? ''); // after 3DS

// ensure order belongs to user + not already paid
$check = $conn->prepare("SELECT user_id,status FROM orders WHERE order_id=? LIMIT 1");
$check->bind_param('i',$orderId); $check->execute();
$own=$check->get_result()->fetch_assoc(); $check->close();
if(!$own) bad('Order not found',404);
if((int)$own['user_id'] !== $userId) bad('This order does not belong to you.',403);
if (strcasecmp((string)($own['status']??''),'Paid')===0) { header('Location: '.success_url($orderId)); exit; }

/* After 3DS: finalize */
if ($piId !== '') {
  $pi = stripe_request('get', "/payment_intents/{$piId}");
  if (($pi['status'] ?? '') === 'succeeded') {
    mark_order_paid($conn, $orderId, $pi['id']);
    maybe_send_payment_email($orderId, $conn);
    header('Location: '.success_url($orderId)); exit;
  }
  bad('Payment not completed.');
}

/* First attempt */
if ($orderId<=0 || !is_numeric($amount) || (float)$amount<=0 || $pmId==='') bad('Bad payment data.');
$amtCents = (int)round(((float)$amount)*100);

// best-effort receipt email
$receiptEmail = (string)($_SESSION['user_email'] ?? ($_SESSION['email'] ?? ''));
if ($receiptEmail==='') {
  try {
    $st=$conn->prepare("SELECT email FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i',$userId); $st->execute();
    if($r=$st->get_result()->fetch_assoc()) $receiptEmail=(string)$r['email'];
    $st->close();
  }catch(Throwable $e){}
}

$pi = stripe_request('post','/payment_intents',[
  'amount'               => $amtCents,
  'currency'             => CURRENCY,
  'payment_method'       => $pmId,
  'payment_method_types' => ['card'],
  'confirm'              => 'true',
  'confirmation_method'  => 'automatic',
  'receipt_email'        => $receiptEmail,
  'metadata[order_id]'   => (string)$orderId,
]);

$status = $pi['status'] ?? '';
if ($status === 'succeeded') {
  mark_order_paid($conn, $orderId, $pi['id']);
  maybe_send_payment_email($orderId, $conn);
  header('Location: '.success_url($orderId)); exit;
}

if ($status === 'requires_action' && !empty($pi['client_secret'])) {
  $clientSecret = $pi['client_secret'];
  ?>
  <!doctype html><html><head><meta charset="utf-8"><title>Authenticating…</title>
  <script src="https://js.stripe.com/v3/"></script></head><body>
  <p>Please complete authentication…</p>
  <script>
  (async function(){
    const stripe = Stripe('<?= h(PUBLISHABLE_KEY) ?>');
    try{
      const res = await stripe.handleCardAction('<?= h($clientSecret) ?>');
      if (res.error) { document.body.innerHTML = '<p style="color:#c00">'+(res.error.message||'Authentication failed')+'</p>'; return; }
      const f=document.createElement('form'); f.method='POST'; f.action=location.pathname;
      const add=(k,v)=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;f.appendChild(i);};
      add('payment_intent_id',res.paymentIntent.id);
      add('order_id','<?= (int)$orderId ?>');
      document.body.appendChild(f); f.submit();
    }catch(e){ document.body.innerHTML = '<p style="color:#c00">'+(e.message||'Unexpected error')+'</p>'; }
  })();
  </script>
  </body></html>
  <?php
  exit;
}

bad('Payment could not be completed. Status: '.$status, 402);



