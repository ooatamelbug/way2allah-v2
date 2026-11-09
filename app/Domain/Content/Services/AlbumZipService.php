<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Album;
use App\Domain\Content\Models\AlbumImage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Full-album ZIP downloads (owner, 2026-11-06; decision-log #107) — restores OLD's «حفظ جميع صور الألبوم» without
 * OLD's server: OLD's admin pre-built `zipped_albums/{id}.zip` (`new_album/admin/index.php:451-490`) and the visitor
 * handlers only echoed a URL to it; Gen 2 pointed at `old.way2allah.com`. Here the archive is built on demand, cached
 * privately and reused:
 *
 *  - eligible images: the album's `nuke_albums_images` rows in display order (`order`, then `image_id`) whose `url`
 *    resolves (realpath) to a readable image file inside a trusted root — `public/` or the resolved target of the
 *    known `public/media` location (a symlink to `legacy-project/media` locally) — anything else, missing, or not an
 *    image extension is skipped and logged;
 *  - cache: one archive per album in Laravel's private `local` disk (`gallery-zips/`), named by a fingerprint of the
 *    eligible entries (id, url, order, size, mtime) — any delete / replace / reorder changes the name, the next request
 *    rebuilds, and older archives of that album are removed (at most one archive per album);
 *  - builds: under a per-album cache lock (no duplicate concurrent builds), written to a temporary file in the same
 *    directory and renamed into place (atomic); a failed build removes its temporary file;
 *  - limits: image count and total bytes; entries are stored uncompressed (images are already compressed), and
 *    ZipArchive reads files from disk at close — no image is loaded into PHP memory.
 */
class AlbumZipService
{
    public const DIRECTORY = 'gallery-zips';

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    private const LOCK_SECONDS = 300;

    private const FINGERPRINT_VERSION = 'v1';

    /** Laravel's public directory (resolved) — image `url`s are relative to it. */
    private readonly string $publicRoot;

    /** @var list<string> resolved directories an image may live in: `public/` and the approved `public/media` target */
    private readonly array $trustedRoots;

    private readonly string $storageRoot;

    public function __construct(
        ?string $publicRoot = null,
        ?string $storageRoot = null,
        private readonly int $maxImages = 500,
        private readonly int $maxTotalBytes = 536_870_912,
        private readonly int $lockWaitSeconds = 30,
    ) {
        $this->publicRoot = rtrim((string) realpath($publicRoot ?? public_path()), DIRECTORY_SEPARATOR);
        $this->trustedRoots = $this->trustedRootsFor($this->publicRoot);
        $this->storageRoot = rtrim($storageRoot ?? Storage::disk('local')->path(self::DIRECTORY), DIRECTORY_SEPARATOR);
    }

    /**
     * The absolute path of a current archive for the album, building it when needed.
     *
     * @throws AlbumZipUnavailableException no valid image, or over the limits
     * @throws LockTimeoutException another build of this album did not finish in time
     */
    public function archiveFor(Album $album): string
    {
        $albumId = (int) $album->album_id;
        $entries = $this->eligibleEntries($album);

        $refusal = match (true) {
            $entries === [] => 'no valid image files',
            count($entries) > $this->maxImages => "more than {$this->maxImages} images",
            array_sum(array_column($entries, 'size')) > $this->maxTotalBytes => "larger than {$this->maxTotalBytes} bytes",
            default => null,
        };

        if ($refusal !== null) {
            // an archive cached before the album changed must not linger
            $this->removeOtherArchives($albumId, '');

            throw new AlbumZipUnavailableException("Album {$albumId}: {$refusal}.");
        }

        $final = $this->storageRoot.DIRECTORY_SEPARATOR.'album-'.$albumId.'-'.$this->fingerprint($entries).'.zip';

        if (is_file($final)) {
            return $final;
        }

        return Cache::lock('gallery-album-zip:'.$albumId, self::LOCK_SECONDS)
            ->block($this->lockWaitSeconds, function () use ($albumId, $entries, $final): string {
                if (! is_file($final)) {
                    $this->build($entries, $final);
                }
                $this->removeOtherArchives($albumId, $final);

                return $final;
            });
    }

    /**
     * @return list<array{id: int, url: string, order: int, path: string, size: int, mtime: int, name: string}>
     */
    private function eligibleEntries(Album $album): array
    {
        $images = AlbumImage::query()->where('album_id', $album->album_id)
            ->orderBy('order')->orderBy('image_id')->get(['image_id', 'url', 'order']);
        $entries = [];
        $width = max(3, strlen((string) $images->count()));

        foreach ($images as $image) {
            $path = $this->resolve((string) $image->url);

            if ($path === null) {
                Log::warning('Album ZIP: image skipped (missing, unreadable or outside the media root).', [
                    'album_id' => (int) $album->album_id, 'image_id' => (int) $image->image_id,
                ]);

                continue;
            }

            $entries[] = [
                'id' => (int) $image->image_id,
                'url' => (string) $image->url,
                'order' => (int) $image->order,
                'path' => $path,
                'size' => (int) filesize($path),
                'mtime' => (int) filemtime($path),
                'name' => sprintf('%0'.$width.'d-%s', count($entries) + 1, $this->entryName($path)),
            ];
        }

        return $entries;
    }

    /** A real, readable image file strictly inside the media root — otherwise null. */
    private function resolve(string $url): ?string
    {
        if ($url === '' || str_contains($url, "\0") || $this->trustedRoots === []) {
            return null;
        }

        $real = realpath($this->publicRoot.DIRECTORY_SEPARATOR.ltrim($url, '/\\'));

        if ($real === false
            || ! $this->insideTrustedRoot($real)
            || ! is_file($real) || ! is_readable($real)
            || ! in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)
        ) {
            return null;
        }

        return $real;
    }

    /**
     * Decision-log #107 (symlink fix, owner-approved): the trusted roots are the resolved public directory and the
     * resolved target of the known `public/media` location only — locally `public/media` is a symlink to the approved
     * `legacy-project/media`. No other symlink under `public/` is trusted, and a root that resolves to the filesystem
     * root is never accepted.
     *
     * @return list<string>
     */
    private function trustedRootsFor(string $publicRoot): array
    {
        if ($publicRoot === '') {
            return [];
        }

        $roots = [$publicRoot];
        $media = realpath($publicRoot.DIRECTORY_SEPARATOR.'media');

        if ($media !== false && is_dir($media)) {
            $media = rtrim($media, DIRECTORY_SEPARATOR);
            if ($media !== '' && ! in_array($media, $roots, true)) {
                $roots[] = $media;
            }
        }

        return $roots;
    }

    /** Directory-boundary containment: `/x/media` admits `/x/media/…`, never `/x/media-backup/…`. */
    private function insideTrustedRoot(string $real): bool
    {
        foreach ($this->trustedRoots as $root) {
            if (str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /** A flat, safe entry name: no directories, no control or path characters; the extension kept. */
    private function entryName(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $stem = (string) preg_replace('/[^\p{L}\p{N}._-]+/u', '-', pathinfo($path, PATHINFO_FILENAME));
        $stem = trim(Str::limit($stem, 80, ''), '.-_');

        return ($stem === '' ? 'image' : $stem).'.'.$extension;
    }

    /** @param  list<array{id: int, url: string, order: int, size: int, mtime: int}>  $entries */
    private function fingerprint(array $entries): string
    {
        $basis = array_map(fn (array $e) => [$e['id'], $e['url'], $e['order'], $e['size'], $e['mtime']], $entries);

        return substr(hash('sha256', self::FINGERPRINT_VERSION.json_encode($basis)), 0, 32);
    }

    /** @param  list<array{path: string, name: string}>  $entries */
    private function build(array $entries, string $final): void
    {
        if (! is_dir($this->storageRoot) && ! mkdir($this->storageRoot, 0775, true) && ! is_dir($this->storageRoot)) {
            throw new RuntimeException('Album ZIP: cannot create the archive directory.');
        }

        $temporary = $final.'.tmp-'.Str::random(12);

        try {
            $this->writeArchive($temporary, $entries);

            if (! rename($temporary, $final)) {
                throw new RuntimeException('Album ZIP: cannot move the finished archive into place.');
            }
        } catch (Throwable $e) {
            Log::error('Album ZIP: build failed.', ['archive' => basename($final), 'error' => $e->getMessage()]);

            throw $e;
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @param  list<array{path: string, name: string}>  $entries */
    protected function writeArchive(string $temporary, array $entries): void
    {
        $zip = new ZipArchive;

        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Album ZIP: cannot open a temporary archive.');
        }

        foreach ($entries as $entry) {
            if (! $zip->addFile($entry['path'], $entry['name'])) {
                $zip->close();

                throw new RuntimeException('Album ZIP: cannot add an entry.');
            }
            $zip->setCompressionName($entry['name'], ZipArchive::CM_STORE);
        }

        if (! $zip->close()) {
            throw new RuntimeException('Album ZIP: cannot write the archive.');
        }
    }

    /** Keep at most the current archive per album; also clears leftover temporary files of that album. */
    private function removeOtherArchives(int $albumId, string $keep): void
    {
        foreach (glob($this->storageRoot.DIRECTORY_SEPARATOR.'album-'.$albumId.'-*') ?: [] as $file) {
            if ($file !== $keep && is_file($file)) {
                @unlink($file);
            }
        }
    }
}
