<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The item page's banner, transcript (PDF) and Word (DOCX) files — OLD
 * `UploadBanner` / `confirm_delete_banner`, `ConfirmUploadPdf` /
 * `confirm_delete_pdf`, `ConfirmUploaddocx` / `confirm_delete_docx`
 * (`modules/Khotab/admin/index.php`, production working copy).
 *
 * Each upload is stored where OLD stored it and where the public site reads
 * it — `media/{banner|pdf|docx}/{floor(id/1000)}/{id}.{gif|pdf|docx}`, under
 * `admin_khotab.media_root` — replacing any earlier file. Then OLD's writes:
 *
 * - banner: `banner = 1`;
 * - pdf: author `pdf + 1`, then `pdf = 1, pdf_time = now`;
 * - docx: author `docx + 1`, then `docx = 1, docx_time = now`.
 *
 * OLD added 1 to the author's counter on every successful upload, a
 * replacement included («تعديل التفريغ» / «تعديل الوورد» post to the same
 * handler), and this does too.
 *
 * Deleting writes only the flags, as OLD did: banner `banner = 0`; pdf
 * author `pdf - 1` and `pdf = 0`; docx author `docx - 1` and `docx = 0`. OLD
 * never removed the file (its `unlink` is commented out), and neither does
 * this.
 *
 * Permissions are the real `admincp/khotab/menu.php` keys; the owner mapped
 * the two deletes OLD left unchecked (2026-10-21): banner delete →
 * `uploadbanner`, PDF delete → `uploadpdf`. Every write is one locked
 * transaction and audited.
 */
final class KhotabItemFiles
{
    public const KINDS = [
        'banner' => ['folder' => 'banner', 'ext' => 'gif', 'upload' => 'khotab.uploadbanner', 'delete' => 'khotab.uploadbanner', 'author' => null, 'time' => null, 'log' => 'banner'],
        'pdf' => ['folder' => 'pdf', 'ext' => 'pdf', 'upload' => 'khotab.uploadpdf', 'delete' => 'khotab.uploadpdf', 'author' => 'pdf', 'time' => 'pdf_time', 'log' => 'pdf'],
        'docx' => ['folder' => 'docx', 'ext' => 'docx', 'upload' => 'khotab.adddocx', 'delete' => 'khotab.deletedocx', 'author' => 'docx', 'time' => 'docx_time', 'log' => 'docx'],
    ];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** The file's public path, `media/…`. */
    public static function path(string $kind, int $itemId): string
    {
        return MediaPathResolver::path(self::KINDS[$kind]['folder'], $itemId, self::KINDS[$kind]['ext']);
    }

    /** Where that file lives on disk. */
    public static function absolutePath(string $kind, int $itemId): string
    {
        return rtrim((string) config('admin_khotab.media_root'), '/').'/'.substr(self::path($kind, $itemId), strlen('media/'));
    }

    public function upload(AdminUser $actor, KhotabItem $item, string $kind, UploadedFile $file, ?string $ip = null): void
    {
        $spec = self::KINDS[$kind];
        $this->authorizer->authorize($actor, $spec['upload'], $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $kind, $spec, $file, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()->first(['id', 'author']);
            abort_if($row === null, 404);

            $target = self::absolutePath($kind, (int) $row->id);
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create {$directory}");
            }
            $file->move($directory, basename($target));

            if ($spec['author'] !== null && (int) $row->author > 0) {
                $main->table('nuke_islamic_authors')->where('id', $row->author)->update([$spec['author'] => DB::raw($spec['author'].'+1')]);
            }

            $set = [$kind => 1];
            if ($spec['time'] !== null) {
                $set[$spec['time']] = time();
            }
            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update($set);

            $this->audit->record($actor, 'upload_'.$spec['log'], 'khotab', 'item', (int) $row->id, [
                'path' => self::path($kind, (int) $row->id),
                'bytes' => (int) filesize($target),
                'author_id' => (int) $row->author,
            ], $ip);
        });
    }

    public function delete(AdminUser $actor, KhotabItem $item, string $kind, ?string $ip = null): void
    {
        $spec = self::KINDS[$kind];
        $this->authorizer->authorize($actor, $spec['delete'], $item);

        DB::connection('main')->transaction(function () use ($actor, $item, $kind, $spec, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()->first(['id', 'author', $kind]);
            abort_if($row === null, 404);

            if ($spec['author'] !== null && (int) $row->author > 0) {
                $main->table('nuke_islamic_authors')->where('id', $row->author)->update([$spec['author'] => DB::raw($spec['author'].'-1')]);
            }
            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update([$kind => 0]);

            $this->audit->record($actor, 'delete_'.$spec['log'], 'khotab', 'item', (int) $row->id, [
                'was' => (int) $row->{$kind},
                'author_id' => (int) $row->author,
            ], $ip);
        });
    }

    /**
     * «طلب بانر» — OLD `RequestBanner`: refused while the item already has a
     * banner (`banner = 1`); otherwise `banner = -1` (a design request).
     * OLD's token was `requestbanner`, the real menu key used here.
     */
    public function requestBanner(AdminUser $actor, KhotabItem $item, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, 'khotab.requestbanner', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_khotab')->where('id', $item->id)->lockForUpdate()->first(['id', 'banner']);
            abort_if($row === null, 404);

            if ((string) $row->banner === '1') {
                return false;
            }

            $main->table('nuke_islamic_khotab')->where('id', $row->id)->update(['banner' => -1]);
            $this->audit->record($actor, 'req_banner', 'khotab', 'item', (int) $row->id, ['was' => (int) $row->banner], $ip);

            return true;
        });
    }
}
