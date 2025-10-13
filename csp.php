<?php
// /csp.php — include BEFORE any HTML output
if (!headers_sent()) {
  header_remove('Content-Security-Policy');
  header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://embed.tawk.to https://va.tawk.to https://static.tawk.to https://js.stripe.com; connect-src 'self' https://api.stripe.com https://q.stripe.com https://*.tawk.to wss://*.tawk.to; img-src 'self' data: https://*.tawk.to; style-src 'self' 'unsafe-inline' https://static.tawk.to; frame-src 'self' https://*.tawk.to https://js.stripe.com");
}
