<?php

namespace App\Support\Downloads;

/**
 * Content-Type of a proxied download (Phase 3B.1). An explicit allowlist keyed by file extension — exactly the types
 * legacy `download_khotab()` sent (`khotab/functions.php`) — with `application/octet-stream` for everything else.
 *
 * The extension comes from trusted metadata only: for a local file, the name of the file actually opened (symlinks
 * resolved); for a remote link, the path of the stored link. The upstream's own Content-Type is never used: a source
 * cannot make the browser treat a download as HTML, script or anything outside this list. Sent with
 * `X-Content-Type-Options: nosniff`.
 */
final class DownloadMime
{
    public const FALLBACK = 'application/octet-stream';

    /** @var array<string, string> */
    public const ALLOWLIST = [
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function for(string $link, bool $isRemote): string
    {
        if ($isRemote) {
            $path = parse_url($link, PHP_URL_PATH);
            $name = is_string($path) ? rawurldecode($path) : '';
        } else {
            $real = @realpath($link);
            $name = $real === false ? $link : $real;
        }

        return self::ALLOWLIST[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? self::FALLBACK;
    }

    /** @return array{Content-Type: string, X-Content-Type-Options: string} */
    public static function headers(string $link, bool $isRemote): array
    {
        return ['Content-Type' => self::for($link, $isRemote), 'X-Content-Type-Options' => 'nosniff'];
    }
}
