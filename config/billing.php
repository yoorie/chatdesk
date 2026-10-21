<?php

return [
    'default_plan_key' => env('DEFAULT_PLAN_KEY', 'starter'),

    // Production endpoints. Override only for a local fake gateway.
    'stripe_api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com/v1'),
    'razorpay_api_base' => env('RAZORPAY_API_BASE', 'https://api.razorpay.com/v1'),
    'paypal_api_base' => env('PAYPAL_API_BASE'),
];
