<?php

use App\Domain\Content\Support\MediaPathResolver;

/**
 * Mandatory before Wave 4 per Blueprint v1.0 §19/Roadmap task 1.5 — the
 * highest silent-failure-risk component in the whole roadmap. Every case
 * is cross-checked directly against PHP's own floor($id/1000), not just
 * against MediaPathResolver's own logic, so a regression in the resolver
 * can't accidentally agree with itself.
 */
it('matches floor($id/1000) across the realistic id boundary range', function (int $id) {
    expect(MediaPathResolver::bucket($id))->toBe((int) floor($id / 1000));
})->with([
    'zero' => 0,
    'one' => 1,
    'just under first bucket boundary' => 999,
    'exactly on first bucket boundary' => 1000,
    'just over first bucket boundary' => 1001,
    'just under second bucket boundary' => 1999,
    'exactly on second bucket boundary' => 2000,
    'a realistic khotab id (documented example, id 97350)' => 97350,
    'a large id' => 999999,
    'a very large id' => 12345678,
]);

it('matches floor($id/1000) across a dense sweep of ids, not just hand-picked boundaries', function () {
    for ($id = 0; $id <= 5000; $id++) {
        expect(MediaPathResolver::bucket($id))->toBe((int) floor($id / 1000));
    }
});

it('reproduces the documented khotab-frame example exactly: id 97350 -> bucket 97', function () {
    expect(MediaPathResolver::bucket(97350))->toBe(97)
        ->and(MediaPathResolver::path('khotab_frames', 97350, 'jpg'))->toBe('media/khotab_frames/97/97350.jpg');
});

it('builds correct paths for each confirmed base-folder family', function () {
    expect(MediaPathResolver::path('khotab_frames', 1500, 'jpg'))->toBe('media/khotab_frames/1/1500.jpg')
        ->and(MediaPathResolver::path('khotab_gifs', 2500, 'gif'))->toBe('media/khotab_gifs/2/2500.gif')
        ->and(MediaPathResolver::path('authors', 350, 'jpg'))->toBe('media/authors/0/350.jpg');
});

it('normalizes a leading dot on the extension and slashes on the base folder', function () {
    expect(MediaPathResolver::path('/khotab_frames/', 1000, '.jpg'))->toBe('media/khotab_frames/1/1000.jpg');
});
