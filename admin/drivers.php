<?php
// Include the core bootstrap file which handles session, database connection ($conn),
// admin authentication, and helper functions (h).
// We assume bootstrap.php is in the root directory.
require_once __DIR__ . '/../bootstrap.php';

// CRITICAL: Ensure the user is logged in as an admin to view this page
require_admin();

// --- Main Logic ---

$drivers = []; // Initialize array to hold driver data
$errorMessage = '';

try {
    // Select relevant driver information.
    // NOTE: Using 'phone_number' based on the schema defined in db_reset_all_tables.php
    $sql = "SELECT id, name, phone_number, created_at FROM drivers ORDER BY name ASC";
    
    // Using the reliably loaded $conn object from bootstrap.php
    $result = $conn->query($sql);

    if ($result === false) {
        throw new \mysqli_sql_exception("SQL Query Failed: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $drivers[] = $row;
        }
    }
    $result->free();
} catch (\mysqli_sql_exception $e) {
    // Log the error and set a friendly message
    error_log("Driver list fetch failed: " . $e->getMessage());
    $errorMessage = "Error fetching driver data. Database issue: " . h($e->getMessage());
}

// --- HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Drivers</title>
    <style>
        /* Simple, clean styling based on the previous admin panel theme */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0;}
        .container { max-width: 900px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        h1 { font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f1f5f9; font-weight: 600; color: #475569; text-transform: uppercase; font-size: 0.75rem; }
        tr:hover { background-color: #f8fafc; }
        .error { color: #dc2626; background-color: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .action-link { color: #2563eb; text-decoration: none; font-weight: 500; }
        .action-link:hover { text-decoration: underline; }
        .no-data { text-align: center; padding: 20px; color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Driver Management Dashboard</h1>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="error"><?php echo h($errorMessage); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($drivers)): ?>
                    <?php foreach ($drivers as $driver): ?>
                    <tr>
                        <td><?php echo h((string)$driver['id']); ?></td>
                        <td><?php echo h($driver['name']); ?></td>
                        <td><?php echo h($driver['phone_number']); ?></td>
                        <td><?php echo h($driver['created_at']); ?></td>
                        <td>
                            <a href="edit_driver.php?id=<?php echo h((string)$driver['id']); ?>" class="action-link">Edit</a> | 
                            <a href="view_driver_packages.php?id=<?php echo h((string)$driver['id']); ?>" class="action-link">View Packages</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="no-data">No drivers found in the system.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top: 30px;"><a href="index.php" class="action-link">← Back to Admin Home</a></p>
    </div>
</body>
</html>
