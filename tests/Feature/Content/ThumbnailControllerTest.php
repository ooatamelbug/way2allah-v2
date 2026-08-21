<?php

/**
 * `/thumbnails.php` compatibility endpoint (Laravel-owned replacement for
 * legacy's TimThumb 2.8.14, `legacy-project/thumbnails.php`). Image-
 * processing assertions require ext-gd; when it isn't loaded in this
 * runtime they're skipped with an explicit RUNTIME_BLOCKED_BY_MISSING_GD
 * reason (Pest reports the skip and its reason in the run output) rather
 * than silently vanishing — see the Implementation Report for the exact
 * runtime this was verified against.
 *
 * Real fixture assets, not synthetic ones: `public/images/tvnoise.gif`
 * (60x45, the exact production-verified case) and two files from
 * `public/images/cds_image2/` (`1338973161.jpg`, 400x300;
 * `31369589356.png`, 800x600) — both are real symlinked legacy assets
 * already used by the actual w2acd caller this endpoint serves.
 */

$hasGd = extension_loaded('gd');
$gdSkipReason = 'RUNTIME_BLOCKED_BY_MISSING_GD: ext-gd is not loaded in this test runtime.';

it('rejects a request with no src at all', function () {
    $this->get('/thumbnails.php')->assertStatus(400);
});

it('rejects an empty src', function () {
    $this->get('/thumbnails.php?src=')->assertStatus(400);
});

it('rejects a remote (http) URL src, and must not attempt a network request', function () {
    $this->get('/thumbnails.php?w=72&h=50&src='.urlencode('http://example.com/a.jpg'))->assertStatus(400);
});

it('rejects a remote (https) URL src', function () {
    $this->get('/thumbnails.php?w=72&h=50&src='.urlencode('https://example.com/a.jpg'))->assertStatus(400);
});

it('rejects a protocol-relative URL src', function () {
    $this->get('/thumbnails.php?w=72&h=50&src='.urlencode('//example.com/a.jpg'))->assertStatus(400);
});

it('rejects a path-traversal attempt', function () {
    $this->get('/thumbnails.php?w=72&h=50&src='.urlencode('images/../../../../etc/passwd'))->assertStatus(400);
});

it('rejects a src outside the approved images/media roots', function () {
    $this->get('/thumbnails.php?w=72&h=50&src=routes/content.php')->assertStatus(400);
});

it('rejects a nonexistent local file', function () {
    $this->get('/thumbnails.php?w=72&h=50&src=images/does-not-exist-at-all.jpg')->assertStatus(400);
});

it('rejects an unsupported file type (safe failure, not a crash)', function () {
    // Self-contained fixture (created/removed inside the test, not a permanent
    // addition to the real images/ asset directory) rather than relying on any
    // particular non-image file already existing there.
    $fixture = public_path('images/__thumbnail_test_unsupported.txt');
    file_put_contents($fixture, 'not an image');

    try {
        $this->get('/thumbnails.php?w=72&h=50&src=images/__thumbnail_test_unsupported.txt')
            ->assertStatus(400);
    } finally {
        @unlink($fixture);
    }
});

it('rejects when both w and h are omitted (no confirmed caller ever omits both)', function () {
    $this->get('/thumbnails.php?src=images/tvnoise.gif')->assertStatus(400);
});

it('rejects a malformed (non-numeric) w parameter', function () {
    $this->get('/thumbnails.php?w=abc&h=50&src=images/tvnoise.gif')->assertStatus(400);
});

it('rejects a negative w parameter', function () {
    $this->get('/thumbnails.php?w=-72&h=50&src=images/tvnoise.gif')->assertStatus(400);
});

it('error responses never leak the resolved server filesystem path (base_path)', function () {
    // The request's own `src` value naturally appears in the debug page's
    // echoed request URI — that's the caller's own input, not a leak. What
    // must never appear is the server's real absolute filesystem prefix.
    $response = $this->get('/thumbnails.php?w=72&h=50&src='.urlencode('images/../../../../etc/passwd'));

    $response->assertStatus(400);
    expect($response->getContent())->not->toContain(base_path());
});

test('the exact tvnoise.gif case matches the confirmed production contract: 72x50, image/gif', function () use ($hasGd) {
    $response = $this->get('/thumbnails.php?h=50&w=72&src=images/tvnoise.gif');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/gif');

    $size = getimagesizefromstring($response->getContent());
    expect($size)->not->toBeFalse();
    expect([$size[0], $size[1]])->toBe([72, 50]);
})->skip(! $hasGd, $gdSkipReason);

test('a JPEG source is resized to the requested box and stays image/jpeg (gallery grid-thumb shape)', function () use ($hasGd) {
    $response = $this->get('/thumbnails.php?h=150&w=166&src=images/cds_image2/1338973161.jpg');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([166, 150]);
})->skip(! $hasGd, $gdSkipReason);

test('a PNG source is resized to the requested box and stays image/png (w2acd list-thumb shape)', function () use ($hasGd) {
    $response = $this->get('/thumbnails.php?h=105&w=104&src=images/cds_image2/31369589356.png');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');

    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([104, 105]);
})->skip(! $hasGd, $gdSkipReason);

test('zc=0 (the real w2acd/show.blade.php caller shape) stretches the source into the exact box, no cropping', function () use ($hasGd) {
    // Confirmed real caller: w2acd/show.blade.php:30 — h=400&w=400&zc=0&q=100&src=/images/cds_image2/{file}
    // (leading slash included, exactly as the real caller emits it).
    $response = $this->get('/thumbnails.php?h=400&w=400&zc=0&q=100&src='.urlencode('/images/cds_image2/1338973161.jpg'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([400, 400]);
})->skip(! $hasGd, $gdSkipReason);

test('a single provided dimension (gallery lightbox shape, w only) computes height proportionally from the source', function () use ($hasGd) {
    // gallery/show.blade.php:29 — w=500&src=... with no h. Source is 400x300 (4:3) so h should come out to 375.
    $response = $this->get('/thumbnails.php?w=500&src=images/cds_image2/1338973161.jpg');

    $response->assertOk();
    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([500, 375]);
})->skip(! $hasGd, $gdSkipReason);

test('oversized dimensions are clamped to TimThumb\'s 1500 maximum, not rejected', function () use ($hasGd) {
    $response = $this->get('/thumbnails.php?w=5000&h=5000&src=images/cds_image2/31369589356.png');

    $response->assertOk();
    $size = getimagesizefromstring($response->getContent());
    expect([$size[0], $size[1]])->toBe([1500, 1500]);
})->skip(! $hasGd, $gdSkipReason);

test('returns 503 (not a generic 400) when ext-gd is unavailable, since that is a runtime condition, not a bad request', function () {
    $response = $this->get('/thumbnails.php?h=50&w=72&src=images/tvnoise.gif');
    $response->assertStatus(503);
})->skip($hasGd, 'ext-gd is loaded in this runtime, so the extension-unavailable path cannot be exercised here.');
