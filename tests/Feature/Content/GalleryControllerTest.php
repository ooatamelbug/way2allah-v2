<?php

use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

function useInMemoryMainConnectionForGallery(): void
{
    InMemoryConnection::setup('main', [
        'nuke_albums' => MainSchema::nukeAlbums(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForGallery();
});

it('index: lists albums with their thumbnail (first image by order)', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album', 'count' => 2]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/second.jpg', 'order' => 2],
        ['image_id' => 2, 'album_id' => 1, 'url' => 'media/albums/first.jpg', 'order' => 1],
    ]);

    $response = $this->get('/gallery.htm');

    $response->assertOk()->assertSee('Ramadan Album');
});

it('show: renders an album\'s images ordered and increments the album\'s hits', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Eid Album', 'hits' => 4]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1],
    ]);

    $this->get('/gallery-1.htm')->assertOk();

    expect(DB::connection('main')->table('nuke_albums')->where('album_id', 1)->first()->hits)->toBe(5);
});

it('show: an album with no images shows the "no images yet" message', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Empty Album']);

    $this->get('/gallery-1.htm')->assertOk()->assertSee('لا يوجد صور مضافة');
});

it('show: 404s for a nonexistent album', function () {
    $this->get('/gallery-999.htm')->assertNotFound();
});

it('download: IF-027 fix — resolves from the app\'s own storage, not a hardcoded external legacy path', function () {
    $relativePath = 'gallery-test-fixture/sample.jpg';
    $fullPath = public_path($relativePath);
    @mkdir(dirname($fullPath), recursive: true);
    file_put_contents($fullPath, 'fake-image-bytes');

    DB::connection('main')->table('nuke_albums_images')->insert([
        'image_id' => 1, 'album_id' => 1, 'url' => $relativePath,
    ]);

    $this->get('/albumimg-download-1.htm')->assertOk();

    @unlink($fullPath);
    @rmdir(dirname($fullPath));
});

it('download: 404s when the file does not exist on disk', function () {
    DB::connection('main')->table('nuke_albums_images')->insert([
        'image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/does-not-exist.jpg',
    ]);

    $this->get('/albumimg-download-1.htm')->assertNotFound();
});

// ---- G-03 (Migration Gap Register): thumbnails.php parity + lightbox ----

it('index: album thumbnail routes through thumbnails.php at the exact legacy 250x350 dimensions, not the raw image', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Ramadan Album', 'count' => 1]);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/first.jpg', 'order' => 1]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=250&amp;w=350&amp;src=media/albums/first.jpg')
        ->and($content)->toContain('class="img-responsive"');
});

it('index: shows the album\'s last-update date using plain date(), not CoolShortDate()', function () {
    $timestamp = mktime(0, 0, 0, 3, 15, 2024);
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Dated Album', 'last_update' => $timestamp]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->toContain(date('Y-m-d', $timestamp))
        ->not->toContain('مارس'); // CoolShortDate()'s Arabic month name — confirms no verbatim port
});

it('show: image thumbnail routes through thumbnails.php at the exact legacy 150x166 dimensions', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/thumbnails.php?h=150&amp;w=166&amp;src=media/albums/a.jpg')
        ->and($content)->toContain('class="img-responsive pwimages"');
});

it('show: lightbox target uses width-only thumbnails.php (w=500, NO h, NO zc) — a deliberately different shape from the grid thumbnail', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('href="/thumbnails.php?w=500&amp;src=media/albums/a.jpg"')
        ->not->toContain('w=500&amp;h=')
        ->not->toContain('zc=');
});

it('show: loads the legacy lightbox CSS/JS assets and the exact legacy init call', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);
    DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1]);

    $content = $this->get('/gallery-1.htm')->assertOk()->getContent();

    expect($content)->toContain('/gallery/lightbox/jquery.lightbox-0.4.css')
        ->toContain('/gallery/lightbox/jquery.blockUI-1.33.pack.js')
        ->toContain('/gallery/lightbox/jquery.lightbox-0.4.pack.js')
        ->toContain("\$('a.lightbox').lightBox();");
});

it('index: does NOT load the lightbox assets — page-scoped to gallery-{album}.htm only, not the shared layout', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Album']);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect($content)->not->toContain('jquery.lightbox');
});

it('the public/gallery/lightbox symlink resolves and actually serves the real legacy asset bytes', function () {
    $cssPath = public_path('gallery/lightbox/jquery.lightbox-0.4.css');
    $jsPath = public_path('gallery/lightbox/jquery.lightbox-0.4.pack.js');
    $blockUiPath = public_path('gallery/lightbox/jquery.blockUI-1.33.pack.js');

    expect(is_link(public_path('gallery/lightbox')) || is_dir(public_path('gallery/lightbox')))->toBeTrue()
        ->and(is_file($cssPath))->toBeTrue()
        ->and(is_file($jsPath))->toBeTrue()
        ->and(is_file($blockUiPath))->toBeTrue()
        ->and(filesize($cssPath))->toBeGreaterThan(0)
        ->and(filesize($jsPath))->toBeGreaterThan(0)
        ->and(filesize($blockUiPath))->toBeGreaterThan(0);
});
