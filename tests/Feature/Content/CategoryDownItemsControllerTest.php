<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * G-06 (test-hardening pass) — protects `CategoryDownItemsController`
 * (`khotab-series-{id}.grx` / `khotab-series-{id}-{cat}.grx`) against
 * regression. No application behavior is changed by this file — every
 * assertion targets behavior already documented in IF-040/IF-041.
 */
function useInMemoryMainConnectionForCategoryDownItems(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForCategoryDownItems();
});

it('khotab-series-{id}.grx: 200, correct headers, one URL/File block per item', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'Item One'],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/b.mp3', 'title' => 'Item Two'],
    ]);

    $response = $this->get('/khotab-series-9.grx');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/force-download')
        ->assertHeader('Content-Disposition', 'attachment; filename="Way2Allah-My Series.grx"')
        ->assertHeader('Content-Transfer-Encoding', 'binary');

    $decoded = iconv('windows-1256', 'utf-8', $response->getContent());
    expect(substr_count($decoded, 'URL:'))->toBe(2)
        ->and($decoded)->toContain('https://example.com/a.mp3')
        ->toContain('https://example.com/b.mp3')
        ->toContain('Item One.mp3.GetRight')
        ->toContain('C:\Way2Allah\My Series\\');
});

it('khotab-series-{id}-{cat}.grx: filters items through khotab_category_index for the given category', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'My Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/in-cat.mp3', 'title' => 'In Category'],
        ['id' => 2, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/not-in-cat.mp3', 'title' => 'Not In Category'],
    ]);
    DB::connection('main')->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 11]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9-11.grx')->assertOk()->getContent());

    expect($decoded)->toContain('in-cat.mp3')->not->toContain('not-in-cat.mp3');
});

it('IF-040: output is windows-1256-encoded, not UTF-8 — round-trips correctly, and raw bytes are not valid UTF-8', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'سلسلة عربية']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3', 'title' => 'شرح صحيح مسلم',
    ]);

    $body = $this->get('/khotab-series-9.grx')->assertOk()->getContent();

    expect(mb_check_encoding($body, 'UTF-8'))->toBeFalse();

    $decoded = iconv('windows-1256', 'utf-8', $body);
    expect($decoded)->toContain('شرح صحيح مسلم');
});

it('title sanitization: backslash and forward-slash become a hyphen, colon becomes underscore, other reserved chars become a space', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 0, 'link' => 'https://example.com/a.mp3',
        'title' => 'A\\B/C:D*E?F<G>H|I"J',
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->toContain('A-B-C_D E F G H I J.mp3.GetRight');
});

it('hidden items are excluded from the playlist', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert(['id' => 9, 'title' => 'Series']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'ser_id' => 9, 'hidden' => 1, 'link' => 'https://example.com/hidden.mp3', 'title' => 'Hidden',
    ]);

    $decoded = iconv('windows-1256', 'utf-8', $this->get('/khotab-series-9.grx')->assertOk()->getContent());

    expect($decoded)->not->toContain('hidden.mp3');
});

it('404s for a nonexistent series', function () {
    $this->get('/khotab-series-99999.grx')->assertNotFound();
    $this->get('/khotab-series-99999-11.grx')->assertNotFound();
});
