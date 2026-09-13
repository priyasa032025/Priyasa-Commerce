<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    */

    'default' => env('SMS_DRIVER', 'placeholder'),

    'enabled' => env('COMMUNICATION_SMS_ENABLED', true),

    'timeout' => (int) env('SMS_TIMEOUT', 30),

    'connect_timeout' => (int) env('SMS_CONNECT_TIMEOUT', 10),

    'retry_attempts' => (int) env('SMS_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Placeholder Driver
    |--------------------------------------------------------------------------
    |
    | Used during development.
    | Replace with actual provider in production.
    |
    */

    'placeholder' => [

        'enabled' => env('SMS_PLACEHOLDER_ENABLED', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | MSG91
    |--------------------------------------------------------------------------
    */

    'msg91' => [

        'enabled' => env('MSG91_ENABLED', false),

        'base_url' => env(
            'MSG91_BASE_URL',
            'https://control.msg91.com/api/v5'
        ),

        'auth_key' => env('MSG91_AUTH_KEY'),

        'sender_id' => env('MSG91_SENDER_ID'),

        'template_id' => env('MSG91_TEMPLATE_ID'),

    ],

    /*
    |--------------------------------------------------------------------------
    | TextLocal
    |--------------------------------------------------------------------------
    */

    'textlocal' => [

        'enabled' => env('TEXTLOCAL_ENABLED', false),

        'api_key' => env('TEXTLOCAL_API_KEY'),

        'sender' => env('TEXTLOCAL_SENDER'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Route Mobile
    |--------------------------------------------------------------------------
    */

    'routemobile' => [

        'enabled' => env('ROUTEMOBILE_ENABLED', false),

        'username' => env('ROUTEMOBILE_USERNAME'),

        'password' => env('ROUTEMOBILE_PASSWORD'),

        'sender' => env('ROUTEMOBILE_SENDER'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio SMS
    |--------------------------------------------------------------------------
    */

    'twilio' => [

        'enabled' => env('TWILIO_SMS_ENABLED', false),

        'account_sid' => env('TWILIO_ACCOUNT_SID'),

        'auth_token' => env('TWILIO_AUTH_TOKEN'),

        'from' => env('TWILIO_SMS_FROM'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Custom SMS Provider
    |--------------------------------------------------------------------------
    */

    'custom' => [

        'enabled' => env('CUSTOM_SMS_ENABLED', false),

        'endpoint' => env('CUSTOM_SMS_ENDPOINT'),

        'token' => env('CUSTOM_SMS_TOKEN'),

        'method' => env('CUSTOM_SMS_METHOD', 'POST'),

    ],

];