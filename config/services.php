<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'RCDA'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'paymongo' => [
        'public' => env('PAYMONGO_PUBLIC_KEY'),
        'secret' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'api_url' => env('PAYMONGO_API_URL', 'https://api.paymongo.com/v1'),
        // Comma-separated payment method types enabled on the merchant account.
        // Valid: card, gcash, grab_pay, paymaya, billease, dob, qrph.
        'payment_methods' => env('PAYMONGO_PAYMENT_METHODS', ''),
        'timeout' => (int) env('PAYMONGO_TIMEOUT', 20),
        // Optional webhook replay window in seconds. 0 disables the check.
        'webhook_tolerance' => (int) env('PAYMONGO_WEBHOOK_TOLERANCE', 0),
    ],

    'nlp' => [
        'driver' => env('NLP_DRIVER', 'local'),
        'url' => env('NLP_SERVICE_URL', ''),
    ],

];
