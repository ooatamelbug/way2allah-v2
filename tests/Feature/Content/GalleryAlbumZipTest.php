<?php

/*
 * Gallery full-album ZIP downloads (owner, 2026-11-06; decision-log #107) — `GET /download-album-{id}.htm`,
 * built on demand with ZipArchive, cached privately per album by fingerprint, locked and atomic.
 * Isolated: images live in a temporary media root, archives on a faked `local` disk, data in-memory.
 */

use App\Domain\Content\Models\Album;
use App\Domain\Content\Services\AlbumZipService;
use App\Domain\Content\Services\AlbumZipUnavailableException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

beforeEach(function () {
    InMemoryConnection::setup('main', [
        'nuke_albums' => MainSchema::nukeAlbums(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
    ]);
    Storage::fake('local');

    $this->sandbox = sys_get_temp_dir().'/gallery-zip-'.bin2hex(random_bytes(5));
    $this->media = $this->sandbox.'/public';
    File::ensureDirectoryExists($this->media.'/media/albums/2013');
    file_put_contents($this->sandbox.'/secret.txt', 'outside the media root');
    file_put_contents($this->sandbox.'/secret.jpg', 'outside the media root');

    $this->useService = function (array $options = []): AlbumZipService {
        $service = new AlbumZipService($this->media, null, ...$options);
        app()->instance(AlbumZipService::class, $service);

        return $service;
    };
    ($this->useService)();
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

function gzImage(string $media, string $relative, string $content): string
{
    File::ensureDirectoryExists(dirname($media.'/'.$relative));
    file_put_contents($media.'/'.$relative, $content);

    return $relative;
}

/** Album 1 (eligible) with three images; `order` deliberately not the insertion order. */
function gzSeedAlbum(string $media, int $compressed = 1): void
{
    $db = DB::connection('main');
    $db->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'ألبوم', 'count' => 3, 'is_compressed' => $compressed]);
    $db->table('nuke_albums_images')->insert([
        ['image_id' => 10, 'album_id' => 1, 'url' => gzImage($media, 'media/albums/2013/c.jpg', 'CCC'), 'order' => 3],
        ['image_id' => 11, 'album_id' => 1, 'url' => gzImage($media, 'media/albums/2013/a.jpg', 'AAA'), 'order' => 1],
        ['image_id' => 12, 'album_id' => 1, 'url' => gzImage($media, 'media/albums/2013/b.png', 'BBB'), 'order' => 2],
    ]);
}

/** @return array<string, string> entry name => content */
function gzEntries(string $zipPath): array
{
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $out[$name] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $out;
}

function gzArchives(): array
{
    return glob(Storage::disk('local')->path(AlbumZipService::DIRECTORY).'/*') ?: [];
}

// ─── endpoint ───────────────────────────────────────────────────────────────

it('an eligible album downloads as a ZIP with its images in display order, safe flat names, exact contents', function () {
    gzSeedAlbum($this->media);

    $response = $this->get('/download-album-1.htm')->assertOk()
        ->assertHeader('Content-Type', 'application/zip');

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('album-1.zip')
        ->and(gzEntries($response->baseResponse->getFile()->getPathname()))
        ->toBe(['001-a.jpg' => 'AAA', '002-b.png' => 'BBB', '003-c.jpg' => 'CCC']);
});

it('ineligible (is_compressed ≠ 1), missing or non-numeric albums are a 404 and build nothing', function (string $url, int $compressed) {
    gzSeedAlbum($this->media, $compressed);

    $this->get($url)->assertNotFound();
    expect(gzArchives())->toBe([]);
})->with([
    'not compressed' => ['/download-album-1.htm', 0],
    'missing album' => ['/download-album-999.htm', 1],
    'non-numeric id' => ['/download-album-abc.htm', 1],
]);

it('missing images are skipped (and logged); if none remains the answer is a 404 and no ZIP exists', function () {
    gzSeedAlbum($this->media);
    unlink($this->media.'/media/albums/2013/b.png');
    Log::spy();

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());
    expect($entries)->toBe(['001-a.jpg' => 'AAA', '002-c.jpg' => 'CCC']);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $context['image_id'] === 12)->once();

    unlink($this->media.'/media/albums/2013/a.jpg');
    unlink($this->media.'/media/albums/2013/c.jpg');
    $this->get('/download-album-1.htm')->assertNotFound();
    expect(gzArchives())->toBe([]);
});

