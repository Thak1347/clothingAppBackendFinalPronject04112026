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

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_ids' => env('TELEGRAM_ADMIN_CHAT_IDS'),
    ],
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],
    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'khqr'),
        'anajakpay' => [
            'profile_id' => env('ANJAKPAY_PROFILE_ID'),
            'secret' => env('ANJAKPAY_SECRET'),
        ],
        'khqr' => [
            'base_url' => env('KHQR_BASE_URL', 'https://anajakpay.com/api'),
            'profile_id' => env('KHQR_PROFILE_ID'),
            'secret_key' => env('KHQR_SECRET_KEY', env('KHQR_SECRET')),
            'profile_key' => env('KHQR_PROFILE_KEY'),
            'webhook_url' => env('KHQR_WEBHOOK_URL', env('KHQR_SUCCESS_URL')),
            'success_url' => env('KHQR_SUCCESS_URL'),
            'checkout_return_url' => env('KHQR_CHECKOUT_RETURN_URL'),
            'client_return_url' => env('FRONTEND_PAYMENT_RETURN_URL', env('KHQR_CLIENT_RETURN_URL')),
            'checkout_url' => env('KHQR_CHECKOUT_URL', 'https://anajakpay.com/api/payment/request'),
            'checkout_success_url' => env('KHQR_CHECKOUT_SUCCESS_URL'),
            'timeout' => (int) env('KHQR_TIMEOUT', 15),
        ],
    ],
];
