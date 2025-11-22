<?php
require 'config.php';

// Prices from your Stripe dashboard (replace with real IDs)
$prices = [
    'small' => 'price_1RvfqKAuq4Y1kCpx0g7Uk2Uv',
    'medium' => 'price_1RvftKAuq4Y1kCpxqlSKLS5R',
    'large' => 'price_1Rvfv0Auq4Y1kCpxwdrjqPuF',
    'oversized' => 'price_1RvfwPAuq4Y1kCpx0t7qZJCf',
];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? null;

if (!$type || !isset($prices[$type])) {
    echo json_encode(['error' => 'Invalid package type']);
    exit;
}

try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price' => $prices[$type],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://yourdomain.com/success.html',
        'cancel_url' => 'https://yourdomain.com/cancel.html',
    ]);

    echo json_encode(['url' => $checkout_session->url]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
