<?php

/*
|--------------------------------------------------------------------------
| Legacy URL Compatibility Map (Blueprint v1.0 §11, Roadmap task 0.7)
|--------------------------------------------------------------------------
|
| Skeleton only — empty until Wave 2 starts populating it from
| 00-url-inventory.md's full 217-rule .htaccess-derived table. Every
| legacy URL pattern that must keep working after migration gets one entry
| here, keyed by the exact legacy path.
|
| Two rule shapes:
|
|   'legacy/path' => [
|       'type' => 'redirect',      // path genuinely changes
|       'to' => '/new/path',
|       'status' => 301,           // optional, defaults to 301
|   ],
|
|   'legacy/path' => [
|       'type' => 'pass-through',  // path stays the same, served by Laravel
|       'to' => [SomeController::class, 'method'],
|       'name' => 'optional.route.name',
|   ],
|
| See App\Support\LegacyUrlCompatibility\UrlMapRouteRegistrar for how each
| rule becomes a real route, and routes/legacy-compat.php for where this
| map is consumed.
|
*/

return [

    // Roadmap task 2.2. Both confirmed via the audit (pages.md §2 row 25,
    // help.md §2 row 20) to have NO matching .htaccess rewrite rule — each
    // was only ever reachable at its raw legacy path, ".php" extension and
    // all. That raw path is still the "legacy URL" worth protecting here
    // (a search engine or bookmark could plausibly reference it even
    // without a pretty-URL rewrite ever having existed for it).

    'pages/privacy.php' => [
        'type' => 'redirect',
        'to' => '/privacy',
    ],

    'help/about.php' => [
        'type' => 'redirect',
        'to' => '/about',
    ],

    // Roadmap task 2.4 — same "no .htaccess rule, raw-path-only" profile
    // (pages.md §2 row 26).
    'pages/mobile-app.php' => [
        'type' => 'redirect',
        'to' => '/mobile-app',
    ],

    // Renamed per pages.md §5's own recommendation — see
    // VisitorFeedbackController's docblock.
    'pages/estebian.php' => [
        'type' => 'redirect',
        'to' => '/visitor-feedback',
    ],

    'pages/mo7fzat-quran.php' => [
        'type' => 'redirect',
        'to' => '/quran-memorization-application',
    ],

    'pages/tatw3-w2a-team.php' => [
        'type' => 'redirect',
        'to' => '/volunteer',
    ],

    // Roadmap task 3.3. live.php has no .htaccess rule at all (confirmed —
    // live-stream.md §2), same raw-path-only profile as Wave 2's pages.
    // Everything else in Wave 3 (live-stream.htm, live-channel-{id}.htm,
    // channels.htm, channel-{id}.htm, channel-{id}-{id2}.htm) is a real,
    // live .htaccess rule and keeps its exact path directly in
    // routes/content.php instead — no redirect needed for those.
    'live-stream/live.php' => [
        'type' => 'redirect',
        'to' => '/live-stream/featured',
    ],

    // Roadmap task 4.1 — khotab/dump.php has no .htaccess rule at all
    // (confirmed by exhaustive search), same raw-path-only profile as
    // live-stream/live.php above.
    'khotab/dump.php' => [
        'type' => 'redirect',
        'to' => '/khotab/dump',
    ],

    // Same raw-path-only profile — see IF-018's reachability note.
    'khotab/search.php' => [
        'type' => 'redirect',
        'to' => '/khotab/search',
    ],

    // Roadmap task 2.5 (added post-Wave-4). The real legacy URL worth
    // protecting is `social.htm` — kept at its exact path directly in
    // routes/pages.php, not redirected, since it's a real standing nav
    // link (SocialController's docblock). This entry is defensive only:
    // the raw `pages/social.php` file itself was always directly
    // reachable too (Apache serves .php files regardless of .htaccess),
    // even though nothing in the codebase was found linking to it that
    // way (grep confirmed only header.php's social.htm references).
    'pages/social.php' => [
        'type' => 'redirect',
        'to' => '/social.htm',
    ],

];
