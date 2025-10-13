<?php
// /logout.php — works for both customers & admins
declare(strict_types=1);
session_start();

// clear session vars we use
$_SESSION = [];
unset($_SESSION['user_id'], $_SESSION['admin_id'], $_SESSION['csrf']);

// kill PHP session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// pick best login/home that exists
$targets = ['/customer/login.php','/admin/login.php','/login.php','/index.php','/'];
$to = '/';
foreach ($targets as $t) {
    if ($t === '/' || is_file($_SERVER['DOCUMENT_ROOT'].$t)) { $to = $t; break; }
}
header('Location: '.$to, true, 302);
exit;
