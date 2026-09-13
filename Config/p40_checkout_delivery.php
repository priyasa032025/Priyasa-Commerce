<?php

return [
    'serviceable_pincodes' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRIYASA_SERVICEABLE_PINCODES', ''))))),
    'blocked_pincodes' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRIYASA_BLOCKED_PINCODES', ''))))),
    'shipping_charge' => (float) env('PRIYASA_DEFAULT_SHIPPING_CHARGE', 0),
    'free_shipping_above' => env('PRIYASA_FREE_SHIPPING_ABOVE') !== null ? (float) env('PRIYASA_FREE_SHIPPING_ABOVE') : null,
    'cod_enabled' => (bool) env('PRIYASA_COD_ENABLED', true),
    'cod_min_order' => (float) env('PRIYASA_COD_MIN_ORDER', 0),
    'cod_max_order' => env('PRIYASA_COD_MAX_ORDER') !== null ? (float) env('PRIYASA_COD_MAX_ORDER') : null,
    'default_eta' => env('PRIYASA_DEFAULT_DELIVERY_ETA', '3-7 business days'),
];
