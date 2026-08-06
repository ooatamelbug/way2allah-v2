<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForKhotabSearch(): void
{
    InMemoryConnection::setup('main', [
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForKhotabSearch();
});

it('index: with no criteria, renders the form but no results', function () {
    $response = $this->get('/khotab/search');

    $response->assertOk()->assertSee('البحث المتقدم في المرئيات');
});

it('index: IF-023 fix — the page title reflects the search page itself, not a blank/undefined author', function () {
    $content = $this->get('/khotab/search')->assertOk()->getContent();

    expect($content)->toContain('<title>البحث المتقدم في المرئيات');
});

it('index: title search returns matching series and items', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 1, 'title' => 'Ramadan Lessons', 'vedio' => 1, 'hidden' => 0, 'count' => 5,
    ]);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Ramadan Khotbah', 'vedio' => 1, 'hidden' => 0,
    ]);

    $response = $this->get('/khotab/search?title=Ramadan');

    $response->assertOk()->assertSee('Ramadan Lessons')->assertSee('Ramadan Khotbah');
});

it('index: IF-024 fix — a channel-only search (no title) returns results instead of being rejected', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_sat_channels')->insert(['id' => 9, 'title' => 'Channel Nine']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'channel_id' => 9, 'title' => 'Channel Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $response = $this->get('/khotab/search?channel=9');

    $response->assertOk()->assertSee('Channel Item')->assertDontSee('أربعة أحرف');
});

it('index: a title under 4 characters is rejected with a validation message', function () {
    $response = $this->get('/khotab/search?title=ab');

    $response->assertOk()->assertSee('أربعة أحرف');
});

it('index: IF-018 fix — an item search result\'s author link uses the correct author id', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 7, 'title' => 'Findable Item', 'vedio' => 1, 'hidden' => 0,
    ]);

    $content = $this->get('/khotab/search?title=Findable')->assertOk()->getContent();

    expect($content)->toContain('khotab-video-7.htm');
});

it('index: hidden items and series are excluded from results', function () {
    DB::connection('main')->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Author', 'prename' => 'Sheikh']);
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        'id' => 1, 'author' => 1, 'title' => 'Secret Item', 'vedio' => 1, 'hidden' => 1,
    ]);

    $this->get('/khotab/search?title=Secret')->assertOk()->assertDontSee('Secret Item');
});
