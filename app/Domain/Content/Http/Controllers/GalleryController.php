<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Album;
use App\Domain\Content\Models\AlbumImage;
use Illuminate\Contracts\View\View;
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

        return view('gallery.index', compact('albums'));
    }

    public function show(int $album): View
    {
        $albumModel = Album::findOrFail($album);
        $images = $albumModel->images()->orderBy('order')->get();

        $albumModel->recordView();

        return view('gallery.show', compact('albumModel', 'images'));
    }

    public function download(int $image): StreamedResponse
    {
        $albumImage = AlbumImage::findOrFail($image);

        $path = public_path($albumImage->url);

        abort_unless(is_file($path), 404, 'File not found');

        return response()->streamDownload(function () use ($path) {
            $handle = fopen($path, 'rb');
            abort_if($handle === false, 500, 'Error reading file');

            while (! feof($handle)) {
                echo fread($handle, 8192);
            }

            fclose($handle);
        }, basename($path));
    }
}
