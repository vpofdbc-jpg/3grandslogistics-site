<?php
// /admin/billing_portal.php — open Stripe Customer Billing Portal for a user
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_id'])) { http_response_code(403); exit('Not authorized'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
if (empty($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

// === DB CONNECTION FIX ===
// Use the reliable, absolute path with require_once to connect to the database.
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

// Set strong error reporting for MySQLi operations.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ensure the connection object is available as $conn for the rest of the script.
// We assume /db.php defines the connection as $db.
$conn = $db ?? null;

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection error: $db object not available after including db.php.');
}

$conn->set_charset('utf8mb4');

/* Load your Stripe secret key.
    If you keep keys in /customer/stripe_keys.php, it must define STRIPE_SECRET. */
if (is_file(__DIR__.'/../customer/stripe_keys.php')) {
  require __DIR__.'/../customer/stripe_keys.php';
} elseif (is_file(__DIR__.'/stripe_keys.php')) {
  require __DIR__.'/stripe_keys.php';
} else {
  http_response_code(500); exit('stripe_keys.php not found');
}
if (!defined('STRIPE_SECRET') || !preg_match('/^sk_(test|live)_/i', STRIPE_SECRET)) {
  http_response_code(500); exit('Bad STRIPE_SECRET');
}

/* Find the Stripe customer id */
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$email  = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

$custId = '';

try {
  if ($userId > 0) {
    // prefer user_billing snapshot
    $st=$conn->prepare("SELECT stripe_customer_id FROM user_billing WHERE user_id=? LIMIT 1");
    $st->bind_param('i',$userId); $st->execute();
    $custId = (string)($st->get_result()->fetch_column() ?? '');
    $st->close();

    if ($custId==='') {
      // fallback: users table if it has the column
      $r=$conn->query("SHOW COLUMNS FROM `users` LIKE 'stripe_customer_id'");
      if ($r && $r->num_rows) {
        $st=$conn->prepare("SELECT stripe_customer_id FROM users WHERE id=? LIMIT 1");
        $st->bind_param('i',$userId); $st->execute();
        $custId = (string)($st->get_result()->fetch_column() ?? '');
        $st->close();
      }
      if ($r) $r->close();
    }
  } elseif ($email !== '') {
    // lookup by email → user_id → user_billing
    $st=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $st->bind_param('s',$email); $st->execute();
    $uid = (int)($st->get_result()->fetch_column() ?? 0);
    $st->close();

    if ($uid) {
      $st=$conn->prepare("SELECT stripe_customer_id FROM user_billing WHERE user_id=? LIMIT 1");
      $st->bind_param('i',$uid); $st->execute();
      $custId = (string)($st->get_result()->fetch_column() ?? '');
      $st->close();
    }
  }
} catch (Throwable $e) { /* fall through with empty $custId */ }

if ($custId === '') {
  http_response_code(404);
  exit('No Stripe customer id on file for that user.');
}

/* Create a Billing Portal session and redirect */
$domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$return = $domain.'/admin/dashboard.php';

$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.STRIPE_SECRET],
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query([
    'customer'   => $custId,
    'return_url' => $return,
  ]),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$body = json_decode((string)$res, true);
if ($code === 200 && !empty($body['url'])) {
  header('Location: '.$body['url'], true, 303);
  exit;
}

http_response_code($code >= 400 ? $code : 500);
echo "Portal error: ".htmlspecialchars($body['error']['message'] ?? 'Unexpected error');

