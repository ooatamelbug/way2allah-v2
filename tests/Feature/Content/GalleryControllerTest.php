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

    $response->assertOk()->assertSee('Ramadan Album')->assertSee('media/albums/first.jpg', false);
});

it('show: renders an album\'s images ordered and increments the album\'s hits', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'Eid Album', 'hits' => 4]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => 'media/albums/a.jpg', 'order' => 1],
    ]);

    $this->get('/gallery-1.htm')->assertOk()->assertSee('media/albums/a.jpg', false);

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
