<?php

/* Phase 3B.1 — `mobile-file-{lid}.htm`: HEAD gets the same redirect as GET and never increments `hits`. */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileHarness as H;

uses(RefreshDatabase::class);

beforeEach(fn () => H::setUp());

it('HEAD: same 302 and Location as GET, hits unchanged; GET counts once', function () {
    $lid = H::material(H::category(), ['url' => 'https://archive.org/download/a/b.mp3', 'hits' => 4]);
    $hits = fn () => (int) DB::connection('main')->table('nuke_downloads_downloads')->where('lid', $lid)->value('hits');

    $head = $this->call('HEAD', "/mobile-file-{$lid}.htm");
    expect($hits())->toBe(4);
    $get = $this->get("/mobile-file-{$lid}.htm");

    expect($head->getStatusCode())->toBe(302)->toBe($get->getStatusCode())
        ->and($head->headers->get('Location'))->toBe($get->headers->get('Location'))->toBe('https://archive.org/download/a/b.mp3')
        ->and($hits())->toBe(5);
});
