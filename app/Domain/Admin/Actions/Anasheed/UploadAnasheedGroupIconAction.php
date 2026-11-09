<?php

namespace App\Domain\Admin\Actions\Anasheed;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\AnasheedGroup;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * «اضافة صورة للقسم» — OLD `AnasheedGroupIcon` → `UploadIcon()`
 * (`modules/Anasheed/admin/index.php`, HEAD:1229-1380): a `.jpg` stored as
 * `media/anasheed/icons/{floor(id/1000)}/{id}.jpg`, replacing the previous
 * one, then `icon = 1` on the group.
 *
 * Corrections: authorized (`anasheed.addicon`; OLD's GreenZone was inert);
 * the request checks the detected type as well as the extension; the
 * uploaded name is never used — the target is always `{id}.jpg`, so no path
 * can be steered (OLD's rename/«gennewname» branches could write other names
 * into the folder); the file lands under `admin_anasheed.media_root`.
 * `icon = 1` and the audit row commit only after the file is in place; if
 * they fail, the previous file (or none) is put back.
 */
final class UploadAnasheedGroupIconAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public static function path(int $groupId): string
    {
        return MediaPathResolver::path('anasheed/icons', $groupId, 'jpg');
    }

    public static function absolutePath(int $groupId): string
    {
        return rtrim((string) config('admin_anasheed.media_root'), '/').'/'.substr(self::path($groupId), strlen('media/'));
    }

    public function execute(AdminUser $actor, AnasheedGroup $group, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'anasheed.addicon', $group);

        $groupId = (int) $group->id;
        $target = self::absolutePath($groupId);
        $directory = dirname($target);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}");
        }

        $backup = null;
        if (is_file($target)) {
            $backup = $target.'.previous-'.bin2hex(random_bytes(4));
            if (! @copy($target, $backup)) {
                throw new RuntimeException("Cannot keep the previous icon {$target}");
            }
        }

        $file->move($directory, basename($target));

        try {
            DB::connection('main')->transaction(function () use ($actor, $groupId, $target, $ip) {
                $main = DB::connection('main');
                abort_if($main->table('nuke_anasheed_groups')->where('id', $groupId)->lockForUpdate()->value('id') === null, 404);
                $main->table('nuke_anasheed_groups')->where('id', $groupId)->update(['icon' => 1]);

                $this->audit->record($actor, 'upload_group_icon', 'anasheed', 'group', $groupId, [
                    'path' => self::path($groupId),
                    'bytes' => (int) filesize($target),
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
