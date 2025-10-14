<?php
require __DIR__ . '/vendor/stripe/init.php';

// LIVE key for production — replace with your actual live key
\Stripe\Stripe::setApiKey('sk_test_xxxxxxxxxxxxxxxxxxxxx');
