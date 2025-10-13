<?php
// book.php

header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageType  = $_POST['type'] ?? '';
    $vehicleType  = $_POST['vehicle'] ?? '';
    $pickup       = $_POST['pickup'] ?? '';
    $delivery     = $_POST['delivery'] ?? '';
    $contact      = $_POST['contact'] ?? '';
    $phone        = $_POST['phone'] ?? '';
    $miles        = $_POST['miles'] ?? 0;

    // Price tables
    $packagePrices = ['small' => 22, 'medium' => 31, 'large' => 40, 'oversized' => 52];
    $vehicleRates  = ['car' => 2.20, 'truck' => 4.40];

    $packageCost = $packagePrices[$packageType] ?? 0;
    $mileageRate = $vehicleRates[$vehicleType] ?? 0;
    $mileageCost = $miles * $mileageRate;
    $finalCost   = $packageCost + $mileageCost;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'Order Summary' : 'Schedule a Delivery'; ?></title>
<style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; }
    .container {
        background: #fff; max-width: 600px; margin: 40px auto;
        padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { color: #2d7a33; text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 8px; text-align: left; }
    th { background: #f3f3f3; }
    .final-cost { font-weight: bold; color: #2d7a33; }
    .btn {
        display: block; background: #2d7a33; color: #fff;
        padding: 12px; text-align: center; text-decoration: none;
        border-radius: 5px; margin-top: 20px; font-weight: bold;
        border: none; cursor: pointer;
        width: 100%;
    }
    input, select {
        width: 100%; padding: 10px; margin: 8px 0;
        border: 1px solid #ccc; border-radius: 4px;
    }
    .cost-box { background: #eef9ee; padding: 10px; margin-top: 10px; border-radius: 5px; }
</style>
</head>
<body>

<div class="container">
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <!-- Booking Form -->
    <h2>Schedule a Pickup</h2>
    <form action="book.php" method="post" id="bookingForm">
        <label>Package Type</label>
        <select name="type" id="packageType" required>
            <option value="">Select</option>
            <option value="small">Small ($22)</option>
            <option value="medium">Medium ($31)</option>
            <option value="large">Large ($40)</option>
            <option value="oversized">Oversized ($52)</option>
        </select>

        <label>Vehicle Type</label>
        <select name="vehicle" id="vehicleType" required>
            <option value="">Select</option>
            <option value="car">Car ($2.20/mile)</option>
            <option value="truck">Truck ($4.40/mile)</option>
        </select>

        <label>Pickup Location</label>
        <input type="text" name="pickup" required>

        <label>Destination Location</label>
        <input type="text" name="delivery" required>

        <label>Contact Name</label>
        <input type="text" name="contact" required>

        <label>Phone Number</label>
        <input type="text" name="phone" required>

        <label>Distance (miles)</label>
        <input type="number" step="0.1" name="miles" id="miles" required>

        <div class="cost-box">
            <strong>Estimated Cost:</strong> $<span id="estimatedCost">0.00</span>
        </div>

        <input type="hidden" name="finalCost" id="finalCost">
        <input type="hidden" name="mileageCost" id="mileageCost">

        <button type="submit" class="btn">Get Summary</button>
    </form>

    <script>
    const packagePrices = { small: 22, medium: 31, large: 40, oversized: 52 };
    const vehicleRates  = { car: 2.20, truck: 4.40 };

    function updateCost() {
        const type   = document.getElementById('packageType').value;
        const vehicle= document.getElementById('vehicleType').value;
        const miles  = parseFloat(document.getElementById('miles').value) || 0;

        const packageCost = packagePrices[type] || 0;
        const mileageRate = vehicleRates[vehicle] || 0;
        const mileageCost = miles * mileageRate;
        const finalCost   = packageCost + mileageCost;

        document.getElementById('estimatedCost').textContent = finalCost.toFixed(2);
        document.getElementById('finalCost').value = finalCost.toFixed(2);
        document.getElementById('mileageCost').value = mileageCost.toFixed(2);
    }

    document.getElementById('packageType').addEventListener('change', updateCost);
    document.getElementById('vehicleType').addEventListener('change', updateCost);
    document.getElementById('miles').addEventListener('input', updateCost);
    </script>

<?php else: ?>
    <!-- Order Summary -->
    <h2>Order Summary</h2>

    <p><strong>Pickup:</strong> <?php echo htmlspecialchars($pickup); ?></p>
    <p><strong>Delivery:</strong> <?php echo htmlspecialchars($delivery); ?></p>
    <p><strong>Contact:</strong> <?php echo htmlspecialchars($contact); ?></p>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>

    <table>
        <tr>
            <th>Item</th>
            <th>Amount</th>
        </tr>
        <tr>
            <td>Base Package (<?php echo ucfirst($packageType); ?>)</td>
            <td>$<?php echo number_format($packageCost, 2); ?></td>
        </tr>
        <tr>
            <td>Mileage Cost (<?php echo number_format($miles, 2); ?> miles × $<?php echo number_format($mileageRate, 2); ?>/mile)</td>
            <td>$<?php echo number_format($mileageCost, 2); ?></td>
        </tr>
        <tr>
            <td class="final-cost">Final Cost</td>
            <td class="final-cost">$<?php echo number_format($finalCost, 2); ?></td>
        </tr>
    </table>

    <form action="checkout.php" method="post">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($packageType); ?>">
        <input type="hidden" name="vehicle" value="<?php echo htmlspecialchars($vehicleType); ?>">
        <input type="hidden" name="pickup" value="<?php echo htmlspecialchars($pickup); ?>">
        <input type="hidden" name="delivery" value="<?php echo htmlspecialchars($delivery); ?>">
        <input type="hidden" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
        <input type="hidden" name="finalCost" value="<?php echo htmlspecialchars($finalCost); ?>">
        <input type="hidden" name="miles" value="<?php echo htmlspecialchars($miles); ?>">
        <input type="hidden" name="mileageCost" value="<?php echo htmlspecialchars($mileageCost); ?>">

        <button type="submit" class="btn">Proceed to Payment</button>
    </form>
<?php endif; ?>
</div>

</body>
</html>

