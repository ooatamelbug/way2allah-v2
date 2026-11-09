<?php

namespace App\Support\Filesystem;

/**
 * Canonical containment for a database-sourced, web-root-relative file path (S2, P0 2026-11-09).
 *
 * A path is accepted only when its canonical location (`realpath`, so `..` segments and symlinks are
 * resolved) is a readable regular file strictly inside a trusted root. Trusted roots — the same rule
 * decision-log #107 approved for `AlbumZipService`: the resolved public root itself, and the resolved
 * target of `<public root>/media` (locally a symlink to the approved legacy media tree). Any other
 * symlink that leads outside those roots is rejected, as is a root that resolves to the filesystem root.
 * Read-only: never creates or modifies anything.
 */
final class PublicFileResolver
{
    /** The canonical file, or null when it is missing, unreadable, not a regular file, or outside the trusted roots. */
    public static function resolve(string $publicRoot, string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0") || $publicRoot === '' || str_contains($publicRoot, "\0")) {
            return null;
        }

        $roots = self::trustedRoots($publicRoot);
        if ($roots === []) {
            return null;
        }

        $real = realpath(rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, '/\\'));
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            return null;
        }

        foreach ($roots as $root) {
            if (str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function trustedRoots(string $publicRoot): array
    {
        $roots = [];
        foreach ([$publicRoot, rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'media'] as $candidate) {
            $real = realpath($candidate);
            if ($real === false || ! is_dir($real)) {
                continue;
            }
            $real = rtrim($real, DIRECTORY_SEPARATOR);
            if ($real !== '' && ! in_array($real, $roots, true)) {
                $roots[] = $real;
            }
        }

        return $roots;
    }
}
