<?php

use App\Support\LegacyUrlCompatibility\UrlMapRouteRegistrar;

it('registers a redirect rule and serves it correctly', function () {
    UrlMapRouteRegistrar::registerRule('/legacy-dummy-redirect-test', [
        'type' => 'redirect',
        'to' => '/new-dummy-path',
        'status' => 301,
    ]);

    $this->get('/legacy-dummy-redirect-test')
        ->assertRedirect('/new-dummy-path')
        ->assertStatus(301);
});

it('registers a pass-through rule and serves it correctly', function () {
    UrlMapRouteRegistrar::registerRule('/legacy-dummy-passthrough-test', [
        'type' => 'pass-through',
        'to' => fn () => 'dummy-content',
    ]);

    $this->get('/legacy-dummy-passthrough-test')
        ->assertOk()
        ->assertSee('dummy-content');
});

it('throws for an unrecognized rule type instead of silently registering nothing', function () {
    expect(fn () => UrlMapRouteRegistrar::registerRule('/legacy-dummy-bad-rule', ['type' => 'not-a-real-type']))
        ->toThrow(InvalidArgumentException::class);
});

it('returns a clean 404 for a legacy URL that has no mapping at all', function () {
    $this->get('/totally-unmapped-legacy-url-xyz')->assertNotFound();
});
