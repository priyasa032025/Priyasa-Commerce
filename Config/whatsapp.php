<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active WhatsApp Driver
    |--------------------------------------------------------------------------
    */

    'default' => env('WHATSAPP_DRIVER', 'meta'),

    /*
    |--------------------------------------------------------------------------
    | Global Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('COMMUNICATION_WHATSAPP_ENABLED', true),

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),

    'verify_ssl' => env('WHATSAPP_VERIFY_SSL', true),

    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 10),

    'retry_attempts' => (int) env('WHATSAPP_RETRY_ATTEMPTS', 3),

    'default_language' => env('WHATSAPP_DEFAULT_LANGUAGE', 'en_US'),

    'templates' => [
        'login_otp' => env('WHATSAPP_LOGIN_OTP_TEMPLATE', 'priyasa_otp'),
        'authentication_button' => env('WHATSAPP_AUTHENTICATION_BUTTON', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Cloud API
    |--------------------------------------------------------------------------
    */

    'meta' => [

        'enabled' => env('META_ENABLED', true),

        'base_url' => env(
            'META_BASE_URL',
            'https://graph.facebook.com/v23.0'
        ),

        'phone_number_id' => env('META_PHONE_NUMBER_ID'),

        'business_account_id' => env('META_BUSINESS_ACCOUNT_ID'),

        'access_token' => env('META_ACCESS_TOKEN'),

        'verify_token' => env('META_VERIFY_TOKEN'),

        'webhook_secret' => env('META_WEBHOOK_SECRET'),

    ],

    /*
    |--------------------------------------------------------------------------
    | AiSensy
    |--------------------------------------------------------------------------
    */

    'aisensy' => [

        'enabled' => env('AISENSY_ENABLED', false),

        'base_url' => env('AISENSY_BASE_URL'),

        'api_key' => env('AISENSY_API_KEY'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Interakt
    |--------------------------------------------------------------------------
    */

    'interakt' => [

        'enabled' => env('INTERAKT_ENABLED', false),

        'base_url' => env('INTERAKT_BASE_URL'),

        'api_key' => env('INTERAKT_API_KEY'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Gupshup
    |--------------------------------------------------------------------------
    */

    'gupshup' => [

        'enabled' => env('GUPSHUP_ENABLED', false),

        'app_name' => env('GUPSHUP_APP_NAME'),

        'api_key' => env('GUPSHUP_API_KEY'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio WhatsApp
    |--------------------------------------------------------------------------
    */

    'twilio' => [

        'enabled' => env('TWILIO_WHATSAPP_ENABLED', false),

        'account_sid' => env('TWILIO_ACCOUNT_SID'),

        'auth_token' => env('TWILIO_AUTH_TOKEN'),

        'from' => env('TWILIO_WHATSAPP_FROM'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Provider
    |--------------------------------------------------------------------------
    */

    'custom' => [

        'enabled' => env('CUSTOM_WHATSAPP_ENABLED', false),

        'endpoint' => env('CUSTOM_WHATSAPP_ENDPOINT'),

        'token' => env('CUSTOM_WHATSAPP_TOKEN'),

    ],

];