it('unsafe paths are never archived: traversal, absolute paths, non-image files', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'x', 'is_compressed' => 1]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => '../secret.jpg', 'order' => 1],
        ['image_id' => 2, 'album_id' => 1, 'url' => 'media/../../secret.txt', 'order' => 2],
        ['image_id' => 3, 'album_id' => 1, 'url' => $this->sandbox.'/secret.jpg', 'order' => 3],
        ['image_id' => 4, 'album_id' => 1, 'url' => gzImage($this->media, 'media/albums/2013/run.php', '<?php'), 'order' => 4],
        ['image_id' => 5, 'album_id' => 1, 'url' => gzImage($this->media, 'media/albums/2013/ok.jpg', 'OK'), 'order' => 5],
    ]);

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());

    expect($entries)->toBe(['001-ok.jpg' => 'OK']);
});

it('entry names are flattened and sanitised, and same-named files in different folders do not collide', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'x', 'is_compressed' => 1]);
    DB::connection('main')->table('nuke_albums_images')->insert([
        ['image_id' => 1, 'album_id' => 1, 'url' => gzImage($this->media, 'media/albums/one/same.jpg', '1'), 'order' => 1],
        ['image_id' => 2, 'album_id' => 1, 'url' => gzImage($this->media, 'media/albums/two/same.jpg', '2'), 'order' => 2],
        ['image_id' => 3, 'album_id' => 1, 'url' => gzImage($this->media, 'media/albums/two/صورة "x";.jpg', '3'), 'order' => 3],
    ]);

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());

    expect(array_keys($entries))->toBe(['001-same.jpg', '002-same.jpg', '003-صورة-x.jpg'])
        ->and(array_values($entries))->toBe(['1', '2', '3']);
});

it('the image-count and total-size limits refuse an album (404) without building', function (array $options) {
    gzSeedAlbum($this->media);
    ($this->useService)($options);

    $this->get('/download-album-1.htm')->assertNotFound();
    expect(gzArchives())->toBe([]);
})->with([
    'more images than allowed' => [['maxImages' => 2]],
    'more bytes than allowed' => [['maxTotalBytes' => 8]],
]);

// ─── cache ──────────────────────────────────────────────────────────────────

it('cache hit: a second download reuses the same archive without rebuilding it', function () {
    gzSeedAlbum($this->media);

    $first = $this->get('/download-album-1.htm')->baseResponse->getFile()->getPathname();
    touch($first, time() - 600);
    clearstatcache();
    $second = $this->get('/download-album-1.htm')->baseResponse->getFile()->getPathname();
    clearstatcache();

    expect($second)->toBe($first)->and(filemtime($second))->toBe(time() - 600)->and(gzArchives())->toHaveCount(1);
});

it('invalidation: reordering, deleting or replacing an image produces a new archive and removes the old one', function (Closure $change, array $expected) {
    gzSeedAlbum($this->media);
    $before = $this->get('/download-album-1.htm')->baseResponse->getFile()->getPathname();

    $change($this->media);
    clearstatcache();
    $after = $this->get('/download-album-1.htm')->baseResponse->getFile()->getPathname();

    expect($after)->not->toBe($before)
        ->and(file_exists($before))->toBeFalse()
        ->and(gzArchives())->toBe([$after])
        ->and(gzEntries($after))->toBe($expected);
})->with([
    'reordered' => [fn () => DB::connection('main')->table('nuke_albums_images')->where('image_id', 10)->update(['order' => 0]),
        ['001-c.jpg' => 'CCC', '002-a.jpg' => 'AAA', '003-b.png' => 'BBB']],
    'deleted' => [fn () => DB::connection('main')->table('nuke_albums_images')->where('image_id', 12)->delete(),
        ['001-a.jpg' => 'AAA', '002-c.jpg' => 'CCC']],
    'file replaced' => [function (string $media) {
        file_put_contents($media.'/media/albums/2013/a.jpg', 'NEW-A');
    }, ['001-a.jpg' => 'NEW-A', '002-b.png' => 'BBB', '003-c.jpg' => 'CCC']],
]);

