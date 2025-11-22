<?php
// /admin/impersonate.php — Allows an authenticated admin to switch context to a regular user.
declare(strict_types=1);

// Use the core bootstrap file for session, database connection ($conn), security, and helpers.
require_once __DIR__ . '/../bootstrap.php';

// CRITICAL: Ensure the user is logged in as an admin before allowing impersonation.
require_admin();

// --- Input Validation ---
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$user_id || $user_id <= 0) {
    // We can redirect back to a user list instead of just exiting with a 400 error.
    set_session_message('error_message', 'Invalid or missing User ID for impersonation.');
    header('Location: /admin/users_list.php'); 
    exit;
}

// --- Fetch User Data ---
$u = null;
try {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id=? LIMIT 1");
    if (!$stmt) {
        throw new \Exception("Failed to prepare user select statement: " . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (\Exception $e) {
    // Log the database error and redirect
    error_log("Impersonation DB Error: " . $e->getMessage());
    set_session_message('error_message', 'A database error occurred while fetching the user.');
    header('Location: /admin/users_list.php'); 
    exit;
}

if (!$u) { 
    set_session_message('error_message', "User ID #{$user_id} not found.");
    header('Location: /admin/users_list.php'); 
    exit;
}

// --- Impersonation Logic ---

// 1. Store admin context so we can return later via a 'stop impersonation' link.
// We use COALESCE/ternary to ensure we never rely on $_SESSION keys existing.
$_SESSION['impersonating'] = true;
$_SESSION['admin_backup'] = [
    'admin_id'   => $_SESSION['admin_id'] ?? null,
    'admin_name' => $_SESSION['admin_name'] ?? null,
    'admin_role' => $_SESSION['admin_role'] ?? null,
];

// 2. Set the customer session variables.
$_SESSION['user_id'] = (int)$u['id'];
$_SESSION['name']    = (string)$u['name'];
$_SESSION['email']   = (string)$u['email'];

// 3. Log the action (optional but highly recommended for audit trails).
// You would implement a logging function here, e.g., log_action('impersonate', $user_id, 'users');

// 4. Redirect to the customer dashboard with a success message.
set_session_message('success_message', "Successfully impersonating user: " . h($u['name']) . " (ID: {$u['id']}). Use the link in the header to return to admin mode.");
header('Location: /dashboard.php'); 
exit;

