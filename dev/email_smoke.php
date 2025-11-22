<?php
declare(strict_types=1);
ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: text/plain; charset=UTF-8');

$root = dirname(__DIR__);                 // => /home/…/public_html
$emails = $root.'/emails.php';

echo "ROOT: $root\n";
echo "emails.php: $emails\n";

if (!file_exists($emails)) {
  exit("❌ emails.php not found.\n");
}
require $emails;

if (!function_exists('send_mail')) {
  exit("❌ send_mail() not defined in emails.php.\n");
}

$to = 'you@yourdomain.com';               // <-- change to your inbox
$ok = false;
try {
  $ok = send_mail($to, 'Smoke test', '<p>This is a test from email_smoke.php</p>');
} catch (Throwable $e) {
  exit("❌ Exception: ".$e->getMessage()."\n");
}

echo $ok ? "✅ Sent\n" : "❌ send_mail() returned false\n";
