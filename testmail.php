<?php
$to = "your@email.com"; // <-- replace with YOUR email
$subject = "Test Mail from 3Grands Logistics";
$message = "Hello! This is a test email from your server.";
$headers = "From: contact@3grandslogistics.com";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ Test email sent to $to";
} else {
    echo "❌ Mail function failed. Check error log or SMTP settings.";
}
