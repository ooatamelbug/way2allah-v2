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
 * Test-owned fixture images, not real legacy assets: `public/images` (one
 * of the 2 approved src roots, `ThumbnailService::APPROVED_ROOTS`) is a
 * symlink to the sibling `legacy-project` repository — real production
 * media, deliberately never committed, unavailable in a CI checkout of
 * this repository alone. The resize/crop/clamp math these tests verify
 * doesn't depend on any specific legacy byte content, only on real,
 * correctly-dimensioned GIF/JPEG/PNG source files — `makeFixtureImage()`
 * below generates one, self-contained and disposable, per test, created
 * and deleted within that test's own lifecycle (matching the existing
 * `rejects an unsupported file type` test's already-established pattern
 * in this same file, extended here rather than duplicated ad hoc). The
 * exact real caller URL/query shapes (leading `/`, `zc=0&q=100`, the
 * `cds_image2/` subdirectory, w-only) are preserved unchanged — only the
 * underlying image bytes are test-owned instead of legacy-borrowed, per
 * this project's own "test-owned fixture for behavior, not asset
 * availability" convention.
 */

$hasGd = extension_loaded('gd');
$gdSkipReason = 'RUNTIME_BLOCKED_BY_MISSING_GD: ext-gd is not loaded in this test runtime.';

/**
 * Creates a real, valid GIF/JPEG/PNG at the exact given dimensions under
 * one of the approved `images`/`media` src roots, and returns its
 * public-relative path (e.g. `images/__thumbnail_fixture_abc123.jpg`).
 * Caller owns cleanup (`@unlink(public_path($relativePath))`).
 */
function makeFixtureImage(string $format, int $width, int $height, string $subdir = ''): string
{
    $relative = 'images/'.($subdir !== '' ? $subdir.'/' : '').'__thumbnail_fixture_'.bin2hex(random_bytes(6)).'.'.$format;
    $path = public_path($relative);
    @mkdir(dirname($path), 0777, true);

    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 160, 200));

    match ($format) {
        'gif' => imagegif($image, $path),
        'jpg' => imagejpeg($image, $path, 90),
        'png' => imagepng($image, $path),
    };
    imagedestroy($image);

    return $relative;
}

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
    // Must reach ThumbnailService::targetDimensions()'s own "both
    // omitted" rejection, not an earlier "file not found" 400 for an
    // unrelated reason — resolvePath()/getimagesize() both run before
    // that check and before any GD-availability check, so a real,
    // existing, valid-image source file is required regardless of
    // whether ext-gd is loaded here. The real `images/tvnoise.gif` this
    // test used before is unavailable in CI (public/images is an empty
    // directory there, not the symlinked legacy asset) — a minimal,
    // literal 1x1 GIF byte sequence is written directly instead, GD-
    // independent, so this test exercises the same rejection in every
    // runtime, not "file not found" in CI specifically.
    $fixture = public_path('images/__thumbnail_test_both_omitted.gif');
    file_put_contents($fixture, base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));

    try {
        $this->get('/thumbnails.php?src=images/__thumbnail_test_both_omitted.gif')->assertStatus(400);
    } finally {
        @unlink($fixture);
    }
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
    // Real source is 60x45 (public/images/tvnoise.gif) — the resize math
    // under test doesn't depend on that exact content, only on a real
    // GIF existing at a plausible source size; reproduced at the same
    // dimensions for fidelity.
    $fixture = makeFixtureImage('gif', 60, 45);

    try {
        $response = $this->get('/thumbnails.php?h=50&w=72&src='.$fixture);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');

        $size = getimagesizefromstring($response->getContent());
        expect($size)->not->toBeFalse();
        expect([$size[0], $size[1]])->toBe([72, 50]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('a JPEG source is resized to the requested box and stays image/jpeg (gallery grid-thumb shape)', function () use ($hasGd) {
    // Real source is 1338973161.jpg, 400x300 — same dimensions reproduced here.
    $fixture = makeFixtureImage('jpg', 400, 300, 'cds_image2');

    try {
        $response = $this->get('/thumbnails.php?h=150&w=166&src='.$fixture);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');

        $size = getimagesizefromstring($response->getContent());
        expect([$size[0], $size[1]])->toBe([166, 150]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('a PNG source is resized to the requested box and stays image/png (w2acd list-thumb shape)', function () use ($hasGd) {
    // Real source is 31369589356.png, 800x600 — same dimensions reproduced here.
    $fixture = makeFixtureImage('png', 800, 600, 'cds_image2');

    try {
        $response = $this->get('/thumbnails.php?h=105&w=104&src='.$fixture);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        $size = getimagesizefromstring($response->getContent());
        expect([$size[0], $size[1]])->toBe([104, 105]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('zc=0 (the real w2acd/show.blade.php caller shape) stretches the source into the exact box, no cropping', function () use ($hasGd) {
    // Confirmed real caller: w2acd/show.blade.php:30 — h=400&w=400&zc=0&q=100&src=/images/cds_image2/{file}
    // (leading slash included, exactly as the real caller emits it) — preserved unchanged below.
    $fixture = makeFixtureImage('jpg', 400, 300, 'cds_image2');

    try {
        $response = $this->get('/thumbnails.php?h=400&w=400&zc=0&q=100&src='.urlencode('/'.$fixture));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');

        $size = getimagesizefromstring($response->getContent());
        expect([$size[0], $size[1]])->toBe([400, 400]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('a single provided dimension (gallery lightbox shape, w only) computes height proportionally from the source', function () use ($hasGd) {
    // gallery/show.blade.php:29 — w=500&src=... with no h. Source is 400x300 (4:3) so h should come out to 375.
    $fixture = makeFixtureImage('jpg', 400, 300, 'cds_image2');

    try {
        $response = $this->get('/thumbnails.php?w=500&src='.$fixture);

        $response->assertOk();
        $size = getimagesizefromstring($response->getContent());
        expect([$size[0], $size[1]])->toBe([500, 375]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('oversized dimensions are clamped to TimThumb\'s 1500 maximum, not rejected', function () use ($hasGd) {
    // Real source is 31369589356.png, 800x600 — same dimensions reproduced here.
    $fixture = makeFixtureImage('png', 800, 600, 'cds_image2');

    try {
        $response = $this->get('/thumbnails.php?w=5000&h=5000&src='.$fixture);

        $response->assertOk();
        $size = getimagesizefromstring($response->getContent());
        expect([$size[0], $size[1]])->toBe([1500, 1500]);
    } finally {
        @unlink(public_path($fixture));
    }
})->skip(! $hasGd, $gdSkipReason);

test('returns 503 (not a generic 400) when ext-gd is unavailable, since that is a runtime condition, not a bad request', function () {
    $response = $this->get('/thumbnails.php?h=50&w=72&src=images/tvnoise.gif');
    $response->assertStatus(503);
})->skip($hasGd, 'ext-gd is loaded in this runtime, so the extension-unavailable path cannot be exercised here.');