// ─── concurrency and failure ────────────────────────────────────────────────

it('concurrent build protection: while another build holds the album lock, no second build runs (503, Retry-After)', function () {
    gzSeedAlbum($this->media);
    ($this->useService)(['lockWaitSeconds' => 0]);
    $held = Cache::lock('gallery-album-zip:1', 60);
    expect($held->get())->toBeTrue();

    $this->get('/download-album-1.htm')->assertStatus(503)->assertHeader('Retry-After', '30');
    expect(gzArchives())->toBe([]);

    $held->release();
    $this->get('/download-album-1.htm')->assertOk();
    expect(gzArchives())->toHaveCount(1);
});

it('the service itself throws a lock timeout rather than building twice', function () {
    gzSeedAlbum($this->media);
    $service = ($this->useService)(['lockWaitSeconds' => 0]);
    Cache::lock('gallery-album-zip:1', 60)->get();

    expect(fn () => $service->archiveFor(Album::find(1)))->toThrow(LockTimeoutException::class);
});

it('a failed build leaves no archive and no temporary file behind, and the lock is released', function () {
    gzSeedAlbum($this->media);
    $failing = new class($this->media) extends AlbumZipService
    {
        protected function writeArchive(string $temporary, array $entries): void
        {
            file_put_contents($temporary, 'partial');

            throw new RuntimeException('disk full');
        }
    };

    expect(fn () => $failing->archiveFor(Album::find(1)))->toThrow(RuntimeException::class, 'disk full')
        ->and(gzArchives())->toBe([])
        ->and(Cache::lock('gallery-album-zip:1', 1)->get())->toBeTrue();
});

it('an album with no valid image throws the unavailable exception at service level', function () {
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'x', 'is_compressed' => 1]);

    expect(fn () => app(AlbumZipService::class)->archiveFor(Album::find(1)))
        ->toThrow(AlbumZipUnavailableException::class);
});

// ─── buttons and existing downloads ─────────────────────────────────────────

it('both Gallery download buttons navigate straight to the ZIP endpoint (no AJAX), markup unchanged', function () {
    gzSeedAlbum($this->media);

    $index = $this->get('/gallery.htm')->assertOk()->getContent();
    $show = $this->get('/gallery-1.htm')->assertOk()->getContent();

    foreach ([$index, $show] as $html) {
        preg_match('#function downlaod_gellery_images\(id\)\s*\{(.*?)\}#s', $html, $fn);
        expect($html)->toContain('onclick="downlaod_gellery_images(1)"')
            ->and($fn[1] ?? '')->toContain('window.location.href')->toContain('/download-album-')
            ->not->toContain('$.ajax')->not->toContain('$.get');
    }
    expect($index)->toContain('class="w2a-album-btn-download"')
        ->and($show)->toContain('class="w2a-gallery-download-zip-btn"');
});

it('single-image downloads are unchanged (route and handler)', function () {
    expect(app('router')->getRoutes()->match(request()->create('/albumimg-download-5.htm'))->getName())->toBe('gallery.download')
        ->and(app('router')->getRoutes()->match(request()->create('/download-album-5.htm'))->getName())->toBe('gallery.download-album');
});

// ─── symlinked media layout (decision-log #107 symlink fix) ─────────────────

