<?php
// edit_order.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db.php'; // must define $conn (MySQLi)

// --- Validate and fetch the order ---
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid order ID.");
}
$order_id = (int)$_GET['id'];
$user_id  = (int)$_SESSION['user_id'];

// Fetch order owned by this user
$sql  = "SELECT order_id, user_id, status, created_at FROM orders WHERE order_id=? AND user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die("Order not found or you do not have permission to edit this order.");
}

// --- Handle POST (update) ---
$notice = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    // Only allow these statuses (keeps DB clean)
    $allowed = ['pending','processing','delivered','canceled'];
    $new_status = strtolower(trim($_POST['status'] ?? ''));

    if (!in_array($new_status, $allowed, true)) {
        $notice = "Invalid status selected.";
    } else {
        $upd = $conn->prepare("UPDATE orders SET status=? WHERE order_id=? AND user_id=?");
        $upd->bind_param("sii", $new_status, $order_id, $user_id);
        if ($upd->execute()) {
            // Refresh $order to reflect new status or just redirect
            header("Location: order_details.php?id=" . $order_id);
            exit;
        } else {
            $notice = "Failed to update order. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Order #<?php echo htmlspecialchars($order['order_id']); ?></title>
  <style>
    body { font-family: Arial, sans-serif; background:#f4f4f9; padding: 20px; }
    .box {
      max-width: 600px; margin: 0 auto; background: #fff; padding: 20px;
      border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    h2 { margin-top: 0; }
    label { display:block; font-weight:600; margin:12px 0 6px; }
    select, input[type="text"] { width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; }
    .row { margin-top: 14px; display:flex; gap:10px; flex-wrap:wrap; }
    .btn {
      display:inline-block; padding:10px 16px; border:none; border-radius:6px; cursor:pointer; text-decoration:none;
    }
    .save { background:#007bff; color:#fff; }
    .back { background:#6c757d; color:#fff; }
    .danger { background:#dc3545; color:#fff; }
    .note { color:#b00020; margin-top:8px; }
    .meta { color:#555; font-size:0.95rem; margin-top:6px; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Edit Order #<?php echo htmlspecialchars($order['order_id']); ?></h2>
    <div class="meta">Created: <?php echo htmlspecialchars($order['created_at']); ?></div>

    <?php if (!empty($notice)) : ?>
      <p class="note"><?php echo htmlspecialchars($notice); ?></p>
    <?php endif; ?>

    <form method="POST">
      <label for="status">Status</label>
      <select id="status" name="status" required>
        <?php
          $statuses = ['pending' => 'Pending', 'processing' => 'Processing', 'delivered' => 'Delivered', 'canceled' => 'Canceled'];
          foreach ($statuses as $val => $label):
        ?>
          <option value="<?php echo $val; ?>" <?php echo ($order['status'] === $val ? 'selected' : ''); ?>>
            <?php echo $label; ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="row">
        <button type="submit" name="update_order" class="btn save">💾 Save Changes</button>
        <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn back">⬅ Back to Order</a>
        <a href="dashboard.php" class="btn back">🏠 Dashboard</a>
      </div>
    </form>

    <hr style="margin:24px 0">

    <!-- Optional quick delete from edit page (same as on details) -->
    <form method="POST" action="order_details.php?id=<?php echo $order_id; ?>" onsubmit="return confirm('Are you sure you want to cancel/delete this order?');">
      <button type="submit" name="delete" class="btn danger">🗑 Cancel/Delete Order</button>
    </form>
  </div>
</body>
</html>

