<?php
// /driver/update_status.php — Placeholder for setting driver status on an order
declare(strict_types=1);
session_start();

// Security check: If driver is not logged in, redirect to login page
if (empty($_SESSION['driver_id'])) { 
    header('Location: login.php'); 
    exit; 
}

// === TEMPORARY: REMOVED DB CONNECTION DUE TO HTTP 500 ERROR ===
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php';

$driverId = $_SESSION['driver_id']; // Using string ID from session
$package_id = 0;
$new_status = '';
$message = '';
$allowed_statuses = ['Accepted','PickedUp','In Transit','Delivered','Cancelled'];

// --- 1. Handle POST Request (Form Submission) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = (int)($_POST['package_id'] ?? 0);
    $new_status = (string)($_POST['new_status'] ?? '');

    if ($package_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        
        // **DATABASE BYPASS:** Simulate the successful database update
        // In a real scenario, the database code you provided would go here.
        
        // Set a session message to show on the dashboard after redirect
        $_SESSION['status_message'] = "✅ Status for Package #{$package_id} successfully updated to '{$new_status}' (Simulated).";

    } else {
        // Fallback in case of bad data
        $_SESSION['status_message'] = "❌ Error: Invalid package ID or status provided.";
    }

    // Redirect back to the dashboard after processing
    header('Location: dashboard.php');
    exit;
}

// --- 2. Handle GET Request (Page Load via Dashboard Link) ---
// We need to fetch the package ID from the URL (e.g., update_status.php?id=1)
$package_id = (int)($_GET['id'] ?? 0);

if ($package_id <= 0) {
    // If no valid ID is provided, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}

// **DATABASE BYPASS:** Simulate fetching package details
// This data should match the placeholder data in dashboard.php for consistency
$package_details = [
    1 => ['tracking_id' => '3GL-12345678', 'recipient_name' => 'John Smith', 'delivery_address' => '101 Placeholder Ln', 'current_status' => 'Assigned'],
    2 => ['tracking_id' => '3GL-98765432', 'recipient_name' => 'Jane Doe', 'delivery_address' => '555 Main St', 'current_status' => 'PickedUp'],
    3 => ['tracking_id' => '3GL-55555555', 'recipient_name' => 'Michael Johnson', 'delivery_address' => '222 Oak Ave', 'current_status' => 'In Transit'],
];

if (!isset($package_details[$package_id])) {
    $_SESSION['status_message'] = "❌ Package ID #{$package_id} not found.";
    header('Location: dashboard.php');
    exit;
}

$pkg = $package_details[$package_id];

// Determine the next logical status options for the form
$current_status = $pkg['current_status'];
$available_updates = array_filter($allowed_statuses, fn($s) => $s !== $current_status);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status - Package #<?php echo $package_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .update-card { background: white; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; margin: 4rem auto; }
        .input-field { width: 100%; padding: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #d1d5db; border-radius: 0.5rem; box-sizing: border-box; }
        .submit-btn { padding: 0.75rem 1.5rem; background-color: #f59e0b; color: white; font-weight: bold; border-radius: 0.5rem; cursor: pointer; transition: background-color 0.3s; }
        .submit-btn:hover { background-color: #d97706; }
        .back-link { color: #1e40af; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="update-card">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Update Package Status</h1>
    
    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-sm text-gray-500">Package ID: <span class="font-mono text-gray-700"><?php echo $package_id; ?></span></p>
        <p class="text-lg font-semibold text-gray-800">Recipient: <?php echo htmlspecialchars($pkg['recipient_name']); ?></p>
        <p class="text-md text-gray-600">Address: <?php echo htmlspecialchars($pkg['delivery_address']); ?></p>
        <p class="mt-2 text-xl font-bold">Current Status: <span class="text-red-500"><?php echo htmlspecialchars($current_status); ?></span></p>
    </div>

    <form method="POST" action="update_status.php">
        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
        
        <label for="new_status" class="block text-sm font-medium text-gray-700 mb-2">Select New Status</label>
        <select id="new_status" name="new_status" required class="input-field">
            <option value="" disabled selected>-- Choose a Status --</option>
            <?php foreach ($available_updates as $status): ?>
                <option value="<?php echo $status; ?>"><?php echo htmlspecialchars($status); ?></option>
            <?php endforeach; ?>
        </select>
        
        <p class="text-sm text-red-600 mb-6">
            Note: Database connection is currently bypassed. This action is **simulated** and will not save to the database.
        </p>

        <button type="submit" class="submit-btn">
            Confirm Status Update
        </button>
    </form>
    
    <div class="mt-6 border-t pt-4">
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    </div>

</div>

</body>
</html>


