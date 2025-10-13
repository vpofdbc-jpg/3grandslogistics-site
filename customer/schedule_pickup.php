<?php
// /customers/schedule_pickup.php — Quote-first flow with summary + save/checkout
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (isset($conn) && $conn instanceof mysqli) $conn->set_charset('utf8mb4');

if (empty($_SESSION['user_id'])) { header('Location:/customers/login.php'); exit; }

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
$CSRF   = $_SESSION['csrf'];
$userId = (int)$_SESSION['user_id'];

/* ====== CONFIG ====== */
$CHECKOUT_URL = '/customers/checkout.php';   // matches /customers folder
$PLATFORM_PCT = 0.05;                        // 5% platform/service fee
$FUEL_PCT     = 0.08;                        // 8% fuel surcharge
$TAX_PCT      = 0.00;                        // sales tax if any
$RATE = [
  'bike'      => ['base'=>10,'per_mile'=>1.25,'per_lb'=>0,    'min'=>10],
  'car'       => ['base'=>15,'per_mile'=>1.75,'per_lb'=>0,    'min'=>15],
  'suv'       => ['base'=>20,'per_mile'=>2.10,'per_lb'=>0,    'min'=>20],
  'van'       => ['base'=>35,'per_mile'=>2.60,'per_lb'=>0.10, 'min'=>35],
  'box_truck' => ['base'=>75,'per_mile'=>3.25,'per_lb'=>0.15, 'min'=>75],
];

