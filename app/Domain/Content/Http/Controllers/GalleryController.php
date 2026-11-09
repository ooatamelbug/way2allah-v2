<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Album;
use App\Domain\Content\Models\AlbumImage;
use App\Domain\Content\Services\AlbumZipService;
use App\Domain\Content\Services\AlbumZipUnavailableException;
use App\Support\Filesystem\PublicFileResolver;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Replaces `gallery/list.php` and `gallery/item.php` — Roadmap task 4.6.
 *
 * `gallery/list.php`'s `op=download-album` branch (bulk album zip
 * download, `downlaod_album()`) is deliberately NOT ported — confirmed
 * unreachable on multiple independent levels, not silently dropped: its
 * own trigger URL (`download-album-{id}.htm`) routes through the
 * confirmed-nonexistent `new_modules.php` (IF-026, 3 duplicate
 * `.htaccess` rules for it, all equally dead), and the function itself
 * doesn't even create a zip file — it assumes one already exists at a
 * hardcoded path outside the web root and just echoes that path back.
 * [Superseded 2026-11-06, decision-log #107 (owner): full-album ZIP downloads are restored natively —
 * `downloadAlbum()` below, `AlbumZipService`; no dependency on OLD's pre-built archives.]
 *
 * IF-027's fixes: `index()` orders albums by `album_id` (the dead
 * `@order`-based sort never had a real effect to preserve); `download()`
 * resolves images from the app's own storage, not a hardcoded legacy
 * server path.
 */
class GalleryController
{
    public function index(): View
    {
        $albums = Album::orderBy('album_id')->get(['album_id', 'title', 'count', 'is_compressed', 'last_update']);

        return view('gallery.index', [
            'albums' => $albums,
            'thumbnailUrls' => Album::thumbnailUrlsFor($albums),
        ]);
    }

    public function show(int $album): View
    {
        $albumModel = Album::findOrFail($album);
        $images = $albumModel->images()->orderBy('order')->get();

        $albumModel->recordView();

        return view('gallery.show', compact('albumModel', 'images'));
    }

    /**
     * `GET /download-album-{id}.htm` (decision-log #107) — the album's ZIP as a download. Only albums flagged
     * `is_compressed = 1` (the flag that already shows the download buttons) are offered; anything else, and an album
     * with no valid image left or over the limits, is a plain 404. A build still running elsewhere past the wait is a
     * 503 with Retry-After. No storage path is disclosed.
     */
    public function downloadAlbum(int $album, AlbumZipService $zips): BinaryFileResponse
    {
        $albumModel = Album::query()->whereKey($album)->first();

        abort_if($albumModel === null || (int) $albumModel->is_compressed !== 1, 404);

        try {
            $path = $zips->archiveFor($albumModel);
        } catch (AlbumZipUnavailableException) {
            abort(404);
        } catch (LockTimeoutException) {
            abort(503, '', ['Retry-After' => '30']);
        }

        return response()->download($path, 'album-'.$album.'.zip', ['Content-Type' => 'application/zip']);
    }

    public function download(int $image): StreamedResponse
    {
        $albumImage = AlbumImage::findOrFail($image);

        // S2 (P0, 2026-11-09): the database path must canonically resolve to a readable file inside the public
        // root (or its approved `media` target) — no `..` or symlink escape. The download name is unchanged.
        $path = PublicFileResolver::resolve(public_path(), (string) $albumImage->url);
        $name = basename(public_path((string) $albumImage->url));

        abort_if($path === null, 404, 'File not found');

        return response()->streamDownload(function () use ($path) {
            $handle = fopen($path, 'rb');
            abort_if($handle === false, 500, 'Error reading file');

            while (! feof($handle)) {
                echo fread($handle, 8192);
            }

            fclose($handle);
        }, $name);
    }
}
