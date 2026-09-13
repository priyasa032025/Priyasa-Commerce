<?php

return [
    'currency' => env('PRIYASA_CURRENCY', 'INR'),
    'invoice_number_prefix' => env('PRIYASA_INVOICE_PREFIX', 'PRI-INV'),
    'gstin' => env('PRIYASA_GSTIN'),
    'seller_state' => env('PRIYASA_SELLER_STATE'),
    'order_number_prefix' => env('PRIYASA_ORDER_PREFIX', 'PRI'),
    'reservation_minutes' => (int) env('PRIYASA_STOCK_RESERVATION_MINUTES', 15),
    'default_tax_rate' => (float) env('PRIYASA_DEFAULT_TAX_RATE', 0),
    'payment_provider' => env('PRIYASA_PAYMENT_PROVIDER', 'razorpay'),
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'shiprocket' => [
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'pickup_pincode' => env('SHIPROCKET_PICKUP_PINCODE'),
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
        'channel_id' => env('SHIPROCKET_CHANNEL_ID'),
        'weight_kg' => (float) env('SHIPROCKET_DEFAULT_WEIGHT_KG', 0.5),
        'length_cm' => (float) env('SHIPROCKET_DEFAULT_LENGTH_CM', 20),
        'breadth_cm' => (float) env('SHIPROCKET_DEFAULT_BREADTH_CM', 15),
        'height_cm' => (float) env('SHIPROCKET_DEFAULT_HEIGHT_CM', 5),
    ],
    'woocommerce' => [
        'store_url' => env('WOOCOMMERCE_STORE_URL'),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
        'timeout' => (int) env('WOOCOMMERCE_TIMEOUT', 30),
        'webhook_secret' => env('WOOCOMMERCE_WEBHOOK_SECRET'),
    ],
    'per_page' => min(100, max(10, (int) env('PRIYASA_API_PER_PAGE', 24))),
    'admin_otp' => [
        'expiry' => (int) env('ADMIN_OTP_EXPIRY', 300),
        'resend_after' => (int) env('ADMIN_OTP_RESEND_AFTER', 60),
        'max_attempts' => (int) env('ADMIN_OTP_MAX_ATTEMPTS', 5),
    ],
];
