<?php

/*
 * P0 (owner-approved 2026-11-09):
 *  S1 — CD release images take their stored extension from the server-detected image type, never the client name;
 *  S2 — gallery image downloads are canonically contained in the public root (or its approved media target).
 */

use App\Domain\Admin\Actions\Cds\CdImageList;
use App\Domain\Admin\Actions\Cds\UnsupportedCdImageException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\CdAdminHarness as H;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

uses(RefreshDatabase::class);

const MUD_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

/** A real uploaded file (content-detected MIME type, unlike UploadedFile::fake(), which reports one from the name). */
function mudUpload(string $clientName, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mud');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $clientName, null, null, true);
}

function mudTemp(): string
{
    $dir = sys_get_temp_dir().'/mud-'.bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);

    return (string) realpath($dir);
}

function mudRemove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            mudRemove($path.'/'.$entry);
        }
    }
    @rmdir($path);
}

// ================================================================ S1 — CD image extension

describe('CD image extension (S1)', function () {
    beforeEach(function () {
        $this->root = H::setUp();
    });

    afterEach(function () {
        H::tearDown($this->root);
    });

    it('stores valid GIF content uploaded as .html with a .gif extension, never .html', function () {
        $name = CdImageList::store(mudUpload('x.html', base64_decode(MUD_GIF)), 0);

        expect($name)->toMatch('/^0\d+\.gif$/')->not->toContain('html')
            ->and(is_file($this->root.'/'.$name))->toBeTrue()
            ->and(glob($this->root.'/*.html'))->toBe([]);
    });

    it('keeps valid JPG, PNG and GIF uploads (a .jpeg JPEG is stored as .jpg)', function () {
        expect(CdImageList::store(UploadedFile::fake()->image('a.jpg'), 0))->toMatch('/^0\d+\.jpg$/')
            ->and(CdImageList::store(UploadedFile::fake()->image('b.png'), 1))->toMatch('/^1\d+\.png$/')
            ->and(CdImageList::store(UploadedFile::fake()->image('c.gif'), 2))->toMatch('/^2\d+\.gif$/')
            ->and(CdImageList::store(UploadedFile::fake()->image('d.jpeg'), 3))->toMatch('/^3\d+\.jpg$/');
    });

    it('a mislabelled real image is stored under its real type', function () {
        expect(CdImageList::store(UploadedFile::fake()->image('photo.gif'), 0))->toMatch('/^0\d+\.gif$/')
            ->and(CdImageList::detectedExtension(mudUpload('photo.jpg', base64_decode(MUD_GIF))))->toBe('gif');
    });

    it('refuses content that is not an unambiguous JPEG, PNG or GIF, and writes nothing', function (string $name, string $content) {
        expect(fn () => CdImageList::store(mudUpload($name, $content), 0))
            ->toThrow(UnsupportedCdImageException::class);
        expect(glob($this->root.'/*'))->toBe([]);
    })->with([
        'html' => ['x.gif', '<html><body>hi</body></html>'],
        'svg' => ['x.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
        'php text' => ['x.jpg', '<?php echo 1;'],
        'empty' => ['x.gif', ''],
    ]);

    it('through the AdminCP route, a GIF named .html is stored as .gif', function () {
        $this->actingAs(H::superAdmin(), 'admin');

        $this->post(route('admin.cds.store'), [
            'title' => 'إسطوانة',
            'links' => [['title' => 'رابط', 'url' => 'https://archive.org/x.iso']],
            'images' => [mudUpload('cover.html', base64_decode(MUD_GIF))],
        ])->assertRedirect();

        $thumbnail = (string) DB::connection('main')->table('nuke_w2acd_w2acd')->value('thumbnail');
        expect($thumbnail)->toMatch('/^0\d+\.gif,$/')
            ->and(glob($this->root.'/*.html'))->toBe([]);
    });
});

// ================================================================ S2 — gallery download containment

describe('gallery download containment (S2)', function () {
    beforeEach(function () {
        InMemoryConnection::setup('main', [
            'nuke_albums' => MainSchema::nukeAlbums(),
            'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
        ]);
        $this->web = mudTemp();
        $this->outside = mudTemp();
        file_put_contents($this->outside.'/secret.jpg', 'outside-bytes');
        app()->usePublicPath($this->web);
    });

    afterEach(function () {
        mudRemove($this->web);
        mudRemove($this->outside);
    });

    $image = function (string $url): void {
        DB::connection('main')->table('nuke_albums_images')->insert(['image_id' => 1, 'album_id' => 1, 'url' => $url]);
    };

    it('serves a normal gallery image with its original download name', function () use ($image) {
        mkdir($this->web.'/media/albums/2', 0775, true);
        file_put_contents($this->web.'/media/albums/2/photo.jpg', 'gallery-bytes');
        $image('media/albums/2/photo.jpg');

        $response = $this->get('/albumimg-download-1.htm');

        $response->assertOk();
        expect($response->streamedContent())->toBe('gallery-bytes')
            ->and((string) $response->headers->get('content-disposition'))->toContain('photo.jpg');
    });

    it('serves an image through the approved public media symlink (decision-log #107)', function () use ($image) {
        mkdir($this->outside.'/media/albums', 0775, true);
        file_put_contents($this->outside.'/media/albums/ok.jpg', 'legacy-media-bytes');
        symlink($this->outside.'/media', $this->web.'/media');
        $image('media/albums/ok.jpg');

        $response = $this->get('/albumimg-download-1.htm');

        $response->assertOk();
        expect($response->streamedContent())->toBe('legacy-media-bytes');
    });

    it('refuses traversal and absolute paths that point outside', function () use ($image) {
        $relativeOut = str_repeat('../', substr_count($this->web, '/')).ltrim($this->outside, '/').'/secret.jpg';
        foreach ([$relativeOut, 'media/../../'.basename($this->outside).'/secret.jpg', $this->outside.'/secret.jpg'] as $url) {
            DB::connection('main')->table('nuke_albums_images')->delete();
            $image($url);
            $this->get('/albumimg-download-1.htm')->assertNotFound();
        }
    });

    it('refuses a symlinked directory that escapes the public root', function () use ($image) {
        symlink($this->outside, $this->web.'/escape');
        $image('escape/secret.jpg');

        $this->get('/albumimg-download-1.htm')->assertNotFound();
    });

    it('refuses a symlinked file that escapes the public root', function () use ($image) {
        symlink($this->outside.'/secret.jpg', $this->web.'/linked.jpg');
        $image('linked.jpg');

        $this->get('/albumimg-download-1.htm')->assertNotFound();
    });

    it('404s for a missing file, a directory, and an empty path', function () use ($image) {
        mkdir($this->web.'/media/albums', 0775, true);
        foreach (['media/albums/missing.jpg', 'media/albums', ''] as $url) {
            DB::connection('main')->table('nuke_albums_images')->delete();
            $image($url);
            $this->get('/albumimg-download-1.htm')->assertNotFound();
        }
    });
});
