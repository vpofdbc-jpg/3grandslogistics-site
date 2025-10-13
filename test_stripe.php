$orderId    = (int)$_GET['order_id']; // or however you pass it in
$successUrl = "https://3grandslogistics.com/success.php?order_id={$orderId}";
$cancelUrl  = "https://3grandslogistics.com/checkout.php?order_id={$orderId}"; // your cancel page

\Stripe\Checkout\Session::create([
  // … your existing line items & settings …
  'metadata'    => ['order_id' => $orderId],
  'success_url' => $successUrl,
  'cancel_url'  => $cancelUrl,
]);
