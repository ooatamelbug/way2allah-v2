<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The last step of OLD `ConfirmInsertKhotab`: the archive.org animated GIF
 * the «التالي» step offered → `media/khotab_gifs/{id/1000}/{id}.gif`
 * (`gif = 1`), the chosen still frame → `media/khotab_frames/{id/1000}/{id}.jpg`
 * (`frame = 1`).
 *
 * Corrections, as for the group page: only http(s) archive.org URLs are
 * fetched (OLD fetched whatever the hidden fields held), each request is
 * bounded by `admin_khotab.archive_timeout`, and a failed download simply
 * leaves its flag at 0 (OLD would `die()` half-way). Written for the series
 * page; the approved group page keeps its own identical copy of this step
 * inside InsertKhotabItemAction, left untouched.
 */
final class KhotabArchiveImages
{
    private const ARCHIVE_HOST = '/(^|\.)archive\.org$/i';

    /** @return array{gif?: int, frame?: int} the flags to set on the item */
    public function store(int $itemId, string $gifUrl, string $frameUrl): array
    {
        $flags = [];

        if ($this->download($gifUrl, MediaPathResolver::path('khotab_gifs', $itemId, 'gif'))) {
            $flags['gif'] = 1;
        }
        if ($this->download($frameUrl, MediaPathResolver::path('khotab_frames', $itemId, 'jpg'))) {
            $flags['frame'] = 1;
        }

        return $flags;
    }

    /** @param string $relative `media/…` as MediaPathResolver builds it */
    private function download(string $url, string $relative): bool
    {
        if ($url === '' || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            || preg_match(self::ARCHIVE_HOST, (string) parse_url($url, PHP_URL_HOST)) !== 1) {
            return false;
        }

        try {
            $response = Http::timeout(max(1, (int) config('admin_khotab.archive_timeout', 20)))->get($url);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful() || $response->body() === '') {
            return false;
        }

        $path = rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr($relative, strlen('media/'));
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $response->body()) !== false;
    }
}
