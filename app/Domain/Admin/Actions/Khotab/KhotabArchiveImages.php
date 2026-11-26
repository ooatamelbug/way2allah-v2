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

    /**
     * Multi-author creation (2026-11-25): give each copy its own file, copied from what the primary item stored, so
     * archive.org is fetched once whatever the number of copies. Only the kinds the primary actually has are copied;
     * a failed copy leaves that copy's flag at 0 («سحب الصور» recovers it, per item).
     *
     * @param  array{gif?: int, frame?: int}  $sourceFlags
     * @param  list<int>  $targetIds
     * @return array<int, array{gif?: int, frame?: int}> target id => the flags to set on it
     */
    public function copyFromItem(int $sourceId, array $sourceFlags, array $targetIds): array
    {
        $kinds = ['gif' => ['khotab_gifs', 'gif'], 'frame' => ['khotab_frames', 'jpg']];
        $result = [];

        foreach ($targetIds as $targetId) {
            $result[$targetId] = [];
            foreach ($kinds as $flag => [$folder, $extension]) {
                if (! isset($sourceFlags[$flag])) {
                    continue;
                }
                $source = $this->absolute(MediaPathResolver::path($folder, $sourceId, $extension));
                $target = $this->absolute(MediaPathResolver::path($folder, $targetId, $extension));
                $directory = dirname($target);

                if (is_file($source)
                    && (is_dir($directory) || @mkdir($directory, 0775, true) || is_dir($directory))
                    && @copy($source, $target)) {
                    $result[$targetId][$flag] = 1;
                }
            }
        }

        return $result;
    }

    /** @param string $relative `media/…` as MediaPathResolver builds it */
    private function absolute(string $relative): string
    {
        return rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr($relative, strlen('media/'));
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
