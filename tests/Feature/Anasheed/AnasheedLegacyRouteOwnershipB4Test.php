<?php

/*
 * F08 / B4 — public route ownership for Variety URLs. Gen 2's rewrite rules (`7amlat/.htaccess:96-109, 139-140,
 * 149, 174, 321-322`) are listed here verbatim. Each pretty URL is either served by a Laravel route (and so by the
 * B4 visibility rules), or documented as NOT served by Laravel: if production still routes it to legacy PHP, a hidden
 * item may remain reachable there (B4 report §3). Raw legacy PHP paths are not part of Laravel's docroot.
 */

use Illuminate\Http\Request;

/** Gen 2 rule (pattern with sample ids) => the HTTP method Laravel serves it with. */
const B4_LARAVEL_OWNED = [
    'var-download-([0-9]*).htm' => ['GET', '/var-download-1.htm'],
    'var-group-([0-9]*).htm' => ['GET', '/var-group-1.htm'],
    'var-group-([0-9]+)-page-([0-9]*).htm' => ['GET', '/var-group-1-page-2.htm'],
    'var-item-([0-9]*).htm' => ['GET', '/var-item-1.htm'],
    'var-series-([0-9]*).grx' => ['GET', '/var-series-1.grx'],
    'send-friend-anasheed-([0-9]*).htm' => ['POST', '/send-friend-anasheed-1.htm'],
    'var-mirror-([0-9]+)-([0-9]*).htm' => ['GET', '/var-mirror-1-2.htm'],
    'var-item-([0-9]+)-page-([0-9]*).htm' => ['GET', '/var-item-1-page-2.htm'],
    'exclusive-news.htm' => ['GET', '/exclusive-news.htm'],
    'cartoon-news.htm' => ['GET', '/cartoon-news.htm'],
    'documentary-news.htm' => ['GET', '/documentary-news.htm'],
    'anasheed-news.htm' => ['GET', '/anasheed-news.htm'],
    'var-categories.htm' => ['GET', '/var-categories.htm'],
    'var-category-([0-9]*).htm' => ['GET', '/var-category-1.htm'],
];

/** Gen 2 rules Laravel does not serve (their Gen 2 targets: comment add, RSS / XML feeds, satellite var channels). */
const B4_NOT_LARAVEL = [
    'add-anasheed-comment-([0-9]*).htm' => '/add-anasheed-comment-1.htm',
    'var-news.rss' => '/var-news.rss',
    'var-group-([0-9]*).rss' => '/var-group-1.rss',
    'var-news.xml' => '/var-news.xml',
    'var-channel.htm' => '/var-channel.htm',
    'var-channel-([0-9]*).htm' => '/var-channel-1.htm',
];

/** Raw Gen 2 / OLD scripts that read `nuke_anasheed_anasheed` without a hidden filter (Gen 2) — not in Laravel's docroot. */
const B4_RAW_LEGACY = [
    '/anasheed/item.php', '/anasheed/group.php', '/anasheed/more.php', '/vars/var.php', '/vars/download.php', '/vars/more.php',
    '/vars/group.php', '/vars/getright.php', '/new_modules.php',
];

it('the list mirrors Gen 2\'s rewrite rules for Variety URLs', function () {
    $htaccess = base_path('../../7amlat/.htaccess');
    if (! is_file($htaccess)) {
        $this->markTestSkipped('Gen 2 tree not present beside the Laravel project');
    }
    $rules = file_get_contents($htaccess);

    foreach ([...array_keys(B4_LARAVEL_OWNED), ...array_keys(B4_NOT_LARAVEL)] as $pattern) {
        expect($rules)->toContain('RewriteRule ^'.$pattern.' ');
    }
});

it('every Laravel-owned Variety URL resolves to a Laravel controller route', function () {
    foreach (B4_LARAVEL_OWNED as $pattern => [$method, $path]) {
        $route = app('router')->getRoutes()->match(Request::create($path, $method));
        expect($route->getActionName())->toContain('App\\Domain\\Content\\Http\\Controllers\\')
            ->and($route->isFallback)->toBeFalse();
    }
});

it('Variety URLs Laravel does not own are not served by Laravel (documented exposure if routed to legacy)', function () {
    foreach (B4_NOT_LARAVEL as $pattern => $path) {
        $this->get($path)->assertNotFound();
    }
});

it('no raw legacy Variety script exists in Laravel\'s docroot, and Laravel does not route them', function () {
    foreach (B4_RAW_LEGACY as $path) {
        expect(file_exists(public_path(ltrim($path, '/'))))->toBeFalse();
        $this->get($path)->assertNotFound();
    }
});
