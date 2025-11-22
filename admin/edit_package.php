<?php
// /admin/edit_package.php - Page to edit package details and assign a driver.
declare(strict_types=1);

// Use the core bootstrap file for session, database connection ($conn), security, and helpers (h).
require_once __DIR__ . '/../bootstrap.php';

// CRITICAL: Ensure the user is logged in as an admin to view and use this page
require_admin();

// Initialize variables
$package_id = null;
$package_data = [];
$drivers = [];
$form_errors = [];
$is_update_attempt = false;

// Define package size and status options
$package_sizes = ['Small (Envelope/Shoebox)', 'Medium (Standard Box)', 'Large (Pallet/Oversize)'];
$package_statuses = ['logged', 'assigned', 'picked_up', 'delivered', 'failed', 'returned'];

// Helper to set session messages for redirection
function set_session_message(string $key, string $message): void {
    $_SESSION[$key] = $message;
}

// --- 1. HANDLE FORM SUBMISSION (UPDATE PACKAGE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_update_attempt = true;
    
    // Sanitize and validate POST data
    $package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
    $tracking_id = trim(filter_input(INPUT_POST, 'tracking_id', FILTER_SANITIZE_STRING));
    $recipient_name = trim(filter_input(INPUT_POST, 'recipient_name', FILTER_SANITIZE_STRING));
    $delivery_address = trim(filter_input(INPUT_POST, 'delivery_address', FILTER_SANITIZE_STRING));
    $package_size = filter_input(INPUT_POST, 'package_size', FILTER_SANITIZE_STRING);
    $weight_kg = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    // Use 0 if 'UNASSIGNED' is selected
    $assigned_driver_id = filter_input(INPUT_POST, 'assigned_driver_id', FILTER_VALIDATE_INT);
    
    // Simple validation checks
    if (!$package_id) { $form_errors[] = "Missing Package ID."; }
    if (empty($tracking_id)) { $form_errors[] = "Tracking ID is required."; }
    if (empty($recipient_name)) { $form_errors[] = "Recipient Name is required."; }
    if (empty($delivery_address)) { $form_errors[] = "Delivery Address is required."; }
    if ($weight_kg === false || $weight_kg <= 0) { $form_errors[] = "Weight must be a positive number."; }
    if (!in_array($status, $package_statuses)) { $form_errors[] = "Invalid package status."; }
    
    // If validation passes, proceed with database update
    if (empty($form_errors)) {
        $update_sql = "
            UPDATE packages 
            SET 
                tracking_id = ?, 
                recipient_name = ?, 
                delivery_address = ?, 
                package_size = ?, 
                weight_kg = ?, 
                status = ?, 
                assigned_driver_id = ?
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($update_sql);

        // Convert the driver ID 0 back to NULL for the database. 
        // We use a temporary variable for the null check/bind.
        $driver_param = ($assigned_driver_id === 0) ? null : $assigned_driver_id; 

        if ($stmt) {
            // Note: Use 's' for strings, 'd' for double (float), and 'i' for integer/null driver ID
            // The type string must match the number of parameters: ssss d s i i
            $stmt->bind_param("ssssdsii", 
                $tracking_id, 
                $recipient_name, 
                $delivery_address, 
                $package_size, 
                $weight_kg, 
                $status, 
                $driver_param, // mysqli handles binding null for integer fields using "i"
                $package_id
            );
            
            if ($stmt->execute()) {
                set_session_message('success_message', "Package ID #{$package_id} (Tracking: {$tracking_id}) updated successfully!");
                // Redirect back to the package list on SUCCESS
                header("Location: list_packages.php");
                exit;
            } else {
                set_session_message('error_message', "Database error updating package: " . $stmt->error);
            }
            $stmt->close();
        } else {
            set_session_message('error_message', "Database error preparing update statement: " . $conn->error);
        }

    } else {
        // If validation failed, display errors and reuse POST data for the form
        set_session_message('error_message', "Form validation failed: " . implode(", ", $form_errors));
        
        // Populate package_data with POST values so the form retains user input
        $package_data = [
            'id' => $package_id,
            'tracking_id' => $tracking_id,
            'recipient_name' => $recipient_name,
            'delivery_address' => $delivery_address,
            'package_size' => $package_size,
            'weight_kg' => $weight_kg,
            'status' => $status,
            'assigned_driver_id' => $assigned_driver_id,
        ];
    }
}

// --- 2. FETCH PACKAGE DATA (Initial Load or Post-Failure Repopulation) ---
if (!$is_update_attempt || !empty($form_errors)) {
    // Determine the ID from GET (initial load) or POST (failed submission)
    $fetch_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) 
                ?? filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
    
    // Only query DB if it's the initial load OR if a failed POST attempt needs to reload original data
    if ($fetch_id && !$is_update_attempt) {
        $sql = "SELECT * FROM packages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $fetch_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $package_data = $result->fetch_assoc();
            }
            $stmt->close();
            
            // Reassign the ID for the form
            $package_id = $fetch_id;
        }
    }
}

