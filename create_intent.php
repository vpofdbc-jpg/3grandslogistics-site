<?php
declare(strict_types=1);
session_start();
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ── Stripe PHP (use your existing library folder or composer autoload)
require_once __DIR__ . '/stripe-php-master/init.php'; // adjust if different

\Stripe\Stripe::setApiKey('pk_live_51RumndAuq4Y1kCpxegqwjtZAYnpnOidSG52o8qtfxut9o6y9mwcx0niNWdBypgV0AbfwRSVFnBFmiW4YynEDPDBe00IfazGA75'); // <-- YOUR SECRET KEY

header('Content-Type: application/json');

$orderId = (int)($_POST['order_id'] ?? 0);
$amountCents = (int)($_POST['amount'] ?? 0); // cents

if ($orderId <= 0 || $amountCents <= 0) {
  echo json_encode(['ok'=>false,'error'=>'Bad order or amount']); exit;
}

try {
  $intent = \Stripe\PaymentIntent::create([
    'amount' => $amountCents,
    'currency' => 'usd',
    'metadata' => ['order_id' => $orderId, 'user_id' => (int)($_SESSION['user_id'] ?? 0)],
    'automatic_payment_methods' => ['enabled' => true],
  ]);
  echo json_encode(['ok'=>true, 'client_secret'=>$intent->client_secret]);
} catch (\Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
