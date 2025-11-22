<?php
function send_profile_updated(int $userId, mysqli $conn=null): bool {
  try {
    if (!($conn instanceof mysqli)) return false;
    $st=$conn->prepare("SELECT email, COALESCE(NULLIF(first_name,''), username, email) AS name
                        FROM users WHERE id=? LIMIT 1");
    $st->bind_param('i',$userId); $st->execute();
    $u=$st->get_result()->fetch_assoc(); $st->close();
    if (!$u || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return false;

    $to   = $u['email'];
    $name = (string)$u['name'];
    $subj = "Your profile was updated";
    $body = "Hi {$name},\n\nYour profile was updated. If this wasn't you, reply to this email.";
    $hdr  = "From: no-reply@3grandslogistics.com\r\n";
    // TEMP: also send a copy to you while testing:
    // $hdr .= "Bcc: you@yourdomain.com\r\n";

    $ok = mail($to,$subj,$body,$hdr);
    if (!$ok) error_log("mail() false in send_profile_updated for uid=$userId");
    return $ok;
  } catch (Throwable $e) {
    error_log('send_profile_updated error: '.$e->getMessage());
    return false;
<?php
// ... keep your existing functions above

if (!function_exists('send_order_cancelled')) {
  function send_order_cancelled(int $orderId, mysqli $conn = null): bool {
    try {
      $to = '';
      if ($conn instanceof mysqli) {
        $st = $conn->prepare("
          SELECT COALESCE(u.email,'') AS email
          FROM orders o LEFT JOIN users u ON u.id=o.user_id
          WHERE o.order_id=? LIMIT 1
        ");
        $st->bind_param('i',$orderId);
        $st->execute();
        if ($row = $st->get_result()->fetch_assoc()) $to = (string)$row['email'];
        $st->close();
      }
      if ($to === '') return false;

      $sub = "Order #$orderId cancelled";
      $msg = "Hello,\n\nYour pickup (order #$orderId) has been cancelled. "
           . "If this was a mistake, reply to this email and we’ll help.\n\n"
           . "— 3 Grands Logistics";
      $hdr = "From: no-reply@3grandslogistics.com\r\n";
      return @mail($to, $sub, $msg, $hdr);
    } catch (Throwable $e) {
      error_log('send_order_cancelled: '.$e->getMessage());
      return false;
    }


