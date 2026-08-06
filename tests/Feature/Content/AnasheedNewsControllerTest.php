<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForAnasheedNews(): void
{
    InMemoryConnection::setup('main', [
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForAnasheedNews();
});

it('IF-029 fix: exclusive-news.htm (group 158) renders instead of fatal-erroring', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 158, 'title' => 'Exclusive']);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        'id' => 1, 'title' => 'Exclusive Item', 'group_id' => 158, 'fixed' => 0,
    ]);

    $this->get('/exclusive-news.htm')->assertOk()->assertSee('Exclusive Item');
});

it('IF-029 fix: cartoon-news.htm (group 57) renders', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 57, 'title' => 'Cartoon']);

    $this->get('/cartoon-news.htm')->assertOk()->assertSee('Cartoon');
});

it('IF-029 fix: documentary-news.htm (group 12) renders', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 12, 'title' => 'Documentary']);

    $this->get('/documentary-news.htm')->assertOk()->assertSee('Documentary');
});

it('IF-029 fix: anasheed-news.htm (group 98) includes group 16\'s items too — the confirmed special case', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 98, 'title' => 'Anasheed News']);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'In 98', 'group_id' => 98, 'fixed' => 0],
        ['id' => 2, 'title' => 'In 16', 'group_id' => 16, 'fixed' => 0],
        ['id' => 3, 'title' => 'In Other', 'group_id' => 5, 'fixed' => 0],
    ]);

    $response = $this->get('/anasheed-news.htm');

    $response->assertOk()->assertSee('In 98')->assertSee('In 16')->assertDontSee('In Other');
});

it('pinned items (fixed=1) are separated from the newest-items list', function () {
    DB::connection('main')->table('nuke_anasheed_groups')->insert(['id' => 158, 'title' => 'Exclusive']);
    DB::connection('main')->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Pinned Item', 'group_id' => 158, 'fixed' => 1],
        ['id' => 2, 'title' => 'Regular Item', 'group_id' => 158, 'fixed' => 0],
    ]);

    $content = $this->get('/exclusive-news.htm')->assertOk()->getContent();

    preg_match('/المواد المثبتة">(.*?)<\/section>/s', $content, $matches);
    $pinnedSection = $matches[1] ?? '';

    expect($pinnedSection)->toContain('Pinned Item');
    expect($pinnedSection)->not->toContain('Regular Item');
});

it('404s when the themed group id has no matching row', function () {
    // group 12 (documentary) intentionally not inserted.
    $this->get('/documentary-news.htm')->assertNotFound();
});
