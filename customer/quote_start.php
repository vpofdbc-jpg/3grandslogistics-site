<?php
// /customer/quote_start.php — robust launcher to a real Quote/Schedule page
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }

$doc = $_SERVER['DOCUMENT_ROOT'];

// Prefer modern customer quote pages. We intentionally SKIP /quote.php
$candidates = [
  ['/customer/quote_request.php',  ''],
  ['/customer/new_order.php',      ''],
  ['/customer/new_quote.php',      ''],
  ['/customer/order_quote.php',    ''],
  ['/quote_request.php',           ''],
  ['/estimate.php',                ''],
  // ['/quote.php','new=1'], // legacy requires POST/extra params ⇒ causes “Bad input.”
];

foreach ($candidates as [$path,$query]) {
  if (is_file($doc.$path)) {
    $to = $path.($query ? ('?'.$query) : '');
    header('Location: '.$to, true, 302);
    exit;
  }
}

// Friendly fallback page
http_response_code(200);
?><!doctype html><meta charset="utf-8">
<title>Start a Quote</title>
<body style="font-family:system-ui;padding:24px">
<h2>Start a Quote / Schedule a Pickup</h2>
<p>We couldn’t find a quote page yet. Create one at any of these paths and this
button will begin working immediately:</p>
<ul>
  <li>/customer/quote_request.php</li>
  <li>/customer/new_order.php</li>
  <li>/customer/new_quote.php</li>
  <li>/customer/order_quote.php</li>
  <li>/quote_request.php</li>
  <li>/estimate.php</li>
</ul>
<p><a href="/customer/dashboard.php">← Back to dashboard</a></p>
</body>

