<?php
// /admin/new_package.php - Form for logging a new package and optionally assigning a driver.

declare(strict_types=1);

// Assumes 'bootstrap.php' is in the root directory and provides $conn (database), 
// h() (HTML entity escaping), require_admin(), and set/get_session_message() helpers.
require_once __DIR__ . '/../bootstrap.php'; 

// 1. Enforce Admin Access
require_admin();

// 2. Fetch Active Drivers for the dropdown
$drivers = [];
$error_fetching_drivers = false;

try {
    // Note: Using query() is safe here as no user input is involved in the query string.
    $drivers_query = "SELECT id, name FROM drivers WHERE status = 'active' ORDER BY name ASC";
    $drivers_result = $conn->query($drivers_query);
    
    if ($drivers_result === false) {
        // mysqli_query returns false on error
        throw new \Exception("Database query failed: " . $conn->error);
    }
    
    $drivers = $drivers_result->fetch_all(MYSQLI_ASSOC);

} catch (\Exception $e) {
    error_log("Error fetching active drivers: " . $e->getMessage());
    $error_fetching_drivers = true;
    set_session_message('error_message', 'Could not load driver list due to a system error. Packages cannot be assigned initially.');
}

// 3. Handle Form Messages (Success/Error from previous form submission to intake_save.php)
// Assuming these helper functions exist in bootstrap.php and handle unsetting the session var.
$message = get_session_message('success_message');
$error = get_session_message('error_message');

// Note: admin_header.php and admin_footer.php are assumed to exist and wrap the content.
require_once('admin_header.php');
?>

<!-- START: Custom Styles for Professional Look -->
<style>
    /* Use Inter font (assumed to be loaded by admin_header.php) */
    :root {
        --color-primary: #007bff;
        --color-primary-dark: #0056b3;
        --color-header: #172a4e;
        --color-text: #333;
        --color-border: #ccc;
    }

    /* Base Container */
    .form-container {
        max-width: 700px;
        margin: 40px auto;
        padding: 30px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* Form Header */
    .form-header {
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    .form-header h1 {
        font-size: 2em;
        color: var(--color-header);
        margin: 0;
    }

    .form-header p {
        color: #666;
        margin-top: 5px;
    }

    /* Input Grouping */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--color-text);
    }

    .form-group small {
        color: #6c757d;
        font-size: 0.85em;
    }

    /* Input Styling */
    .form-group input:not([type="checkbox"]),
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--color-border);
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 1em;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        outline: none;
    }

    /* Grid Layout for inline fields (Weight/Size) */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    /* Alert Boxes */
    .alert {
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 500;
        animation: fadeIn 0.5s ease-out;
    }

    .alert-success {
        background-color: #e6ffec; /* Light green */
        color: #1a5e2f; /* Dark green */
        border: 1px solid #c8e6c9;
    }

    .alert-error {
        background-color: #ffe6e6; /* Light red */
        color: #7f1717; /* Dark red */
        border: 1px solid #e6c8c8;
    }
    
    .alert-warning {
        background-color: #fff3cd; /* Light yellow */
        color: #856404;
        border: 1px solid #ffeeba;
    }

    /* Submit Button */
    .submit-btn {
        background-color: var(--color-primary);
        color: white;
        padding: 14px 25px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1.1em;
        font-weight: bold;
        transition: background-color 0.3s, box-shadow 0.3s;
        width: 100%;
        margin-top: 10px;
    }

    .submit-btn:hover {
        background-color: var(--color-primary-dark);
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Responsive adjustments */
    @media (max-width: 600px) {
        .form-grid {
            grid-template-columns: 1fr; /* Stack fields vertically on small screens */
        }
        .form-container {
            margin: 20px 10px;
            padding: 20px;
        }
    }
</style>
<!-- END: Custom Styles -->

<div class="form-container">
    <div class="form-header">
        <h1>New Package Intake</h1>
        <p>Log a new package into the system and optionally assign it for delivery.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <strong>System Alert:</strong> <?php echo h($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_fetching_drivers): ?>
         <div class="alert alert-warning">
            The driver assignment list could not be loaded. Please log the package now and assign a driver later.
        </div>
    <?php endif; ?>

    <!-- Form will submit to intake_save.php -->
    <form action="intake_save.php" method="POST">

        <div class="form-group">
            <label for="tracking_id">Tracking ID <small>(Optional - Auto-generated if blank)</small></label>
            <!-- Note: Use h() for pre-filling any values if this form was sticky -->
            <input type="text" id="tracking_id" name="tracking_id" placeholder="e.g., 3GL-12345678" maxlength="50">
        </div>

        <div class="form-group">
            <label for="recipient_name">Recipient Name *</label>
            <input type="text" id="recipient_name" name="recipient_name" required placeholder="Full Name of Consignee" maxlength="100">
        </div>

        <div class="form-group">
            <label for="delivery_address">Delivery Address *</label>
            <input type="text" id="delivery_address" name="delivery_address" required placeholder="Street, City, State ZIP/Postcode" maxlength="255">
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="package_size">Package Size *</label>
                <select id="package_size" name="package_size" required>
                    <option value="small">Small (Envelope/Shoebox)</option>
                    <option value="medium" selected>Medium (Standard Box)</option>
                    <option value="large">Large (Oversize/Pallet)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="weight_kg">Weight (kg) *</label>
                <!-- Use pattern and input mode for better mobile keyboard input -->
                <input type="number" id="weight_kg" name="weight_kg" step="0.01" min="0.01" required value="1.0" inputmode="decimal">
            </div>
        </div>

        <div class="form-group">
            <label for="driver_id">Assign to Driver</label>
            <select id="driver_id" name="driver_id" <?php echo $error_fetching_drivers ? 'disabled' : ''; ?> required>
                <option value="0" selected>-- Do NOT Assign (Initial Status: LOGGED) --</option>
                <?php if (!empty($drivers)): ?>
                    <?php foreach ($drivers as $driver): ?>
                        <option value="<?php echo h((string)$driver['id']); ?>">
                            <?php echo h($driver['name']) . " (ID #" . h((string)$driver['id']) . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="0" disabled>-- No Active Drivers Found --</option>
                <?php endif; ?>
            </select>
            <small>Choosing a driver (ID > 0) will set the initial status to **ASSIGNED**.</small>
        </div>


        <button type="submit" class="submit-btn">
            Log & Assign Package
        </button>

    </form>

    <div style="margin-top: 25px; text-align: center;">
        <a href="packages_list.php" style="color: var(--color-primary); text-decoration: none; font-weight: 500;">&larr; Back to Package List</a>
    </div>

</div>

<?php
// Note: We close the connection (if it hasn't been closed by bootstrap/shutdown handler) 
// and include the footer template.
// $conn->close(); // If bootstrap doesn't handle connection management on shutdown.
require_once('admin_footer.php');
?>


