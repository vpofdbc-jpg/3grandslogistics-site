<?php
// Minimal Admin Header Template
// Checks for required components and starts the HTML structure.

// Ensure session is started only once
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Basic security check (example: assumes admin ID 1 is logged in)
// NOTE: You should replace this with your actual authentication logic!
if (!isset($_SESSION['admin_id'])) {
    // For now, let's set a default N/A ID to allow testing
    $_SESSION['admin_id'] = 'N/A';
    // In a real app, this should redirect: header('Location: login.php'); exit();
}

$admin_id = $_SESSION['admin_id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3GL Admin Panel</title>
    <!-- Include Tailwind CSS via CDN for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        /* Reusable button styles */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
        }

        .success-btn {
            background-color: #10b981; /* Green-500 */
            color: white;
        }

        .success-btn:hover {
            background-color: #059669; /* Green-600 */
        }

        /* Top Navigation Bar */
        .admin-nav {
            background-color: #1f2937; /* Dark Blue/Gray */
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
        }

        .nav-link:hover {
            background-color: #374151;
        }
        
        /* Content Wrapper for main area */
        .content-wrapper {
            max-width: 90%;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        /* Table Styling */
        .package-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .package-table th, .package-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .package-table th {
            background-color: #f9fafb;
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        .package-table tr:hover {
            background-color: #f3f4f6;
        }

        .package-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Tags */
        .status-tag {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .out_for_delivery {
            background-color: #d1fae5; /* Green-100 */
            color: #065f46; /* Green-800 */
        }

        .logged {
            background-color: #fef3c7; /* Yellow-100 */
            color: #b45309; /* Yellow-800 */
        }

        .assigned {
            background-color: #dbeafe; /* Blue-100 */
            color: #1e40af; /* Blue-800 */
        }

        /* Action Links */
        .action-link {
            font-weight: 500;
            text-decoration: none;
            transition: color 0.15s;
            margin: 0 0.25rem;
        }
        
        .edit-link { color: #2563eb; } /* Blue-600 */
        .edit-link:hover { color: #1e40af; } /* Blue-800 */

        .delete-link { color: #ef4444; } /* Red-500 */
        .delete-link:hover { color: #dc2626; } /* Red-600 */
    </style>
</head>
<body class="min-h-screen">

<!-- Admin Navigation Bar -->
<header class="admin-nav">
    <div class="text-xl font-bold">3GL Admin</div>
    <nav class="flex space-x-4">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="list.php" class="nav-link">Packages</a>
        <a href="drivers.php" class="nav-link bg-yellow-600 text-black hover:bg-yellow-700">Drivers</a>
        <a href="intake.php" class="nav-link">Intake</a>
        <a href="stow.php" class="nav-link">Stow</a>
        <a href="live_map.php" class="nav-link">Live Map</a>
        <a href="logout.php" class="nav-link">Logout</a>
    </nav>
    <div class="text-sm">Logged in as Admin ID: **<?php echo htmlspecialchars($admin_id); ?>**</div>
</header>
<!-- Start of the main content area for the included page (e.g., drivers.php) -->






