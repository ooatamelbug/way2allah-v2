<?php

use App\Domain\Content\Models\Album;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Enhancement Batch E-13 — the gallery index resolved each album's
 * thumbnail with its own query (`Album::thumbnailImage()` called from
 * inside the Blade loop), so 84 albums cost 1 + 84 queries.
 * `Album::thumbnailUrlsFor()` now resolves them all in one.
 *
 * The property worth protecting is *which* image wins and that the
 * count stays flat — not a wall-clock figure. The query-count
 * assertions are upper bounds so an extra framework query can never
 * make them brittle, and no test depends on production row counts.
 */
function useInMemoryMainConnectionForGalleryBatching(): void
{
    InMemoryConnection::setup('main', [
        'nuke_albums' => MainSchema::nukeAlbums(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
    ]);
}

/** @return int queries issued on the `main` connection while running the callback */
function countGalleryQueries(Closure $callback): int
{
    $count = 0;
    DB::connection('main')->listen(function () use (&$count) {
        $count++;
    });
    $callback();

    return $count;
}

/** Seeds $n albums, each with $imagesEach images whose `order` descends so the winner is never the first inserted. */
function seedAlbums(int $n, int $imagesEach = 3): void
{
    $db = DB::connection('main');
    $imageId = 1;

    for ($a = 1; $a <= $n; $a++) {
        $db->table('nuke_albums')->insert(['album_id' => $a, 'title' => "Album {$a}", 'count' => $imagesEach]);

        for ($i = $imagesEach; $i >= 1; $i--) {
            $db->table('nuke_albums_images')->insert([
                'image_id' => $imageId++,
                'album_id' => $a,
                'url' => "media/albums/a{$a}-order{$i}.jpg",
                'order' => $i,
            ]);
        }
    }
}

beforeEach(function () {
    useInMemoryMainConnectionForGalleryBatching();
});

it('picks the image with the lowest order as each album\'s thumbnail', function () {
    seedAlbums(1, imagesEach: 4);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    // order=1 wins; it was inserted last and has the highest image_id, so a
    // solution that fell back to insertion order or primary key would fail.
    expect($content)->toContain('src=media/albums/a1-order1.jpg')
        ->not->toContain('a1-order2.jpg')
        ->not->toContain('a1-order4.jpg');
});

it('matches thumbnailImage() exactly for every album', function () {
    seedAlbums(6, imagesEach: 3);
    $albums = Album::orderBy('album_id')->get(['album_id']);

    $batched = Album::thumbnailUrlsFor($albums);

    foreach ($albums as $album) {
        expect($batched[$album->album_id] ?? null)->toBe($album->thumbnailImage()?->url);
    }
});

it('renders an album with a single image', function () {
    seedAlbums(1, imagesEach: 1);

    $this->get('/gallery.htm')->assertOk()->assertSee('media/albums/a1-order1.jpg', false);
});

it('renders an album with no images at all, leaving the src empty', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 7, 'title' => 'Empty Album', 'count' => 0]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    // The legacy expression concatenated a null url, producing a bare
    // `src=` — preserved rather than substituting a placeholder image.
    expect($content)->toContain('Empty Album')
        ->toContain('src="/thumbnails.php?h=250&amp;w=350&amp;src="');
});

it('omits albums with no images from the batched map rather than storing null', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 7, 'title' => 'Empty Album', 'count' => 0]);
    $albums = Album::orderBy('album_id')->get(['album_id']);

    expect(Album::thumbnailUrlsFor($albums))->toBe([]);
});

it('preserves album ordering by album_id', function () {
    DB::connection('main')->table('nuke_albums')->insert([
        ['album_id' => 3, 'title' => 'Third', 'count' => 0],
        ['album_id' => 1, 'title' => 'First', 'count' => 0],
        ['album_id' => 2, 'title' => 'Second', 'count' => 0],
    ]);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    expect(strpos($content, 'First'))->toBeLessThan(strpos($content, 'Second'))
        ->and(strpos($content, 'Second'))->toBeLessThan(strpos($content, 'Third'));
});

it('keeps every album link intact', function () {
    seedAlbums(4);

    $content = $this->get('/gallery.htm')->assertOk()->getContent();

    foreach ([1, 2, 3, 4] as $id) {
        expect($content)->toContain("/gallery-{$id}.htm");
    }
});

it('does not add a query per album — the count stays flat as albums grow', function () {
    seedAlbums(2);
    $twoAlbums = countGalleryQueries(fn () => $this->get('/gallery.htm')->assertOk());

    useInMemoryMainConnectionForGalleryBatching(); // fresh schema
    seedAlbums(20);
    $twentyAlbums = countGalleryQueries(fn () => $this->get('/gallery.htm')->assertOk());

    // Before this batch, 18 extra albums meant 18 extra queries.
    expect($twentyAlbums)->toBe($twoAlbums)
        ->and($twentyAlbums)->toBeLessThanOrEqual(4);
});

it('resolves thumbnails for many albums in a single query', function () {
    seedAlbums(15);
    $albums = Album::orderBy('album_id')->get(['album_id']);

    $count = countGalleryQueries(fn () => Album::thumbnailUrlsFor($albums));

    expect($count)->toBe(1);
});

it('issues no query at all for an empty album list', function () {
    $count = countGalleryQueries(fn () => Album::thumbnailUrlsFor(collect()));

    expect($count)->toBe(0);
});
