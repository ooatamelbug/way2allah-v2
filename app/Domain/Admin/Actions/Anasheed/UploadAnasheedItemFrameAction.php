<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * «اضافة صورة للمادة» — OLD `AnasheedDetailes` → `AnasheedUploadBanner`
 * (`UploadBanner()`, `modules/Anasheed/admin/index.php`, production working
 * copy). Despite OLD's "بانر" wording this is the item's FRAME — the
 * thumbnail the public grids show: a `.jpg` stored as
 * `media/anasheed/frame/{floor(id/1000)}/{id}.jpg`, replacing the previous
 * one, then `frame = 1` and an `upload_image` log row.
 *
 * Corrections: authorized (`anasheed.addbanner`, OLD's GreenZone token;
 * GreenZone was inert); the request checks the detected JPEG type, not just
 * the name; the uploaded name is never used; the file lands under
 * `admin_anasheed.media_root`. `frame = 1` and the audit row commit only after
 * the file is in place; if they fail, the previous file (or none) is put back.
 * No `banner` column is written — production has none.
 */
final class UploadAnasheedItemFrameAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public static function path(int $itemId): string
    {
        return MediaPathResolver::path('anasheed/frame', $itemId, 'jpg');
    }

    public static function absolutePath(int $itemId): string
    {
        return rtrim((string) config('admin_anasheed.media_root'), '/').'/'.substr(self::path($itemId), strlen('media/'));
    }

    public function execute(AdminUser $actor, AnasheedItem $item, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'anasheed.addbanner', $item);

        $itemId = (int) $item->id;
        $target = self::absolutePath($itemId);
        $directory = dirname($target);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $backup = null;
        if (is_file($target)) {
            $backup = $target.'.previous-'.bin2hex(random_bytes(4));
            if (! @copy($target, $backup)) {
                throw new RuntimeException("Cannot keep the previous frame {$target}");
            }
        }

        $file->move($directory, basename($target));

        try {
            DB::connection('main')->transaction(function () use ($actor, $itemId, $target, $ip) {
                $main = DB::connection('main');
                $row = $main->table('nuke_anasheed_anasheed')->where('id', $itemId)->lockForUpdate()->first(['id', 'frame']);
                abort_if($row === null, 404);
                $main->table('nuke_anasheed_anasheed')->where('id', $itemId)->update(['frame' => 1]);

                $this->audit->record($actor, 'upload_image', 'anasheed', 'item', $itemId, [
                    'path' => self::path($itemId),
                    'bytes' => (int) filesize($target),
                    'frame' => ['from' => (int) $row->frame, 'to' => 1],
                ], $ip);
            });
        } catch (Throwable $e) {
            $backup !== null ? @rename($backup, $target) : @unlink($target);

            throw $e;
        }

        if ($backup !== null) {
            @unlink($backup);
        }
    }
}
