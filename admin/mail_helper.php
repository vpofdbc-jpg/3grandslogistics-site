<?php
// /admin/mail_helper.php
declare(strict_types=1);

// ---- BASIC CONFIG (edit these) ----
const MAIL_FROM      = 'no-reply@3grandslogistics.com';
const MAIL_FROM_NAME = '3 Grands Logistics';
const MAIL_REPLY_TO  = 'contact@3grandslogistics.com';
const MAIL_BCC_ADMIN = 'contact@3grandslogistics.com'; // optional; '' to disable

// If you have SMTP later, swap this and use PHPMailer. For now: native mail().
function send_mail_html(string $to, string $subject, string $html, string $textFallback = ''): bool {
    $fromName = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8');
    $headers  = [];
    $headers[] = "From: {$fromName} <" . MAIL_FROM . ">";
    if (MAIL_REPLY_TO) $headers[] = "Reply-To: " . MAIL_REPLY_TO;
    if (MAIL_BCC_ADMIN) $headers[] = "Bcc: " . MAIL_BCC_ADMIN;
    $headers[] = "MIME-Version: 1.0";
    $boundary = md5((string)microtime(true));
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $text = $textFallback !== '' ? $textFallback : strip_tags($html);

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}--";

    return @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $body, implode("\r\n", $headers));
}

/**
 * Notify customer when order status changes (or when a new order is created).
 * $newStatus should be one of: Pending, In Transit, Delivered, Cancelled
 */
function notify_order_status(mysqli $conn, int $orderId, string $newStatus): void {
    // Get customer + order info
    $q = $conn->prepare("
        SELECT o.order_id, o.status, o.pickup_address, o.delivery_address, o.created_at,
               u.email, COALESCE(NULLIF(CONCAT(u.first_name,' ',u.last_name), ' '), u.username) AS name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.order_id = ?
        LIMIT 1
    ");
    $q->bind_param('i', $orderId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    if (!$row || empty($row['email'])) return;

    $customerEmail = (string)$row['email'];
    $customerName  = trim((string)($row['name'] ?? 'Customer'));
    $pickup        = (string)$row['pickup_address'];
    $dest          = (string)$row['delivery_address'];

    // Subject + blurb by status
    $blurb = [
        'Pending'    => "We've received your pickup request and are preparing it.",
        'In Transit' => "Your package is on the move!",
        'Delivered'  => "Your package has been delivered. Thank you for using 3 Grands Logistics.",
        'Cancelled'  => "Your order has been cancelled. If this was unexpected, please contact support.",
    ][$newStatus] ?? "Your order status was updated.";

    $subject = "Order #{$orderId} – {$newStatus}";
    $html = "
      <div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333'>
        <h2 style='margin:0 0 8px'>Order #{$orderId}: {$newStatus}</h2>
        <p>Hi {$customerName},</p>
        <p>{$blurb}</p>
        <table style='border-collapse:collapse'>
          <tr><td style='padding:4px 8px;color:#666'>Pickup:</td><td style='padding:4px 8px'>{$pickup}</td></tr>
          <tr><td style='padding:4px 8px;color:#666'>Destination:</td><td style='padding:4px 8px'>{$dest}</td></tr>
        </table>
        <p style='margin-top:14px'>You can view your recent orders by logging into your dashboard.</p>
        <p style='color:#666'>— ".MAIL_FROM_NAME."</p>
      </div>";
    $text = "Order #{$orderId}: {$newStatus}\n\n{$blurb}\nPickup: {$pickup}\nDestination: {$dest}\n\n— ".MAIL_FROM_NAME;

    // Fire and forget (ignore failure to avoid breaking the UI)
    try { send_mail_html($customerEmail, $subject, $html, $text); } catch (\Throwable $e) {}
}
