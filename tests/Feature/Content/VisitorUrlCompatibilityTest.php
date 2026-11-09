<?php

/*
 * Visitor URL compatibility A–B (owner, 2026-11-06; decision-log #104):
 * A — the two approved Gen 2 `modules.php` query-gated 301s (`7amlat/.htaccess:187-188, 193-194`), exact semantics;
 *     the third (`name=w2acd&op=DownSeries`) stays deferred and is not redirected.
 * B — Gen 2's three radio operation URLs (`.htaccess:343-345`): GET → radio.htm; operations not reimplemented.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function vucRouteName(string $uri, string $method = 'GET'): ?string
{
    try {
        return Route::getRoutes()->match(request()->create($uri, $method))->getName();
    } catch (Throwable) {
        return null;
    }
}

// ─── A: modules.php ─────────────────────────────────────────────────────────

it('A: modules.php?name=Khotab&op=Audio → 301 khotab-audio.htm, query dropped, target served by Laravel', function () {
    $this->get('/modules.php?name=Khotab&op=Audio')->assertStatus(301)->assertRedirect('/khotab-audio.htm');

    expect(vucRouteName('/khotab-audio.htm'))->toBe('khotab.authors.index');
});

it('A: modules.php?name=Khotab&op=Day&video=0&d=&m=&y= → 301 khotab-audiodate-{d}-{m}-{y}.htm, served by Laravel', function () {
    $this->get('/modules.php?name=Khotab&op=Day&video=0&d=5&m=11&y=2020')
        ->assertStatus(301)->assertRedirect('/khotab-audiodate-5-11-2020.htm');

    expect(vucRouteName('/khotab-audiodate-5-11-2020.htm'))->toBe('khotab.day.audio-date');
});

it('A: empty d / m / y are passed through exactly as Gen 2 did (`([0-9]*)`) — the resulting URL is not a page', function () {
    $this->get('/modules.php?name=Khotab&op=Day&video=0&d=&m=&y=')
        ->assertStatus(301)->assertRedirect('/khotab-audiodate---.htm');

    expect(vucRouteName('/khotab-audiodate---.htm'))->toBeNull();
});

it('A: any other modules.php request is a 404 — no redirect, no other modules.php behaviour', function (string $query) {
    $this->get('/modules.php'.$query)->assertNotFound();
})->with([
    'no query' => [''],
    'name only' => ['?name=Khotab'],
    'other op' => ['?name=Khotab&op=Video'],
    'other module' => ['?name=Anasheed&op=Audio'],
    'deferred cds-series redirect (#91 / #92)' => ['?name=w2acd&op=DownSeries&id=5'],
    'parameters reordered' => ['?op=Audio&name=Khotab'],
    'case differs (RewriteCond is case-sensitive)' => ['?name=khotab&op=audio'],
    'extra parameter' => ['?name=Khotab&op=Audio&x=1'],
    'video flag not 0' => ['?name=Khotab&op=Day&video=1&d=5&m=11&y=2020'],
    'non-numeric date part' => ['?name=Khotab&op=Day&video=0&d=ab&m=11&y=2020'],
    'missing y' => ['?name=Khotab&op=Day&video=0&d=5&m=11'],
    'date parameters reordered' => ['?name=Khotab&op=Day&video=0&m=11&d=5&y=2020'],
]);

it('A: non-GET methods are not redirected', function () {
    $this->post('/modules.php?name=Khotab&op=Audio')->assertStatus(405);
});

// ─── B: radio operation URLs ────────────────────────────────────────────────

it('B: a GET to each Gen 2 radio operation URL → 301 radio.htm, nothing read or written', function (string $url) {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get($url)->assertStatus(301)->assertRedirect('/radio.htm');

    expect($queries)->toBe(0);
})->with([
    '/remove-playlist-item-12-khotab.htm',
    '/remove-playlist-item--.htm',
    '/remove-playlist-item-5-.htm',
    '/playlist-item-12.htm',
    '/playlist-item-.htm',
    '/save-last-listen.htm',
]);

it('B: POST (the Gen 2 AJAX method) is not redirected or handled', function (string $url) {
    $this->post($url, ['item_id' => 12, 'item_section' => 'khotab'])->assertStatus(405);
})->with([
    '/remove-playlist-item-12-khotab.htm',
    '/playlist-item-12.htm',
    '/save-last-listen.htm',
]);

it('B: shapes outside Gen 2\'s patterns are not redirected', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/playlist-item-abc.htm',
    '/remove-playlist-item-12-Khotab.htm',
    '/remove-playlist-item-12.htm',
]);

it('no route collisions: existing radio, Khotab and Mobile routes keep their handlers', function (string $uri, string $name) {
    expect(vucRouteName($uri))->toBe($name);
})->with([
    ['/radio.htm', 'radio.index'],
    ['/radio-mobile.htm', 'radio.index.mobile'],
    ['/khotab-audio.htm', 'khotab.authors.index'],
    ['/khotab-audiodate-5-11-2020.htm', 'khotab.day.audio-date'],
    ['/playlist-item-12.htm', 'radio.legacy.playlist-item'],
    ['/modules.php', 'legacy.modules-php'],
]);
