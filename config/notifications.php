<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | RC Driving Academy sends transactional notifications (registration
    | confirmation, payment receipt, certificate issued, session reminders)
    | via SMS and Email. Each channel is driver-based and degrades gracefully:
    | if its credentials are absent from the environment, the channel is
    | treated as "disabled" and the message is logged (channel = disabled)
    | instead of being sent. This lets the app run fully without keys; the
    | real providers are wired up later by populating .env.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'semaphore'), // semaphore | array | log
        'from' => env('SMS_FROM', 'RCDA'),
        'enabled' => env('SMS_ENABLED', false),
    ],

    'mail' => [
        // Reuses Laravel's mailer (config/mail.php). When MAIL_MAILER=log the
        // mail is written to storage/logs/laravel.log instead of delivered.
        'enabled' => env('MAIL_ENABLED', true),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'no-reply@rcdriving.ph'),
            'name' => env('MAIL_FROM_NAME', 'RC Driving Academy'),
        ],
    ],

    'maps' => [
        'driver' => env('MAPS_DRIVER', 'google'),
        'enabled' => env('MAPS_ENABLED', false),
    ],

];
