<?php
// /customer/process_login.php
declare(strict_types=1);
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// The db.php file now provides the PDO connection object: $conn
require __DIR__ . '/../db.php';

// --- Helper Functions ---

// PHP 7 polyfill for str_starts_with, kept for compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n !== '' && substr((string)$h, 0, strlen((string)$n)) === (string)$n; }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect_err(string $m) { 
    header('Location: /customer/login.php?err=' . urlencode($m)); 
    exit; 
}

// --- Main Logic ---

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect_err('Invalid request.');

$user = trim((string)($_POST['username'] ?? ''));
$pass = (string)($_POST['password'] ?? '');
$dbg  = isset($_POST['dbg']) || isset($_GET['dbg']); // debug switch

if ($user === '' || $pass === '') redirect_err('Please enter email/username and password.');

try {
    // 1. Discover password-ish columns (must use standard PDO query)
    $cols = [];
    $stmt_cols = $conn->query("SHOW COLUMNS FROM users");
    
    // PDO equivalent of fetch_assoc() is fetch(PDO::FETCH_ASSOC)
    while ($c = $stmt_cols->fetch(PDO::FETCH_ASSOC)) {
        $cols[$c['Field']] = true;
    }
    // PDO statements are closed implicitly or when re-assigned, no need for close()

    /* try these columns in this order */
    $candidates = array_values(array_filter([
        isset($cols['password_hash']) ? 'password_hash' : null,
        isset($cols['password'])      ? 'password'      : null,
        isset($cols['pwd'])           ? 'pwd'           : null,
        isset($cols['user_pass'])     ? 'user_pass'     : null,
        isset($cols['pass'])          ? 'pass'          : null,
    ]));

    if (!$candidates) redirect_err('No password column found on users table.');

    // Prepare SELECT clause for all candidate password columns
    $sel = implode(', ', array_map(fn($c) => "$c AS `$c`", $candidates));
    
    $sql = "SELECT id, COALESCE(NULLIF(name,''),'Customer') AS name,
                 COALESCE(email,'') AS email, $sel
            FROM users
            WHERE (LOWER(email)=LOWER(:user1) OR LOWER(username)=LOWER(:user2))
            LIMIT 1";
            
    // 2. Prepare and Execute the main query (using PDO)
    $st = $conn->prepare($sql);
    
    // Bind parameters using named placeholders
    $st->bindParam(':user1', $user);
    $st->bindParam(':user2', $user);
    $st->execute();
    
    // Fetch the result
    $row = $st->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Customer Login DB Error: " . $e->getMessage());
    redirect_err('A server error occurred during login. Please try again.');
}

if (!$row) redirect_err('Account not found');

/* verify against whatever is in those columns (logic remains the same) */
$checked = [];
$ok = false;

foreach ($candidates as $c) {
    $v = (string)($row[$c] ?? '');
    if ($v === '') { $checked[]="$c:empty"; continue; }

    // Check for modern hashing (Bcrypt $2y$, Argon2)
    if (str_starts_with($v, '$2y$') || str_starts_with($v, '$argon2')) {
        $ok = password_verify($pass, $v);
        $checked[] = "$c:modern=" . ($ok ? 'ok' : 'no');
    } 
    // Check for legacy hashing (MD5)
    elseif (preg_match('/^[a-f0-9]{32}$/i', $v)) {
        $ok = (strtolower($v) === md5($pass));
        $checked[] = "$c:md5=" . ($ok ? 'ok' : 'no');
    } 
    // Check for legacy hashing (SHA1)
    elseif (preg_match('/^[a-f0-9]{40}$/i', $v)) {
        $ok = (strtolower($v) === sha1($pass));
        $checked[] = "$c:sha1=" . ($ok ? 'ok' : 'no');
    } 
    // Check for plaintext (***SECURITY WARNING: THIS SHOULD BE REMOVED/MIGRATED***)
    else {
        $ok = hash_equals($v, $pass) || hash_equals(trim($v), $pass);
        $checked[] = "$c:plain=" . ($ok ? 'ok' : 'no');
    }

    if ($ok) break;
}

/* optional debug readout */
if ($dbg) {
    header('Content-Type:text/plain; charset=utf-8');
    echo "User: {$user}\n";
    echo "Columns tried: " . implode(', ', $candidates) . "\n";
    echo "Checked: " . implode(' | ', $checked) . "\n";
    echo "Match: " . ($ok ? 'YES' : 'NO') . "\n";
    exit;
}

if (!$ok) redirect_err('Incorrect password.');

// --- Successful Login ---
session_regenerate_id(true);
$_SESSION['user_id']    = (int)$row['id'];
$_SESSION['user_name']  = (string)$row['name'];
$_SESSION['user_email'] = (string)$row['email'];
header('Location: /customer/dashboard.php'); 
exit;
?>