/* Saved addresses (optional) */
$saved=[];
try{
  $st=$conn->prepare("SELECT id,label,CONCAT(street, ', ', city, ', ', state, ' ', zipcode) AS full
                      FROM user_addresses WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
  $st->bind_param('i',$userId); $st->execute();
  $saved=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}catch(Throwable $e){}

/* Helpers */
function add_meta(mysqli $c, int $orderId, string $k, string $v): void {
  $st=$c->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
  $st->bind_param('iss',$orderId,$k,$v); $st->execute(); $st->close();
}
function moneyf(float $n): string { return number_format($n,2,'.',''); }

/* Messages */
$okMsg=''; $err='';

/* Handle submit */
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST' && hash_equals($CSRF, $_POST['csrf'] ?? '')) {
  $action   = $_POST['action']    ?? '';                     // save|checkout
  $taskType = $_POST['task_type'] ?? 'standard';             // standard|custom
  $pickup   = trim($_POST['pickup_address'] ?? '');
  $dropoff  = trim($_POST['delivery_address'] ?? '');
  $vehicle  = $_POST['vehicle'] ?? 'car';
  $miles    = max(0,(float)($_POST['miles'] ?? 0));
  $weightLb = max(0,(float)($_POST['weight_lb'] ?? 0));
  $when     = trim($_POST['pickup_time'] ?? '');
  $notes    = trim($_POST['notes'] ?? '');

  if (!$pickup || !$dropoff) {
    $err = 'Pickup and destination are required.';
  } else {
    try{
      $pkg    = ($taskType==='custom' ? 'Custom Task' : 'Standard');
      $status = ($taskType==='custom' ? 'Quote Requested' : ($action==='checkout' ? 'Pending Payment' : 'Quote Saved'));

      $st=$conn->prepare("INSERT INTO orders (user_id, pickup_address, delivery_address, package_size, status, created_at)
                          VALUES (?,?,?,?,?,NOW())");
      $st->bind_param('issss',$userId,$pickup,$dropoff,$pkg,$status);
      $st->execute(); $orderId=(int)$st->insert_id; $st->close();

      add_meta($conn,$orderId,'task_type',$taskType);
      add_meta($conn,$orderId,'vehicle',$vehicle);
      if ($when!=='')  add_meta($conn,$orderId,'pickup_time',$when);
      if ($notes!=='') add_meta($conn,$orderId,'customer_notes',$notes);

      if ($taskType==='custom') {
        add_meta($conn,$orderId,'pricing_mode','custom');
        add_meta($conn,$orderId,'quote_status','requested');
        $okMsg="Thanks! Your request was submitted. A dispatcher will send a quote shortly. Order #$orderId";
      } else {
        $r = $RATE[$vehicle] ?? $RATE['car'];
        $base   = $r['base'];
        $mCost  = $r['per_mile'] * $miles;
        $wCost  = $r['per_lb']   * $weightLb;
        $sub    = max($r['min'], $base + $mCost + $wCost);
        $fee    = round($sub * $PLATFORM_PCT, 2);
        $fuel   = round($sub * $FUEL_PCT, 2);
        $tax    = round(($sub + $fee + $fuel) * $TAX_PCT, 2);
        $total  = $sub + $fee + $fuel + $tax;

        add_meta($conn,$orderId,'pricing_mode','standard');
        add_meta($conn,$orderId,'miles',        (string)$miles);
        add_meta($conn,$orderId,'weight_lb',    (string)$weightLb);
        add_meta($conn,$orderId,'base_cost',    moneyf($base));
        add_meta($conn,$orderId,'miles_cost',   moneyf($mCost));
        add_meta($conn,$orderId,'weight_cost',  moneyf($wCost));
        add_meta($conn,$orderId,'subtotal',     moneyf($sub));
        add_meta($conn,$orderId,'fee_platform', moneyf($fee));
        add_meta($conn,$orderId,'fee_fuel',     moneyf($fuel));
        add_meta($conn,$orderId,'tax',          moneyf($tax));
        add_meta($conn,$orderId,'price',        moneyf($total));
        add_meta($conn,$orderId,'final_cost',   moneyf($total));

        if ($action==='checkout') {
          header('Location: '.$CHECKOUT_URL.'?order_id='.$orderId);
          exit;
        } else {
          $okMsg="Quote saved · Order #$orderId — Estimated total $".moneyf($total);
        }
      }
    }catch(Throwable $e){
      $err='Could not submit. Please try again.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Schedule / Get a Quote</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto;background:#f6f7fb;margin:0}
  .wrap{max-width:980px;margin:28px auto;padding:0 16px}
  .grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }
  .card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:16px}
  label{font-weight:700}
  input,select,textarea{width:100%;padding:10px;border:1px solid #dfe3ea;border-radius:8px;margin:6px 0 12px}
  .row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .btn{padding:10px 14px;border:0;border-radius:10px;background:#0d6efd;color:#fff;font-weight:800;cursor:pointer}
  .btn.gray{background:#111}
  .ok{background:#e8fff1;border:1px solid #b7f3cf;color:#0f5132;padding:10px;border-radius:8px;margin-bottom:12px}
  .err{background:#ffecec;border:1px solid #ffb3b3;color:#8a1f1f;padding:10px;border-radius:8px;margin-bottom:12px}
  .est{font-size:28px;font-weight:800}
  .sumrow{display:flex;justify-content:space-between;margin:6px 0}
  .muted{color:#666;font-size:13px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Get a Quote / Schedule a Pickup</h1>

  <?php if($okMsg): ?><div class="ok"><?=h($okMsg)?></div><?php endif; ?>
  <?php if($err):   ?><div class="err"><?=h($err)?></div><?php endif; ?>

  <div class="grid">
    <!-- LEFT: form -->
    <div class="card">
      <form method="post" autocomplete="off" id="quoteForm">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

        <label>Task Type</label>
        <div class="row" style="margin-top:6px">
          <label><input type="radio" name="task_type" value="standard" checked> Standard (rate card)</label>
          <label><input type="radio" name="task_type" value="custom"> Custom Task (manual quote)</label>
        </div>

        <?php if ($saved): ?>
          <div class="row">
            <div>
              <label>Use saved pickup address (optional)</label>
              <select id="pickSaved">
                <option value="">— Select —</option>
                <?php foreach($saved as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" data-full="<?= h($a['full']) ?>">
                    <?= h(($a['label']?:'Saved').': '.$a['full']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Use saved delivery address (optional)</label>
              <select id="dropSaved">
                <option value="">— Select —</option>
                <?php foreach($saved as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" data-full="<?= h($a['full']) ?>">
                    <?= h(($a['label']?:'Saved').': '.$a['full']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php endif; ?>

        <label>Pickup Address</label>
        <input id="pickup_address" name="pickup_address" required>

        <label>Delivery Address</label>
        <input id="delivery_address" name="delivery_address" required>

        <div id="standardFields">
          <div class="row">
            <div>
              <label>Vehicle</label>
              <select name="vehicle" id="vehicle">
                <option value="car">Car</option>
                <option value="suv">SUV</option>
                <option value="van">Cargo Van</option>
                <option value="box_truck">Box Truck</option>
                <option value="bike">Bike Courier</option>
              </select>
            </div>
            <div>
              <label>Estimated Miles</label>
              <input name="miles" id="miles" type="number" step="0.1" min="0" placeholder="e.g. 12.5">
            </div>
          </div>
          <div class="row">
            <div>
              <label>Weight (lbs)</label>
              <input name="weight_lb" id="weight_lb" type="number" step="0.1" min="0">
            </div>
            <div>
              <label>Pickup Time (optional)</label>
              <input name="pickup_time" type="datetime-local">
            </div>
          </div>
        </div>

        <div id="customFields" style="display:none">
          <label>Details</label>
          <textarea name="notes" rows="4" placeholder="Describe the custom task and any access/time window details."></textarea>
          <label>Preferred Pickup Time (optional)</label>
          <input name="pickup_time" type="datetime-local">
        </div>

        <div class="row" style="margin-top:10px">
          <button class="btn gray"   type="submit" name="action" value="save">Save Quote</button>
          <button class="btn"        type="submit" name="action" value="checkout">Proceed to Checkout</button>
        </div>
      </form>
    </div>

    <!-- RIGHT: quote summary -->
    <div class="card" id="summaryCard">
      <h3 style="margin-top:0">Quote Summary</h3>
      <div class="sumrow"><span>Base</span><strong id="sBase">$0.00</strong></div>
      <div class="sumrow"><span>Miles</span><strong id="sMiles">$0.00</strong></div>
      <div class="sumrow"><span>Weight</span><strong id="sWeight">$0.00</strong></div>
      <div class="sumrow"><span>Minimum Applied</span><strong id="sMin">$0.00</strong></div>
      <hr>
      <div class="sumrow"><span>Subtotal</span><strong id="sSub">$0.00</strong></div>
      <div class="sumrow"><span>Platform Fee (5%)</span><strong id="sFee">$0.00</strong></div>
      <div class="sumrow"><span>Fuel Surcharge (8%)</span><strong id="sFuel">$0.00</strong></div>
      <div class="sumrow"><span>Tax</span><strong id="sTax">$0.00</strong></div>
      <div class="sumrow" style="font-size:18px"><span>Total</span><strong id="sTotal" class="est">$0.00</strong></div>
      <div class="muted" id="sumNote">Enter pickup & delivery + miles/weight to see a quote.</div>
    </div>
  </div>
</div>

<script>
  // Config (sync with PHP)
  const RATE = {
    bike:{base:10,per_mile:1.25,per_lb:0,min:10},
    car:{base:15,per_mile:1.75,per_lb:0,min:15},
    suv:{base:20,per_mile:2.10,per_lb:0,min:20},
    van:{base:35,per_mile:2.60,per_lb:0.10,min:35},
    box_truck:{base:75,per_mile:3.25,per_lb:0.15,min:75}
  };
  const PLATFORM_PCT=0.05, FUEL_PCT=0.08, TAX_PCT=0.00;

  // Toggle standard/custom
  const radios = document.querySelectorAll('input[name="task_type"]');
  const std = document.getElementById('standardFields');
  const cus = document.getElementById('customFields');
  const summaryCard = document.getElementById('summaryCard');

  // Inputs used to decide when to show non-zero
  const pickupEl = document.getElementById('pickup_address');
  const dropEl   = document.getElementById('delivery_address');

  // Summary refs
  const vehicle=document.getElementById('vehicle');
  const miles=document.getElementById('miles');
  const weight=document.getElementById('weight_lb');
  const sBase=document.getElementById('sBase');
  const sMiles=document.getElementById('sMiles');
  const sWeight=document.getElementById('sWeight');
  const sMin=document.getElementById('sMin');
  const sSub=document.getElementById('sSub');
  const sFee=document.getElementById('sFee');
  const sFuel=document.getElementById('sFuel');
  const sTax=document.getElementById('sTax');
  const sTotal=document.getElementById('sTotal');
  const sumNote=document.getElementById('sumNote');

  function fmt(n){ return '$'+(Number(n||0).toFixed(2)); }

  // Zero-out helper
  function zeroSummary(){
    [sBase,sMiles,sWeight,sMin,sSub,sFee,sFuel,sTax,sTotal].forEach(el=>el.textContent='$0.00');
    sumNote.textContent = 'Enter pickup & delivery + miles/weight to see a quote.';
  }

  function toggleType(){
    const t=[...radios].find(r=>r.checked)?.value;
    std.style.display = (t==='standard')?'block':'none';
    cus.style.display = (t==='custom')  ?'block':'none';
    summaryCard.style.display = (t==='standard')?'block':'none';
    if (t!=='standard') zeroSummary(); // ensure zero when custom
  }
  radios.forEach(r=>r.addEventListener('change',toggleType));
  toggleType();

  // Saved address helpers
  const pickSaved=document.getElementById('pickSaved');
  const dropSaved=document.getElementById('dropSaved');
  if(pickSaved){
    pickSaved.addEventListener('change',()=>{ const o=pickSaved.selectedOptions[0]; if(o&&o.dataset.full) pickupEl.value=o.dataset.full; calc(); });
  }
  if(dropSaved){
    dropSaved.addEventListener('change',()=>{ const o=dropSaved.selectedOptions[0]; if(o&&o.dataset.full) dropEl.value=o.dataset.full; calc(); });
  }

  // Live quote summary (with guard to keep $0.00 until meaningful inputs)
  function calc(){
    // only for Standard
    const t=[...radios].find(r=>r.checked)?.value;
    if (t!=='standard') { zeroSummary(); return; }

    // require pickup + delivery + (miles > 0 || weight > 0)
    const m = parseFloat(miles?.value||0) || 0;
    const w = parseFloat(weight?.value||0) || 0;
    const ready = (pickupEl.value.trim() && dropEl.value.trim() && (m > 0 || w > 0));
    if (!ready) { zeroSummary(); return; }

    const r = RATE[(vehicle?.value)||'car'];
    const base  = r.base;
    const mCost = r.per_mile*m;
    const wCost = r.per_lb*w;
    const raw   = base + mCost + wCost;
    const minAdj= Math.max(0, r.min - raw);
    const sub   = Math.max(r.min, raw);
    const fee   = sub * PLATFORM_PCT;
    const fuel  = sub * FUEL_PCT;
    const tax   = (sub + fee + fuel) * TAX_PCT;
    const total = sub + fee + fuel + tax;

    sBase.textContent  = fmt(base);
    sMiles.textContent = fmt(mCost);
    sWeight.textContent= fmt(wCost);
    sMin.textContent   = fmt(minAdj);
    sSub.textContent   = fmt(sub);
    sFee.textContent   = fmt(fee);
    sFuel.textContent  = fmt(fuel);
    sTax.textContent   = fmt(tax);
    sTotal.textContent = fmt(total);
    sumNote.textContent = 'This is an instant estimate. Final total may vary with actual distance/weight.';
  }

  [vehicle,miles,weight,pickupEl,dropEl].forEach(el=>el && el.addEventListener('input',calc));
  calc();
</script>
</body>
</html>