/**
 * The project's real layout, rebuilt inside the sandbox (the real `legacy-project/media` is never touched):
 * public/media → ../legacy/media (approved), public/backup → ../legacy/media-backup (unapproved, and a name that
 * shares the approved root's prefix), public/ext → ../elsewhere (unapproved external).
 */
function gzSymlinkLayout(string $sandbox): string
{
    $public = $sandbox.'/linked-public';
    File::ensureDirectoryExists($public);
    File::ensureDirectoryExists($sandbox.'/legacy/media/albums/2013');
    File::ensureDirectoryExists($sandbox.'/legacy/media-backup');
    File::ensureDirectoryExists($sandbox.'/elsewhere');
    file_put_contents($sandbox.'/legacy/media/albums/2013/one.jpg', 'ONE');
    file_put_contents($sandbox.'/legacy/media/albums/2013/two.png', 'TWO');
    file_put_contents($sandbox.'/legacy/media-backup/backup.jpg', 'BACKUP');
    file_put_contents($sandbox.'/elsewhere/external.jpg', 'EXTERNAL');
    symlink($sandbox.'/legacy/media', $public.'/media');
    symlink($sandbox.'/legacy/media-backup', $public.'/backup');
    symlink($sandbox.'/elsewhere', $public.'/ext');

    return $public;
}

function gzLinkedAlbum(array $urls): void
{
    DB::connection('main')->table('nuke_albums')->insert(['album_id' => 1, 'title' => 'x', 'is_compressed' => 1]);
    foreach (array_values($urls) as $i => $url) {
        DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => $i + 1, 'album_id' => 1, 'url' => $url, 'order' => $i + 1]);
    }
}

it('approved public/media symlink: images whose real path is in the linked media directory are archived', function () {
    $public = gzSymlinkLayout($this->sandbox);
    app()->instance(AlbumZipService::class, new AlbumZipService($public));
    gzLinkedAlbum(['media/albums/2013/one.jpg', 'media/albums/2013/two.png']);

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());

    expect($entries)->toBe(['001-one.jpg' => 'ONE', '002-two.png' => 'TWO'])
        ->and(realpath($public.'/media/albums/2013/one.jpg'))->not->toStartWith(realpath($public).'/');
});

it('normal (non-symlinked) media directory still works', function () {
    gzSeedAlbum($this->media);
    expect(is_link($this->media.'/media'))->toBeFalse();

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());

    expect($entries)->toBe(['001-a.jpg' => 'AAA', '002-b.png' => 'BBB', '003-c.jpg' => 'CCC']);
});

it('unapproved symlinks, traversal and directory-prefix collisions are rejected; only approved media is archived', function () {
    $public = gzSymlinkLayout($this->sandbox);
    app()->instance(AlbumZipService::class, new AlbumZipService($public));
    gzLinkedAlbum([
        'ext/external.jpg',                       // unapproved external symlink
        'backup/backup.jpg',                      // unapproved symlink to /legacy/media-backup
        'media/../media-backup/backup.jpg',       // prefix collision: /legacy/media-backup is not under /legacy/media
        'media/../../elsewhere/external.jpg',     // traversal out of the approved root
        'media/albums/2013/../../../../secret.jpg', // traversal to the sandbox
        'media/albums/2013/one.jpg',              // the one approved image
    ]);

    $entries = gzEntries($this->get('/download-album-1.htm')->assertOk()->baseResponse->getFile()->getPathname());

    expect($entries)->toBe(['001-one.jpg' => 'ONE']);
});

it('an album whose images all sit behind unapproved links is a 404 and builds nothing', function () {
    $public = gzSymlinkLayout($this->sandbox);
    app()->instance(AlbumZipService::class, new AlbumZipService($public));
    gzLinkedAlbum(['ext/external.jpg', 'backup/backup.jpg', 'media/../media-backup/backup.jpg']);

    $this->get('/download-album-1.htm')->assertNotFound();
    expect(gzArchives())->toBe([]);
});
