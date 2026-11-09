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
 * previous one. OLD wrote no database row, and neither does this.
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

    public function execute(AdminUser $actor, Author $author, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.editauthor', $author);

        $target = self::absolutePath((int) $author->id);
        $directory = dirname($target);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }
        $file->move($directory, basename($target));

        $this->audit->record($actor, 'upload_author_image', 'khotab', 'author', (int) $author->id, [
            'path' => self::path((int) $author->id),
            'bytes' => (int) filesize($target),
        ], $ip);
    }
}
