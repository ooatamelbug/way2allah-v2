<?php

namespace App\Domain\Admin\Actions\Ads;

use App\Domain\Content\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * F01 / B6a — OLD `uploadfile()` (`modules/ads/admin/index.php:288-314`) wrote ANY extension to
 * `public_html/media/bann/{time}.{ext}` and stored `https://way2allah.com/media/bann/{time}.{ext}`.
 *
 * Secure equivalent, same location and URL shape:
 * - the file was already validated as JPEG/PNG/GIF/WebP by content and decodes as an image (AdRequest);
 * - the extension comes from the DETECTED type, never from the client's file name (no `.php`, `.svg`, `.html`);
 * - the name is `{time}_{random}.{ext}` (OLD's bare `{time}` could collide);
 * - it is written under `admin_khotab.media_root` (`bann/`), the directory served at `media.base_url`
 *   (deployment precondition: `php artisan media:check-roots`);
 * - the stored value is absolute, like OLD's, so OLD / Gen 2 renderers keep working: the media base URL,
 *   made absolute with `app.url` when the base is relative.
 */
final class StoreAdImageAction
{
    /** @var array<string, string> detected MIME → extension */
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    public function execute(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $extension = self::EXTENSIONS[$mime] ?? throw new RuntimeException('Unsupported advertisement image type.');

        $directory = rtrim((string) config('admin_khotab.media_root'), '/').'/bann';
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $name = time().'_'.bin2hex(random_bytes(4)).'.'.$extension;
        $file->move($directory, $name);

        return self::publicUrl($name);
    }

    public static function publicUrl(string $name): string
    {
        $url = MediaUrl::asset('bann/'.$name);

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base === '' ? $url : $base.'/'.ltrim($url, '/');
    }
}
