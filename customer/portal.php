<?php
// /customer/portal.php — Stripe Billing Portal launcher
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$userId = (int)$_SESSION['user_id'];

require __DIR__.'/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn = $mysqli;
$conn->set_charset('utf8mb4');

/* ======= STRIPE SECRET (use the same one as checkout) ======= */
const STRIPE_SECRET = 'sk_test

function stripe_request(string $method, string $path, array $params=[]){
  $url = "https://api.stripe.com/v1".$path;
  if ($method==='get' && $params) $url.=(strpos($url,'?')!==false?'&':'?').http_build_query($params);
  $ch=curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer ".STRIPE_SECRET],
    CURLOPT_CUSTOMREQUEST=>strtoupper($method),
  ]);
  if ($method!=='get') curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
  $res=curl_exec($ch); if($res===false){ http_response_code(502); exit('Stripe connection failed'); }
  $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $json=json_decode($res,true); if($code>=400){ http_response_code($code); exit($json['error']['message'] ?? 'Stripe error'); }
  return $json;
}

function get_user_email_name(mysqli $c, int $uid): array {
  $st=$c->prepare("SELECT COALESCE(email,'') email, COALESCE(name, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) AS name FROM users WHERE id=? LIMIT 1");
  $st->bind_param('i',$uid); $st->execute(); $u=$st->get_result()->fetch_assoc() ?: ['email'=>'','name'=>'']; $st->close();
  return [ (string)$u['email'], trim((string)$u['name']) ];
}

function get_or_create_stripe_customer(mysqli $c, int $uid): string {
  // Try users.stripe_customer_id
  $hasCol=false; $res=$c->query("SHOW COLUMNS FROM users LIKE 'stripe_customer_id'"); $hasCol=(bool)$res->num_rows; $res->close();
  if ($hasCol) {
    $st=$c->prepare("SELECT stripe_customer_id FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i',$uid); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
    if (!empty($row['stripe_customer_id'])) return (string)$row['stripe_customer_id'];
  }
  // Try user_meta
  $cust=''; try{
    $st=$c->prepare("SELECT meta_value FROM user_meta WHERE user_id=? AND meta_key='stripe_customer_id' LIMIT 1");
    $st->bind_param('i',$uid); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
    if (!empty($row['meta_value'])) $cust=(string)$row['meta_value'];
  }catch(Throwable $e){}

  if ($cust!=='') return $cust;

  // Create a Stripe customer
  [$email,$name] = get_user_email_name($c,$uid);
  $created = stripe_request('post','/customers', array_filter(['email'=>$email, 'name'=>$name]));
  $cust = $created['id'];

  // Save back
  if ($hasCol) {
    $u=$c->prepare("UPDATE users SET stripe_customer_id=? WHERE id=?");
    $u->bind_param('si',$cust,$uid); $u->execute(); $u->close();
  } else {
    // ensure user_meta table exists; if not, skip persist
    try{
      $c->query("CREATE TABLE IF NOT EXISTS user_meta(
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        meta_key VARCHAR(100) NOT NULL,
        meta_value TEXT,
        KEY(user_id), KEY(meta_key)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $m=$c->prepare("INSERT INTO user_meta (user_id,meta_key,meta_value) VALUES (?,?,?)");
      $k='stripe_customer_id'; $m->bind_param('iss',$uid,$k,$cust); $m->execute(); $m->close();
    }catch(Throwable $e){}
  }
  return $cust;
}

// Build absolute return URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$return = $scheme.'://'.$host.'/customer/dashboard.php';

// Create portal session and redirect
$custId = get_or_create_stripe_customer($conn, $userId);
$sess   = stripe_request('post','/billing_portal/sessions', ['customer'=>$custId, 'return_url'=>$return]);
header('Location: '.$sess['url'], true, 302);
exit;
