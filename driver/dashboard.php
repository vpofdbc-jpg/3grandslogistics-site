<?php
// Start session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// === SECURITY CHECK: REDIRECT IF NOT LOGGED IN ===
if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

// Get driver details from session
$driver_id = $_SESSION['driver_id'];
// Use a placeholder name if the actual name isn't set during login
$driver_name = $_SESSION['driver_name'] ?? 'Driver'; 

// === TEMPORARY: REMOVED DB CONNECTION DUE TO HTTP 500 ERROR ===
// NEW way - Uses absolute path for reliability
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php'; 
// The database connection ($conn) is not available, so we use placeholder data.

$packages = []; // Initialize empty array

// --- PLACEHOLDER DATA FOR FRONT-END DEVELOPMENT ---
$packages = [
    [
        'id' => 1,
        'tracking_id' => '3GL-12345678',
        'recipient_name' => 'John Smith',
        'delivery_address' => '101 Placeholder Ln, Anytown, CA 90210',
        'status' => 'assigned',
        'weight_kg' => 2.5
    ],
    [
        'id' => 2,
        'tracking_id' => '3GL-98765432',
        'recipient_name' => 'Jane Doe',
        'delivery_address' => '555 Main St, Big City, NY 10001',
        'status' => 'picked_up',
        'weight_kg' => 1.0
    ],
    [
        'id' => 3,
        'tracking_id' => '3GL-55555555',
        'recipient_name' => 'Michael Johnson',
        'delivery_address' => '222 Oak Ave, Suburbia, TX 75001',
        'status' => 'delivered',
        'weight_kg' => 5.8
    ];
// --- END PLACEHOLDER DATA ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - 3GL Logistics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .driver-nav { background-color: #22c55e; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .content-wrapper { max-width: 95%; margin: 2rem auto; background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1); }
        .package-table { width: 100%; border-collapse: separate; border-spacing: 0 0.75rem; }
        .package-table th, .package-table td { padding: 1rem; text-align: left; background-color: white; }
        .package-table th { background-color: #1e40af; color: white; text-transform: uppercase; font-size: 0.875rem; font-weight: 600; }
        .package-table tr { border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .package-table tr:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        .status-assigned { background-color: #bfdbfe; color: #1e40af; }
        .status-picked_up { background-color: #fef9c3; color: #a16207; }
        .status-delivered { background-color: #dcfce7; color: #15803d; }
    </style>
</head>
<body class="min-h-screen">

<!-- Driver Navigation Bar -->
<header class="driver-nav">
    <div class="text-2xl font-extrabold">3GL Driver Panel</div>
    <nav>
        <a href="dashboard.php" class="p-2 hover:bg-green-700 rounded-lg font-medium">My Routes</a>
        <!-- Assuming your logout file is named logout.php -->
        <a href="logout.php" class="p-2 ml-4 bg-white text-green-600 rounded-lg hover:bg-gray-100 font-medium transition">Logout</a>
    </nav>
</header>
<!-- End of Driver Navigation Bar -->

<!-- Start of the main content area -->
<div class="content-wrapper">

    <h1 class="text-3xl font-bold text-gray-800 mb-4">Welcome back, <?php echo htmlspecialchars($driver_name); ?>!</h1>
    <p class="text-lg text-gray-600 mb-8">Your Assigned Deliveries (ID: <?php echo htmlspecialchars($driver_id); ?>)</p>

    <?php if (empty($packages)): ?>
        <div class="p-6 text-center bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg">
            <p class="font-bold">No packages currently assigned.</p>
            <p class="text-sm mt-1">
                (Note: Using placeholder data. Once **config/db_connect.php** is working, real data will appear here.)
            </p>
        </div>
    <?php else: ?>

    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Route List (<?php echo count($packages); ?> Packages)</h2>
    
    <div class="overflow-x-auto">
        <table class="package-table">
            <thead>
                <tr class="shadow-md rounded-lg">
                    <th class="rounded-tl-lg">Tracking ID</th>
                    <th>Recipient</th>
                    <th>Address</th>
                    <th>Weight (kg)</th>
                    <th>Status</th>
                    <th class="rounded-tr-lg">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $pkg): ?>
                <tr class="bg-white">
                    <td class="font-mono text-sm"><?php echo htmlspecialchars($pkg['tracking_id']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['recipient_name']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['delivery_address']); ?></td>
                    <td><?php echo htmlspecialchars($pkg['weight_kg']); ?></td>
                    <td>
                        <?php
                            $status_class = '';
                            switch ($pkg['status']) {
                                case 'assigned': $status_class = 'status-assigned'; break;
                                case 'picked_up': $status_class = 'status-picked_up'; break;
                                case 'delivered': $status_class = 'status-delivered'; break;
                                default: $status_class = 'bg-gray-200 text-gray-800'; break;
                            }
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $pkg['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <!-- This link will go to a new file we need to create -->
                        <a href="update_status.php?id=<?php echo $pkg['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">Update Status</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

</div>

</body>
</html>







