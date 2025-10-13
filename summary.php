<?php
// Ensure proper encoding
header('Content-Type: text/html; charset=UTF-8');

// Get POST data from booking form
$packageType  = $_POST['type'] ?? '';
$vehicleType  = $_POST['vehicle'] ?? '';
$pickup       = $_POST['pickup'] ?? '';
$delivery     = $_POST['delivery'] ?? '';
$contact      = $_POST['contact'] ?? '';
$phone        = $_POST['phone'] ?? '';
$finalCost    = $_POST['finalCost'] ?? 0;
$miles        = $_POST['miles'] ?? 0;
$mileageCost  = $_POST['mileageCost'] ?? 0;

// Map package types to base cost
$packagePrices = [
    'small'     => 22,
    'medium'    => 31,
    'large'     => 40,
    'oversized' => 52
];
$packageCost = $packagePrices[$packageType] ?? 0;

// Map vehicle types to per-mile rates
$vehicleRates = [
    'car'   => 2.20,
    'truck' => 4.40
];
$mileageRate = $vehicleRates[$vehicleType] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Summary</title>
<style>
    body { font-family: Arial, sans-serif; background: #f9f9f9; }
    .summary-container {
        background: #fff; max-width: 600px; margin: 40px auto;
        padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { color: #2d7a33; text-align: center; }
    .section-title { font-weight: bold; margin-top: 20px; }
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
    }
</style>
</head>
<body>

<div class="summary-container">
    <h2>Order Summary</h2>

    <div class="section-title">Delivery Details</div>
    <p><strong>Pickup:</strong> <?php echo htmlspecialchars($pickup); ?></p>
    <p><strong>Delivery:</strong> <?php echo htmlspecialchars($delivery); ?></p>
    <p><strong>Contact:</strong> <?php echo htmlspecialchars($contact); ?></p>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>

    <div class="section-title">Cost Breakdown</div>
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
            <td class="final-cost">Final Cost (Greater of above)</td>
            <td class="final-cost">$<?php echo number_format($finalCost, 2); ?></td>
        </tr>
    </table>

    <!-- Form to send data to checkout.php -->
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
</div>

</body>
</html>

