<?php

namespace App\Domain\Admin\Actions\Cds;

use finfo;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Phase 6 — a release's images: files in the CD image directory, listed on
 * `nuke_w2acd_w2acd.thumbnail` as a comma list with OLD's trailing comma
 * (`"a.png,b.jpg,"`). The first image is the list cover
 * (`W2acdItem::firstThumbnailFilename()`); public pages read
 * `/images/cds_image2/<file>`.
 *
 * OLD (`cdsConfirmInsert` / `cdsConfirmEdit`) accepted jpg, jpeg, png and gif
 * by extension, named a file `{slot}{time()}.{ext}`, and never deleted a file
 * when it was removed or replaced from a release — the name was only dropped
 * from the list. All reproduced; a numeric suffix is added only when the OLD
 * name already exists, so an upload can never overwrite another file.
 *
 * S1 (P0, owner-approved 2026-11-09): the stored extension is no longer the client's. It comes from the
 * server-detected image type — `getimagesize()` and the content MIME type (finfo, read from the bytes) must agree on JPEG, PNG or
 * GIF, otherwise the upload is refused ({@see UnsupportedCdImageException}). A JPEG is stored as `.jpg`
 * (so a `.jpeg` upload is now stored as `.jpg`); PNG `.png`; GIF `.gif`. The validator's `mimes` rule alone
 * checks content but not the name, so a real GIF named `x.html` would otherwise have been stored as
 * `….html` inside the web-served image directory.
 */
final class CdImageList
{
    public const MAX_IMAGES = 5;

    /** Server-detected image type → [content MIME type, stored extension]. */
    private const ALLOWED_TYPES = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_GIF => ['image/gif', 'gif'],
    ];

    /** @return list<string> */
    public static function parse(?string $thumbnail): array
    {
        return array_values(array_filter(explode(',', (string) $thumbnail), fn (string $name) => trim($name) !== ''));
    }

    /** @param list<string> $names */
    public static function format(array $names): string
    {
        return $names === [] ? '' : implode(',', $names).',';
    }

    public static function root(): string
    {
        return rtrim((string) config('admin_cds.image_root'), '/');
    }

    public static function store(UploadedFile $file, int $slot): string
    {
        $root = self::root();
        if (! is_dir($root) && ! mkdir($root, 0775, true) && ! is_dir($root)) {
            throw new RuntimeException('CD image directory is not available.');
        }

        $extension = self::detectedExtension($file);
        $base = $slot.time();
        $name = "{$base}.{$extension}";
        for ($n = 1; file_exists("{$root}/{$name}"); $n++) {
            $name = "{$base}_{$n}.{$extension}";
        }

        $file->move($root, $name);

        return $name;
    }

    /**
     * The extension for the file's real image type; refuses anything that is not unambiguously JPEG, PNG or GIF.
     *
     * @throws UnsupportedCdImageException
     */
    public static function detectedExtension(UploadedFile $file): string
    {
        $path = (string) $file->getRealPath();
        $info = $path !== '' ? @getimagesize($path) : false;
        $type = is_array($info) ? $info[2] : null;

        if (! is_int($type) || ! isset(self::ALLOWED_TYPES[$type])) {
            throw new UnsupportedCdImageException('Unsupported CD image type.');
        }

        [$mime, $extension] = self::ALLOWED_TYPES[$type];
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($detected !== $mime) {
            throw new UnsupportedCdImageException('Ambiguous CD image type.');
        }

        return $extension;
    }

    /** Removes files this request wrote, when its transaction failed. */
    public static function discard(array $names): void
    {
        foreach ($names as $name) {
            @unlink(self::root().'/'.$name);
        }
    }
}
