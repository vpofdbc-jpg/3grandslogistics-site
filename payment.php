<?php
// payment.php — minimal, safe, with visible PHP errors
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);
session_start();

/* Loosen CSP just for this page (single-line to avoid header parsing issues) */
header_remove('Content-Security-Policy');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://js.stripe.com 'unsafe-inline' 'unsafe-eval'; connect-src 'self' https://api.stripe.com https://q.stripe.com; frame-src 'self' https://js.stripe.com https://hooks.stripe.com; img-src 'self' data: https://q.stripe.com; style-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'");

/* read values (URL -> session -> localStorage handled by JS) */
$S = $_SESSION['pay_order'] ?? [];
$orderIdFallback = (int)($S['order_id'] ?? 0);
$amountFallback  = (string)($S['amount'] ?? '');
$e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment</title>
<style>
body{font-family:system-ui,Arial;background:#f4f5fb;margin:0;padding:40px}
.card{max-width:520px;margin:auto;background:#fff;padding:22px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.08)}
.row{display:flex;justify-content:space-between;margin:.35rem 0}
.muted{color:#666}.btn{width:100%;margin-top:16px;padding:12px;border:none;border-radius:10px;background:#2d6a4f;color:#fff;font-weight:700;cursor:pointer}
.btn[disabled]{opacity:.6;cursor:not-allowed}#msg{margin-top:12px;color:#c00;white-space:pre-wrap}
</style>
</head>
<body>
  <div class="card">
    <h2>Payment</h2>
    <div class="row"><span class="muted">Order #</span> <span id="orderId">—</span></div>
    <div class="row"><span class="muted">Amount</span> <span>$<span id="amountTxt">—</span></span></div>

    <form id="payment-form">
      <div id="card-element" style="border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-top:10px"></div>
      <button class="btn" id="payBtn" type="submit">Pay</button>
    </form>
    <div id="msg"></div>
  </div>

  <!-- fallbacks for JS -->
  <input type="hidden" id="fallback_order_id" value="<?= $e((string)$orderIdFallback) ?>">
  <input type="hidden" id="fallback_amount"   value="<?= $e($amountFallback) ?>">

  <script src="https://js.stripe.com/v3/"></script>
  <script>
  (function(){
    const $=id=>document.getElementById(id);
    const show=(m,ok=false)=>{ $('msg').textContent=m||''; $('msg').style.color=ok?'#0a5':'#c00'; };

    // values from URL then fallbacks
    const q=new URLSearchParams(location.search);
    let orderId=q.get('order_id')||$('fallback_order_id').value||'';
    let amount =q.get('amount')  ||$('fallback_amount').value  ||'';
    orderId=String(parseInt(orderId||'0',10));
    amount =String(parseFloat(amount||'0'));

    $('orderId').textContent   = (orderId&&orderId!=='NaN')?orderId:'—';
    $('amountTxt').textContent = (!isNaN(+amount)&&+amount>0)?(+amount).toFixed(2):'0.00';

    if (!orderId||orderId==='0'||isNaN(+amount)||+amount<=0) {
      show('Missing or invalid order_id/amount.'); $('payBtn').disabled=true; return;
    }

    if (typeof Stripe!=='function'){ show('Stripe.js failed to load (CSP/network).'); return; }

    // *** USE YOUR PUBLISHABLE KEY (must start with "pk_") ***
    const PUBLISHABLE_KEY = 'pk_test_51RumndAuq4Y1kCpxNwEAeUt9eLCokXur1rKxYuTj8RVR0T7sEsh9McOY4Cw5LNdYxBcSt4MoQrDD01MkyFapjUnL003PiwQYPO';
    if (!/^pk_(test|live)_/.test(PUBLISHABLE_KEY)){ show('.'); return; }

    const stripe = Stripe(PUBLISHABLE_KEY);
    const elements = stripe.elements();
    const card = elements.create('card');
    card.mount('#card-element');

    $('payment-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      $('payBtn').disabled=true; show('Processing…', true);
      try{
        const {error, paymentMethod} = await stripe.createPaymentMethod({type:'card', card});
        if (error){ show(error.message); $('payBtn').disabled=false; return; }
        const body=new URLSearchParams({order_id:orderId, amount:(+amount).toFixed(2), payment_method_id:paymentMethod.id});
        const res = await fetch('/checkout.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
        if (res.redirected){ location.href=res.url; return; }
        const txt=await res.text();
        try{ const j=JSON.parse(txt); if (j.ok&&j.redirect){ location.href=j.redirect; return; } show(j.error||txt); }
        catch{ show(txt); }
      }catch(err){ show(err.message||'Unexpected error.'); }
      finally{ $('payBtn').disabled=false; }
    });
  })();
  </script>
</body>
</html>












