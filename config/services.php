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

        // N1 / R14-A (2026-11-08): the forum's cookie prefix — vBulletin names its
        // auth cookies `{prefix}sessionhash`, `{prefix}userid`, `{prefix}password`.
        // One prefix only; the guard and the EncryptCookies exemption both read it
        // (App\Domain\Identity\Support\VbulletinCookieNames). `bb_` keeps the names
        // Gen 2's check_login() reads; the production value is UNKNOWN until the
        // forum's `cookie_prefix` is verified.
        'cookie_prefix' => env('VBULLETIN_COOKIE_PREFIX', 'bb_'),

        // D1 (2026-11-09, decision-log #138): forum session IP binding, mirrored from the forum's own settings —
        // App\Domain\Identity\Support\VbulletinSessionBinding validates these (fail closed) and reproduces
        // vBulletin's idhash (ALT_IP) and session.host (IPADDRESS) checks. Raw scalars, so config:cache-safe.
        // Defaults reproduce the previous Laravel behaviour (3 octets, 900 s, no proxy list). Production values
        // are UNVERIFIED: mirror the forum's `ipcheck` / `cookietimeout` options and its config.php
        // `$config['Misc']['proxyiplist']` / `['proxyipheader']` only after verification.
        'ipcheck' => env('VBULLETIN_IPCHECK', 1),
        // ipcheck=4 disables IP binding entirely; it is refused unless this explicit approval flag is true.
        'ipcheck_allow_unbound' => env('VBULLETIN_IPCHECK_ALLOW_UNBOUND', false),
        'cookie_timeout' => env('VBULLETIN_COOKIE_TIMEOUT', 900),
        'proxy_ip_list' => env('VBULLETIN_PROXY_IP_LIST', ''),
        'proxy_ip_header' => env('VBULLETIN_PROXY_IP_HEADER', ''),
    ],

];
