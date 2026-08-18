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

    // Roadmap task 4.1 — this redirects the raw legacy .php path only.
    // The pretty URL (dumped-lectures.htm) IS a real, live .htaccess rule
    // (`.htaccess:221`) with a real homepage link (home_functions.php:398)
    // — corrected in G-12 (G-12-04); it is registered directly in
    // routes/content.php at its own exact path, not redirected here.
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

    // Roadmap task 3.4 (added post-Wave-4/-5-analysis). Every pretty URL
    // this module was ever meant to have routes through the confirmed-
    // absent new_modules.php (IF-026's pattern, routes/engagement.php's
    // own comment) — only the raw file paths are genuinely reachable.
    // `surveys/item.php` is NOT redirected here: it's a single file
    // multiplexing 3 different behaviors (show/results/vote) purely via
    // query string, which doesn't reduce to one clean redirect target —
    // a real, honest limitation, not silently ignored.
    'surveys/polls.php' => [
        'type' => 'redirect',
        'to' => '/polls',
    ],

    // Roadmap task 6.3. Same defensive shape as pages/social.php above:
    // `/ramadan.htm` is kept at its exact path directly in routes/pages.php
    // (a real .htaccess rule already targets it), this entry only protects
    // the raw `.php` file path itself. `ramadan1442.php`/`ramadan-archive.php`
    // never had an .htaccess rule or a found internal link at all
    // (pages.md §2/§11) — redirected here anyway, to the same consolidated
    // view, for the same "a bookmark/search engine could still reference
    // it" reasoning applied to every other raw-path-only file in this map;
    // their own distinct content (the "1446"/"1445" duplicate-bug section,
    // the object-syntax counter path) is not preserved by this redirect,
    // only their reachability is.
    'pages/ramadan.php' => [
        'type' => 'redirect',
        'to' => '/ramadan.htm',
    ],

    'pages/ramadan1442.php' => [
        'type' => 'redirect',
        'to' => '/ramadan.htm',
    ],

    'pages/ramadan-archive.php' => [
        'type' => 'redirect',
        'to' => '/ramadan.htm',
    ],

    'help/share.php' => [
        'type' => 'redirect',
        'to' => '/share.htm',
    ],

];
