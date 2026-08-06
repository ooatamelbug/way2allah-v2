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

    // TODO(Wave 0, task 0.3): requires VBULLETIN_COOKIE_SALT in .env — the
    // secret vBulletin hashes into the `bb_password` cookie alongside the
    // user's stored password (see VbulletinSessionGuard::viaCredentialCookie()).
    // The legacy application's current value lives only in the legacy
    // w2a_config.php's $vbhash variable — copy it into a real, untracked
    // .env when configuring a real environment. Never commit the actual
    // value into this file, .env.example, or any tracked source file.
    'vbulletin' => [
        'cookie_salt' => env('VBULLETIN_COOKIE_SALT'),
    ],

];
