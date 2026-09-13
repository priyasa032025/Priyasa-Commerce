<?php
return [
    'enabled' => (bool) env('PRIYASA_ORDER_NOTIFICATIONS_ENABLED', true),
    'queue' => env('PRIYASA_NOTIFICATION_QUEUE', 'notifications'),
];
