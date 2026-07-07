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

    /*
    | Firebase Cloud Messaging (FCM v1) — used by the native WebView shell for
    | push notifications. `project_id` comes from the Firebase project; the
    | server authenticates to the FCM v1 HTTP API with a service-account JSON
    | key (NOT google-services.json) pointed to by `credentials`. The send path
    | (FcmService) is wired separately; this block only holds the config.
    */
    'fcm' => [
        // `?:` (not env's 2nd arg) so an empty `FCM_*=` line in .env falls back
        // to the default instead of overriding it with an empty string.
        'project_id' => env('FCM_PROJECT_ID') ?: 'shfworld-loans',
        'credentials' => env('FCM_CREDENTIALS') ?: storage_path('app/firebase/service-account.json'),
    ],

];