// If no valid package ID or data is found, stop and redirect
if (!$package_id || empty($package_data) || !isset($package_data['id'])) {
    set_session_message('error_message', "Invalid package ID specified or package not found. Redirecting to list.");
    header("Location: list_packages.php");
    exit;
}

// --- 3. FETCH ALL DRIVERS ---
// Using 'name' column based on previous file rewrites
$drivers_sql = "SELECT id, name FROM drivers ORDER BY name ASC";
$drivers_result = $conn->query($drivers_sql); // Note: No WHERE status='active' filter for simplicity
if ($drivers_result) {
    while ($row = $drivers_result->fetch_assoc()) {
        // Rename 'name' to 'driver_name' for consistency with the form rendering logic
        $drivers[] = ['id' => $row['id'], 'driver_name' => $row['name']];
    }
    $drivers_result->free();
}

// NOTE: We do not close $conn here, as it may be reused by bootstrap/other includes.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Package: #<?php echo h((string)$package_data['id']); ?></title>
    <style>
        /* Styles adapted from the original, using Inter font and rounded corners */
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 0;}
        .content-wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        h1 { font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-bottom: 5px; }
        .back-link {
            display: inline-block;
            margin-bottom: 25px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .back-link:hover { text-decoration: underline; color: #1d4ed8; }

        .package-form fieldset {
            border: 2px solid #e2e8f0;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        .package-form legend {
            font-size: 1.25em;
            font-weight: 700;
            color: #334155;
            padding: 0 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box; 
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        .warning-text {
            color: #dc2626;
            font-size: 0.9em;
            margin-top: 5px;
            background-color: #fef2f2;
            padding: 8px;
            border-radius: 4px;
        }

        .form-actions {
            text-align: right;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }
        .primary-btn {
            background-color: #2563eb;
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
        }
        .primary-btn:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(37, 99, 235, 0.3);
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .error-message {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        .success-message {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <?php 
        // Display session messages and clear them
        if (isset($_SESSION['error_message'])): ?>
            <div class="message error-message"><?php echo h($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; 
        if (isset($_SESSION['success_message'])): ?>
            <div class="message success-message"><?php echo h($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <h1>Edit Package: #<?php echo h((string)$package_data['id']); ?></h1>
        <p>Tracking ID: <strong><?php echo h($package_data['tracking_id'] ?? 'N/A'); ?></strong></p>
        <a href="list_packages.php" class="back-link">&larr; Back to Package List</a>

        <form method="POST" action="edit_package.php" class="package-form">
            <!-- Hidden field to carry the package ID -->
            <input type="hidden" name="package_id" value="<?php echo h((string)$package_data['id']); ?>">
            
            <fieldset>
                <legend>Package Details</legend>

                <div class="form-group">
                    <label for="tracking_id">Tracking ID *</label>
                    <input type="text" id="tracking_id" name="tracking_id" required 
                            value="<?php echo h($package_data['tracking_id'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="recipient_name">Recipient Name *</label>
                    <input type="text" id="recipient_name" name="recipient_name" required 
                            value="<?php echo h($package_data['recipient_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="delivery_address">Delivery Address *</label>
                    <input type="text" id="delivery_address" name="delivery_address" required 
                            value="<?php echo h($package_data['delivery_address'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="package_size">Package Size *</label>
                    <select id="package_size" name="package_size" required>
                        <?php 
                            $current_size = $package_data['package_size'] ?? ''; 
                            foreach ($package_sizes as $size): 
                        ?>
                            <option value="<?php echo h($size); ?>"
                                <?php echo $current_size === $size ? 'selected' : ''; ?>>
                                <?php echo h($size); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="weight_kg">Weight (kg) *</label>
                    <input type="number" step="0.01" min="0.1" id="weight_kg" name="weight_kg" required 
                            value="<?php echo h((string)($package_data['weight_kg'] ?? '')); ?>">
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Logistics Status & Assignment</legend>

                <div class="form-group">
                    <label for="status">Package Status *</label>
                    <select id="status" name="status" required>
                        <?php 
                            $current_status = $package_data['status'] ?? ''; 
                            foreach ($package_statuses as $s): 
                        ?>
                            <option value="<?php echo h($s); ?>"
                                <?php echo $current_status === $s ? 'selected' : ''; ?>>
                                <?php echo h(ucwords(str_replace('_', ' ', $s))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_driver_id">Assign to Driver</label>
                    <select id="assigned_driver_id" name="assigned_driver_id">
                        <?php $current_driver_id = $package_data['assigned_driver_id'] ?? 0; ?>
                        <option value="0" <?php echo $current_driver_id == 0 ? 'selected' : ''; ?>>
                            -- UNASSIGNED (Set to NULL) --
                        </option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?php echo h((string)$driver['id']); ?>"
                                <?php echo $current_driver_id == $driver['id'] ? 'selected' : ''; ?>>
                                <?php echo h($driver['driver_name']); ?> (ID #<?php echo h((string)$driver['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($drivers)): ?>
                        <p class="warning-text">No drivers found. Check the <a href="drivers.php" class="back-link">Drivers</a> page.</p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn primary-btn">Update Package Details</button>
            </div>
        </form>
    </div>
</body>
</html>


