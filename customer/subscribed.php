<?php
// /customer/subscribed.php — finalize after Checkout, persist subscription
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$userId = (int)$_SESSION['user_id'];

$sessionId = (string)($_GET['session_id'] ?? '');
if ($sessionId === '') { header('Location:/customer/dashboard.php?subscribed=missing'); exit; }

// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
$conn->set_charset('utf8mb4');

const STRIPE_SECRET = 'sk_test_...'; // same as subscribe.php
if (!preg_match('/^sk_(test|live)_/i', STRIPE_SECRET)) { header('Location:/customer/dashboard.php?subscribed=badkey'); exit; }

function stripe_get(string $path, array $qs=[]): array {
  $url = 'https://api.stripe.com/v1'.$path.($qs?('?'.http_build_query($qs)):'');
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.STRIPE_SECRET],
  ]);
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $j = json_decode((string)$res,true);
  if ($code>=400) throw new RuntimeException($j['error']['message']??('Stripe error '.$code));
  return $j;
}

try {
  // expand to get subscription object eagerly
  $cs = stripe_get("/checkout/sessions/$sessionId", ['expand[]'=>'subscription']);
  // Basic sanity check
  if (!empty($cs['client_reference_id']) && (int)$cs['client_reference_id'] !== $userId) {
    throw new RuntimeException('Session does not belong to this user.');
  }

  $customerId = (string)($cs['customer'] ?? '');
  // If subscription not expanded, fetch it
  $sub = $cs['subscription'] ?? null;
  if (!$sub && !empty($cs['subscription'])) {
    $sub = stripe_get('/subscriptions/'.rawurlencode($cs['subscription']));
  }
  if (!$sub) throw new RuntimeException('No subscription on session.');

  $subId   = (string)($sub['id'] ?? '');
  $status  = (string)($sub['status'] ?? '');
  $periodEnd = (int)($sub['current_period_end'] ?? 0);

  // Upsert record
  $conn->query("CREATE TABLE IF NOT EXISTS user_billing (
    user_id INT PRIMARY KEY,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    status VARCHAR(32),
    current_period_end DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");

  $st = $conn->prepare("INSERT INTO user_billing
      (user_id,stripe_customer_id,stripe_subscription_id,status,current_period_end)
      VALUES (?,?,?,?,FROM_UNIXTIME(?))
      ON DUPLICATE KEY UPDATE
        stripe_customer_id=VALUES(stripe_customer_id),
        stripe_subscription_id=VALUES(stripe_subscription_id),
        status=VALUES(status),
        current_period_end=VALUES(current_period_end),
        updated_at=NOW()");
  $st->bind_param('isssi', $userId, $customerId, $subId, $status, $periodEnd);
  $st->execute(); $st->close();

  header('Location:/customer/dashboard.php?subscribed=1');
  exit;
} catch (Throwable $e) {
  error_log('subscribe finalize: '.$e->getMessage());
  header('Location:/customer/dashboard.php?subscribed=error');
  exit;
}
