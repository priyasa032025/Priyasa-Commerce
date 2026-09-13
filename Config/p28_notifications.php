<?php
return [
    'fcm' => [
        'enabled' => (bool) env('PRIYASA_FCM_ENABLED', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'access_token' => env('FCM_ACCESS_TOKEN'),
        'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/v1/projects/%s/messages:send'),
    ],
    'deep_links' => [
        'scheme' => env('PRIYASA_DEEP_LINK_SCHEME', 'priyasa'),
        'web_url' => rtrim(env('PRIYASA_WEB_URL', env('APP_URL', 'http://localhost')), '/'),
    ],
    'inbox' => ['page_size' => 30],
];
