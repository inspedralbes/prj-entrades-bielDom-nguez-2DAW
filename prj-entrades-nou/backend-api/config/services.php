<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Here you may configure credentials for third party services.
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

    'ticketmaster' => [
        'key' => env('TICKETMASTER_API_KEY'),
        'base_url' => env('TICKETMASTER_DISCOVERY_BASE_URL', 'https://app.ticketmaster.com/discovery/v2'),
        'disabled' => env('TICKETMASTER_DISABLED', false),
    ],

    'socket' => [
        'internal_url' => env('SOCKET_SERVER_INTERNAL_URL'),
        'internal_secret' => env('SOCKET_INTERNAL_SECRET'),
    ],

    'order' => [
        'stub_unit_price' => (float) env('ORDER_STUB_UNIT_PRICE', 25.0),
    ],

];
