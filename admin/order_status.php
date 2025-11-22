require_once __DIR__ . '/../../mail_helper.php';

// get user email for this order
$s = $conn->prepare("SELECT u.email,u.name,o.order_id FROM orders o JOIN users u ON u.id=o.user_id WHERE o.order_id=?");
$s->bind_param('i', $oid);
$s->execute();
$info = $s->get_result()->fetch_assoc();

$nice = [
  'pending'    => 'Pending',
  'in_transit' => 'In Transit',
  'delivered'  => 'Delivered',
  'cancelled'  => 'Cancelled',
][$to] ?? ucfirst($to);

$subj = "Order #{$info['order_id']} status: $nice";
$body = "Hi ".($info['name'] ?? 'Customer').",\n\n".
        "Your order #{$info['order_id']} is now: $nice.\n\n".
        "Thank you for using 3 Grands Logistics.";
send_mail_simple($info['email'] ?? '', $subj, $body);
