<?php

namespace App\Domain\Content\Support;

/**
 * F10 / B5 — a Khotab video item's optional CUSTOM COVER (owner-approved 2026-11-16,
 * `docs/reports/admincp-b5-implementation-results.md`).
 *
 * The cover is a file of its own, `media/khotab_covers/{floor(id/1000)}/{id}.jpg` under `admin_khotab.media_root`,
 * and **the file itself is the only state**: no column, no table, no legacy flag (`frame`, `gif`, `build_thumb`,
 * `thumbnail`) is read or written. The Archive.org copies (`media/khotab_frames/…`, `media/khotab_gifs/…`) are never
 * touched by covers, and no Archive.org fetch ever writes here, so:
 *
 * - no cover → every consumer keeps its existing resolution (frame / GIF, its own file checks and fallbacks);
 * - a cover → it wins, including over the animated GIF on GIF-first surfaces;
 * - cover removed → back to the existing resolution, with whatever Archive.org frame is current.
 *
 * `url()` costs one filesystem check (no database query); the version suffix `?v={mtime}-{inode}` changes on every
 * replacement (the author-image `?v={mtime}` pattern, plus the inode so two replacements in one second differ).
 */
final class KhotabCover
{
    public const FOLDER = 'khotab_covers';

    /** `media/khotab_covers/{b}/{id}.jpg` */
    public static function relativePath(int $itemId): string
    {
        return MediaPathResolver::path(self::FOLDER, $itemId, 'jpg');
    }

    /** The file under `admin_khotab.media_root`. */
    public static function absolutePath(int $itemId): string
    {
        return MediaPathResolver::absolute(self::relativePath($itemId));
    }

    /** The public URL with its cache-busting version, or null when the item has no (non-empty) cover file. */
    public static function url(int $itemId): ?string
    {
        if ($itemId <= 0) {
            return null;
        }

        $file = self::absolutePath($itemId);
        clearstatcache(true, $file);
        if (! is_file($file)) {
            return null;
        }

        $size = @filesize($file);
        $mtime = @filemtime($file);
        if ($size === false || $size === 0 || $mtime === false) {
            return null;
        }

        // Every replacement renames a NEW file into place, so its inode changes even within the same second.
        return MediaUrl::asset(self::relativePath($itemId)).'?v='.$mtime.'-'.(int) @fileinode($file);
    }
}
