<?php

return [
    'reservation_ttl_seconds' => (int) env('PRIYASA_INVENTORY_RESERVATION_TTL', 900),
    'max_checkout_key_length' => 191,
    'queue' => env('PRIYASA_CHECKOUT_QUEUE', 'default'),
];
