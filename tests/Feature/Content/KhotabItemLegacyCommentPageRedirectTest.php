<?php

/*
 * Visitor-parity URL compatibility (owner, 2026-11-06; decision-log #102) — Gen 2's comment-page URL
 * `khotab-item-{id}-page-{n}.htm` permanently redirects to the canonical `khotab-item-{id}.htm?page={n}`.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror(),
        'nuke_islamic_advanced_m' => MainSchema::nukeIslamicAdvancedM(),
        'nuke_islamic_comments' => MainSchema::nukeIslamicComments(),
        'ips' => MainSchema::ips(),
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
});

/** One visible item with 25 visible comments (ids 1–25; newest first, 10 a page → page 2 = ids 15…6). */
function klcpSeed(): void
{
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author Name', 'prename' => 'Sheikh']);
    $db->table('nuke_islamic_khotab')->insert(['id' => 77, 'author' => 1, 'title' => 'Item Seventy-Seven', 'vedio' => 1, 'hidden' => 0, 'comments' => 25]);
    $rows = [];
    for ($i = 1; $i <= 25; $i++) {
        $rows[] = ['id' => $i, 'khid' => 77, 'uname' => "u{$i}", 'comment' => sprintf('comment-%02d-text', $i), 'view' => 1, 'mytime' => 1000 + $i];
    }
    $db->table('nuke_islamic_comments')->insert($rows);
}

it('redirects the Gen 2 comment-page URL permanently to the canonical ?page= URL, keeping the item id and page number', function (string $legacy, string $target) {
    $this->get($legacy)->assertStatus(301)->assertRedirect($target);
})->with([
    'page 2' => ['/khotab-item-77-page-2.htm', '/khotab-item-77.htm?page=2'],
    'page 1' => ['/khotab-item-77-page-1.htm', '/khotab-item-77.htm?page=1'],
    'large ids' => ['/khotab-item-298784-page-13.htm', '/khotab-item-298784.htm?page=13'],
    'page 0 is the first page on Gen 2' => ['/khotab-item-77-page-0.htm', '/khotab-item-77.htm'],
]);

it('the redirect lands on the same comment page Gen 2 showed (10 a page, newest first)', function () {
    klcpSeed();

    $html = $this->followingRedirects()->get('/khotab-item-77-page-2.htm')->assertOk()->getContent();

    expect($html)->toContain('comment-15-text')->toContain('comment-06-text')
        ->not->toContain('comment-16-text')->not->toContain('comment-05-text');
});

it('the canonical item URL is unchanged — no redirect, page 1 of the comments', function () {
    klcpSeed();

    $html = $this->get('/khotab-item-77.htm')->assertOk()->getContent();

    expect($html)->toContain('Item Seventy-Seven')->toContain('comment-25-text')->not->toContain('comment-15-text');
});

it('invalid parameters are not redirected: non-numeric id or page, a missing page, extra segments → 404', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/khotab-item-abc-page-2.htm',
    '/khotab-item-77-page-x.htm',
    '/khotab-item-77-page-.htm',
    '/khotab-item-77-page-2-3.htm',
    '/khotab-item--page-2.htm',
]);

it('a hidden item stays a visitor 404 after the redirect (visibility is decided by the canonical route)', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => '']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 9, 'author' => 1, 'title' => 'Hidden', 'vedio' => 1, 'hidden' => 1]);

    $this->get('/khotab-item-9-page-2.htm')->assertRedirect('/khotab-item-9.htm?page=2');
    $this->followingRedirects()->get('/khotab-item-9-page-2.htm')->assertNotFound();
});

it('no route collisions: the neighbouring Khotab item routes still resolve to their own handlers', function (string $url, string $name) {
    expect(Route::getRoutes()->match(request()->create($url))->getName())->toBe($name);
})->with([
    ['/khotab-item-77.htm', 'khotab.item.show'],
    ['/khotab-item-77-page-2.htm', 'khotab.item.legacy-comment-page'],
    ['/khotab-item-pdf-77.htm', 'khotab.item.download-pdf'],
    ['/khotab-download-77.htm', 'khotab.item.download'],
    ['/khotab-mirror-77-3.htm', 'khotab.item.download-mirror'],
    ['/khotab-code-77-1.htm', 'khotab.item.banner-code'],
]);
