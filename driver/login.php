<?php
// /driver/test_login.php
declare(strict_types=1);
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// CRITICAL: The path is now relative to the root (up one directory)
// This file is assumed to be in /driver/, so it looks for bootstrap.php in the parent folder.
require_once('../bootstrap.php');

// The bootstrap.php file is expected to load db.php, which provides the PDO connection object: $conn

// --- Helper Functions ---

// PHP 7 polyfill for str_starts_with, kept for compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n) { return $n !== '' && substr((string)$h, 0, strlen((string)$n)) === (string)$n; }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect_err(string $m) { 
    // Redirects back to the driver login page (assumed to be login.php in the same directory)
    header('Location: /driver/login.php?err=' . urlencode($m)); 
    exit; 
}

// --- Main Login Logic ---

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect_err('Invalid request.');

$user = trim((string)($_POST['username'] ?? ''));
$pass = (string)($_POST['password'] ?? '');
$dbg  = isset($_POST['dbg']) || isset($_GET['dbg']); // debug switch

if ($user === '' || $pass === '') redirect_err('Please enter email/username and password.');

try {
    // 1. Discover password-ish columns on the 'drivers' table
    $cols = [];
    $stmt_cols = $conn->query("SHOW COLUMNS FROM drivers");
    
    // Fetch all column names
    while ($c = $stmt_cols->fetch(PDO::FETCH_ASSOC)) {
        $cols[$c['Field']] = true;
    }

    /* Try these password columns in this order */
    $candidates = array_values(array_filter([
        isset($cols['password_hash']) ? 'password_hash' : null,
        isset($cols['password'])      ? 'password'      : null,
        isset($cols['pwd'])           ? 'pwd'           : null,
        isset($cols['driver_pass'])   ? 'driver_pass'   : null,
        isset($cols['pass'])          ? 'pass'          : null,
    ]));

    if (!$candidates) redirect_err('No password column found on drivers table.');

    // Prepare SELECT clause for all candidate password columns
    $sel = implode(', ', array_map(fn($c) => "$c AS `$c`", $candidates));
    
    // Select driver details by email or username
    $sql = "SELECT id, COALESCE(NULLIF(name,''),'Driver') AS name,
                 COALESCE(email,'') AS email, is_active, $sel
            FROM drivers
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
    error_log("Driver Login DB Error: " . $e->getMessage());
    redirect_err('A server error occurred during login. Please try again.');
}

// 3. User Found and Active Check
if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
    usleep(250000); // Delay for security
    redirect_err('Account not found or is inactive.');
}

/* 4. Verify Password */
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
    // Legacy support (MD5, SHA1, plaintext) should be removed in production
    // For now, keeping the robust check from the customer login processor:
    elseif (preg_match('/^[a-f0-9]{32}$/i', $v)) { // MD5
        $ok = (strtolower($v) === md5($pass));
        $checked[] = "$c:md5=" . ($ok ? 'ok' : 'no');
    } 
    elseif (preg_match('/^[a-f0-9]{40}$/i', $v)) { // SHA1
        $ok = (strtolower($v) === sha1($pass));
        $checked[] = "$c:sha1=" . ($ok ? 'ok' : 'no');
    } 
    else { // Plaintext fallback
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

if (!$ok) {
    usleep(250000); // Delay for security
    redirect_err('Incorrect password.');
}

// --- Successful Login ---
session_regenerate_id(true);
$_SESSION['driver_id']    = (int)$row['id'];
$_SESSION['driver_name']  = (string)$row['name'];
$_SESSION['driver_email'] = (string)$row['email'];
header('Location: /driver/dashboard.php'); 
exit;
?>







