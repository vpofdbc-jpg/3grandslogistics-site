<?php
declare(strict_types=1);

// Minimal logging for cron; keep output tiny.
ini_set('display_errors','0'); error_reporting(E_ALL);

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Prevent overlapping runs (lock file).
$lockFile = __DIR__ . '/send_notifications.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit; }

$rows = $conn->query("
  SELECT nq.id, nq.user_id, nq.order_id, nq.payload, u.email
  FROM notifications_queue nq
  JOIN users u ON u.id = nq.user_id
  WHERE nq.status = 'pending'
  ORDER BY nq.id ASC
  LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$processed = 0;

foreach ($rows as $r) {
  $email = trim((string)$r['email']);
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $status = 'failed';
  } else {
    // Set headers to reduce spam flags; change domains to yours.
    $fromEmail = 'no-reply@3grandslogistics.com';
    $headers   = "From: 3Grands Logistics <{$fromEmail}>\r\n"
               . "Reply-To: support@3grandslogistics.com\r\n";
    // Some hosts require envelope sender (-f).
    $status = @mail($email, "Order Update", (string)$r['payload'], $headers, "-f {$fromEmail}") ? 'sent' : 'failed';
  }

  $st = $conn->prepare("UPDATE notifications_queue SET status=?, sent_at=NOW() WHERE id=?");
  $st->bind_param('si', $status, $r['id']);
  $st->execute();
  $st->close();

  $processed++;
}

flock($lock, LOCK_UN);
fclose($lock);

echo "processed={$processed}\n";
