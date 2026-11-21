<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * «صورة الداعية الإفتراضية» on the edit page — OLD `KhotabUploadAuthorImage`
 * → `UploadAuthorImage()` (`modules/Khotab/admin/index.php:2387`): a JPG
 * under 1 MB stored as `media/authors/{id/1000}/{id}.jpg`, replacing the
 * previous one. OLD wrote no database row.
 *
 * F06 (owner-approved Option B, 2026-11-14): after the file is written, the
 * author's `author_image` is pointed at it as a root-relative URL with a
 * cache-busting version (`/media/authors/{b}/{id}.jpg?v={mtime}`). The
 * public pages resolve it through `Author::displayImageUrl()` (MediaUrl
 * applies the configured media base URL) and Gen 2's own
 * `author_image`-first pages can use it as a `src` unchanged. Only an upload
 * writes the column; the value it replaces is kept in the audit metadata so
 * it can be restored. No other image file (`authors/sq/{id}.png`, earlier
 * absolute `author_image` URLs) is touched.
 *
 * Corrections (owner, 2026-10-21): a normal CSRF-protected upload instead of
 * OLD's AJAX form (whose script came from an external URL), authorized as
 * `khotab.editauthor` (OLD checked nothing), the detected type checked as
 * well as the extension, stored under `admin_khotab.media_root`, audited.
 */
final class UploadKhotabAuthorImageAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public static function path(int $authorId): string
    {
        return MediaPathResolver::path('authors', $authorId, 'jpg');
    }

    public static function absolutePath(int $authorId): string
    {
        return rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr(self::path($authorId), strlen('media/'));
    }

    /** The value stored in `author_image` after an upload: root-relative, versioned by the file's mtime. */
    public static function publicUrl(int $authorId, int $version): string
    {
        return '/'.self::path($authorId).'?v='.$version;
    }

    public function execute(AdminUser $actor, Author $author, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.editauthor', $author);

        $target = self::absolutePath((int) $author->id);
        $directory = dirname($target);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
        $file->move($directory, basename($target));
        clearstatcache(true, $target);

        $previousImage = $author->getRawOriginal('author_image');
        // The audit refuses markup in metadata; a legacy value carrying any is recorded as unrecordable instead of
        // aborting the upload after the file is already in place.
        if (is_string($previousImage) && preg_match('/<[a-z!\/][^>]*>/i', $previousImage) === 1) {
            $previousImage = '[unrecordable: contained markup]';
        }
        $author->forceFill(['author_image' => self::publicUrl((int) $author->id, (int) filemtime($target))])->save();

        $this->audit->record($actor, 'upload_author_image', 'khotab', 'author', (int) $author->id, [
            'path' => self::path((int) $author->id),
            'bytes' => (int) filesize($target),
            'author_image' => $author->author_image,
            'previous_author_image' => $previousImage,
        ], $ip);
    }
}
