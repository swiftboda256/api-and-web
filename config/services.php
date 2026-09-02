<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'egosms' => [
        'base_url' => env('EGOSMS_BASE_URL', 'https://comms.egosms.co/api/v1/json/'),
        'username' => env('EGOSMS_USERNAME'),
        'password' => env('EGOSMS_PASSWORD'),
        'sender_id' => env('EGOSMS_SENDER_ID'),
        'priority' => env('EGOSMS_PRIORITY', '0'),
    ],

    'yo' => [
        'base_url' => env('YO_BASE_URL', 'https://paymentsapi1.yo.co.ug/ybs/task.php'),
        'api_username' => env('YO_API_USERNAME'),
        'api_password' => env('YO_API_PASSWORD'),
        'account_provider_code' => env('YO_ACCOUNT_PROVIDER_CODE'),
        'ipn_public_key_path' => env('YO_IPN_PUBLIC_KEY'),
    ],

];
