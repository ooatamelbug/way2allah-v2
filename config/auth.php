<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Wave 0 completion review: Laravel's stock 'web'/'users' guard (backed
    | by App\Models\User) is removed entirely, not just left unused —
    | ADR-0011 defines exactly two identity mechanisms for this application,
    | and a third, half-wired one sitting in config was a real footgun for
    | whoever writes the first controller and reaches for the default
    | Auth::user(). 'vbulletin' is the default since it's the public-facing
    | guard; nothing currently relies on this default (every Wave 0-1 test
    | resolves a Guard explicitly), so this is a deliberate, low-risk choice
    | rather than a load-bearing one.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'vbulletin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Two guards, both custom (ADR-0011) — neither delegates to Laravel's
    | UserProvider layer, so neither needs a 'provider' key or an entry in
    | the 'providers' array below (which is why that array is now empty).
    |
    */

    'guards' => [
        // Wave 0, task 0.3 (ADR-0011, Blueprint v1.0 §9) — public-site auth.
        // Resolves users itself directly against vBulletin's database (the
        // 'vbulletin' connection, config/database.php); no 'provider' key
        // needed since it never delegates to Laravel's UserProvider layer.
        'vbulletin' => [
            'driver' => 'vbulletin-session',
        ],

        // Wave 0, task 0.4 (ADR-0011) — admin auth, backed by nuke_authors.
        // Deliberately not unified with the 'vbulletin' guard above — see
        // AdminGuard's class docblock. No 'provider' key needed, same
        // reasoning as the 'vbulletin' guard.
        'admin' => [
            'driver' => 'admin-guard',
        ],
    ],

    // 'providers' and 'passwords' (Laravel's stock Eloquent-provider /
    // password-reset-broker config) are intentionally absent — neither
    // VbulletinSessionGuard nor AdminGuard uses a UserProvider, and neither
    // identity mechanism does password-reset-by-email (public identity is
    // owned by vBulletin; admin credential recovery, if ever needed, is an
    // Admin-domain concern to design later, not a stock Laravel feature to
    // leave dangling against a deleted User model).

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
