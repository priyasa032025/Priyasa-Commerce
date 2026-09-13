<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provider Priority
    |--------------------------------------------------------------------------
    |
    | Providers are attempted in this order.
    |
    */

    'priority' => [

        'whatsapp',

        'sms',

    ],

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable Channels
    |--------------------------------------------------------------------------
    */

    'channels' => [

        'whatsapp' => env('COMMUNICATION_WHATSAPP_ENABLED', true),

        'sms' => env('COMMUNICATION_SMS_ENABLED', false),

        'firebase' => env('COMMUNICATION_FIREBASE_ENABLED', false),

        'email' => env('COMMUNICATION_EMAIL_ENABLED', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Active Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'whatsapp' => env('WHATSAPP_DRIVER', 'meta'),

        'sms' => env('SMS_DRIVER', 'placeholder'),

        'firebase' => env('FIREBASE_DRIVER', 'fcm'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Drivers
    |--------------------------------------------------------------------------
    */

    'supported' => [

        'whatsapp' => [

            'meta',

        ],

        'sms' => [

            'placeholder',

        ],

        'firebase' => [

            'fcm',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Policy
    |--------------------------------------------------------------------------
    */

    'retry' => [

        'enabled' => true,

        'max_attempts' => 3,

        'delay_seconds' => 5,

    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    */

    'health' => [

        'enabled' => true,

        'cache_seconds' => 60,

    ],

];