<?php
// /admin/customers.php
declare(strict_types=1);
error_reporting(E_ALL); ini_set('display_errors','1');

// Use the reliable, absolute path with require_once
require_once $_SERVER['DOCUMENT_ROOT'] . '/db.php'; (mysqli)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$q = trim($_GET['q'] ?? '');

/*
  Compute per-customer Lifetime Spend by joining orders + order_meta and
  summing only known price keys. We still show customers with no orders (LEFT JOIN).
*/
$sql = "
  SELECT
    u.id, u.username, u.email, u.first_name, u.last_name, u.created_at,
    COALESCE(SUM(
      CASE
        WHEN om.meta_key IN ('price','finalCost','final_cost')
          THEN CAST(om.meta_value AS DECIMAL(10,2))
        ELSE 0
      END
    ), 0) AS total_spend
  FROM users u
  LEFT JOIN orders o      ON o.user_id  = u.id
  LEFT JOIN order_meta om ON om.order_id = o.order_id
";

$params = [];
if ($q !== '') {
  $sql .= "
    WHERE u.email      LIKE CONCAT('%',?,'%')
       OR u.username   LIKE CONCAT('%',?,'%')
       OR u.first_name LIKE CONCAT('%',?,'%')
       OR u.last_name  LIKE CONCAT('%',?,'%')
  ";
  $params = [$q,$q,$q,$q];
}

$sql .= "
  GROUP BY u.id
  ORDER BY u.id DESC
  LIMIT 200
";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param('ssss', ...$params); }
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin • Customers</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;margin:0}
 .top{padding:14px 20px;background:#fff;border-bottom:1px solid #e9edf3}
 .nav a{display:inline-block;margin-right:10px;padding:8px 12px;border-radius:8px;background:#0d6efd;color:#fff;text-decoration:none;font-weight:700}
 .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
 .search{background:#fff;border:1px solid #e9edf3;border-radius:10px;padding:12px;margin-bottom:12px}
 .search input{padding:10px;width:70%;max-width:360px;border:1px solid #dfe3ea;border-radius:8px}
 .search button{padding:10px 14px;border:none;border-radius:8px;background:#0d6efd;color:#fff;font-weight:700;margin-left:6px}
 table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden}
 th,td{padding:10px;border-bottom:1px solid #eef1f6;text-align:left;font-size:14px}
 th{background:#fafbfe}
 .act a{display:inline-block;margin:2px 3px;padding:6px 10px;border-radius:6px;color:#fff;text-decoration:none;font-weight:700}
 .primary{background:#0d6efd} .green{background:#198754}
</style>
</head><body>
  <div class="top">
    <div class="nav">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="orders.php">📁 Orders</a>
      <a href="customers.php">👥 Customers</a>
      <a href="new_order.php">➕ New Order</a>
      <a href="change_password.php">🔑 Change Password</a>
      <a href="logout.php" style="background:#6c757d">🚪 Logout</a>
    </div>
  </div>

  <div class="wrap">
    <div class="search">
      <form method="get">
        <strong>Search:</strong>
        <input name="q" value="<?=h($q)?>" placeholder="email, username, first/last name">
        <button>Find</button>
        <a class="act primary" href="new_order.php">+ New Order</a>
      </form>
    </div>

    <table>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Created</th>
        <th>Lifetime Spend</th>
        <th>Actions</th>
      </tr>
      <?php if (!$rows): ?>
        <tr><td colspan="7">No customers found.</td></tr>
      <?php else: foreach ($rows as $r):
        $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
        if ($name==='') $name='—';
      ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($name) ?></td>
          <td><?= h($r['username'] ?? '') ?></td>
          <td><?= h($r['email'] ?? '') ?></td>
          <td><?= h($r['created_at'] ?? '') ?></td>
          <td>$<?= number_format((float)($r['total_spend'] ?? 0), 2) ?></td>
          <td class="act">
            <a class="primary" href="new_order.php?user_id=<?= (int)$r['id'] ?>">Place Order</a>
            <a class="green" href="impersonate.php?user_id=<?= (int)$r['id'] ?>" onclick="return confirm('Login as this customer?');">Login as Customer</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</script>

</body></html>

