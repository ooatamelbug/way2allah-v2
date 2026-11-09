<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdItem;
use App\Domain\Content\Support\CdBanner;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * W2ACD public admin parity, Phase 1 (I5 / I6 / I7) — a CD release's banner:
 * OLD `add_banner` / `edit_banner` → `UploadBanner`, and `W2ACDDelete_banner`
 * → `W2ACDConfirmDelete_banner` (`modules/w2acd/admin/index.php`).
 *
 * OLD accepted a gif, jpg or png of at most 40 000 bytes and always saved it as
 * `media/w2acd/banner/{floor(id/1000)}/{id}.gif` — the name `cds-code` reads —
 * then set `banner = 1`; a replacement overwrote the file. Delete set
 * `banner = 0` and kept the file (its `unlink` is commented out).
 *
 * Kept: the accepted types, the size, the path and name, the flag, the
 * overwrite and the retained file. Corrected (owner, 2026-11-01): OLD renamed
 * the uploaded bytes, so a jpg or png sat under a `.gif` name. A GIF is stored
 * byte for byte; a JPEG or PNG is decoded and re-encoded as a real GIF (GD, as
 * `ThumbnailService` already uses; GIF has no alpha channel, so transparency
 * is flattened onto white). The file is written beside the target and renamed
 * into place, so a failed conversion never replaces the current banner.
 */
final class CdBannerFiles
{
    public const MAX_BYTES = 40000;

    /** Refused before decoding, so a tiny file cannot expand into a huge bitmap. */
    public const MAX_PIXELS = 4000 * 4000;

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function upload(AdminUser $actor, W2acdItem $release, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $release);

        $info = @getimagesize((string) $file->getRealPath());
        $type = is_array($info) ? (int) $info[2] : 0;
        if (! in_array($type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new InvalidArgumentException('not an image');
        }
        if ((int) $info[0] * (int) $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('too large');
        }

        DB::connection('main')->transaction(function () use ($actor, $release, $file, $type, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_w2acd')->where('id', $release->getKey())->lockForUpdate()->first(['id', 'banner']);
            abort_if($row === null, 404);

            $target = CdBanner::absolutePath((int) $row->id);
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create {$directory}");
            }

            $temporary = $directory.'/.'.basename($target).'.'.bin2hex(random_bytes(6));
            try {
                $this->writeGif((string) $file->getRealPath(), $type, $temporary);
                if (! @rename($temporary, $target)) {
                    throw new RuntimeException("Cannot write {$target}");
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }

            $main->table('nuke_w2acd_w2acd')->where('id', $row->id)->update(['banner' => 1]);

            $this->audit->record($actor, 'upload_banner', 'w2acd', 'cd_release', (int) $row->id, [
                'path' => CdBanner::path((int) $row->id),
                'bytes' => (int) filesize($target),
                'source_type' => image_type_to_mime_type($type),
                'was' => (int) $row->banner,
            ], $ip);
        });
    }

    public function delete(AdminUser $actor, W2acdItem $release, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $release);

        DB::connection('main')->transaction(function () use ($actor, $release, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_w2acd')->where('id', $release->getKey())->lockForUpdate()->first(['id', 'banner']);
            abort_if($row === null, 404);

            $main->table('nuke_w2acd_w2acd')->where('id', $row->id)->update(['banner' => 0]);

            $this->audit->record($actor, 'delete_banner', 'w2acd', 'cd_release', (int) $row->id, ['was' => (int) $row->banner], $ip);
        });
    }

    private function writeGif(string $source, int $type, string $destination): void
    {
        if ($type === IMAGETYPE_GIF) {
            if (! @copy($source, $destination)) {
                throw new RuntimeException('Cannot store the banner');
            }

            return;
        }

        $image = $type === IMAGETYPE_JPEG ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('not an image');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        if (! @imagegif($canvas, $destination)) {
            throw new RuntimeException('Cannot store the banner');
        }
    }
}
