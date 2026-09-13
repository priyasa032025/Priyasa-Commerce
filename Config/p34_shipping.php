<?php
return [
    'provider' => env('PRIYASA_SHIPPING_PROVIDER', 'shiprocket'),
    'timeout' => (int) env('PRIYASA_SHIPPING_TIMEOUT', 20),
    'retry' => (int) env('PRIYASA_SHIPPING_RETRY', 2),
    'shiprocket' => [
        'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external'),
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
        'channel_id' => env('SHIPROCKET_CHANNEL_ID'),
        'default_weight_kg' => (float) env('SHIPROCKET_DEFAULT_WEIGHT_KG', 0.5),
        'default_length_cm' => (float) env('SHIPROCKET_DEFAULT_LENGTH_CM', 10),
        'default_breadth_cm' => (float) env('SHIPROCKET_DEFAULT_BREADTH_CM', 10),
        'default_height_cm' => (float) env('SHIPROCKET_DEFAULT_HEIGHT_CM', 10),
    ],
];
