<?php

namespace App\Domain\Admin\Actions\Cds;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\W2acdGroup;
use App\Domain\Content\Support\CdGroupIcon;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * G1 «اضافة صورة للقسم» — OLD `W2ACDGroupIcon` → `UploadIcon`
 * (`modules/w2acd/admin/index.php`, `groupicon.htm`).
 *
 * OLD accepted a gif, jpg or png (`checkExt`) of at most 10 000 bytes (the
 * form's `MAX_FILE_SIZE`, which PHP enforces; `d_config.php` allows 40 000)
 * and always saved it as `media/w2acd/icons/{floor(id/1000)}/{id}.jpg`, then
 * set `icon = 1`; a new upload overwrote the file. OLD had no icon delete.
 *
 * Kept: the accepted types, the size, the path and name, the flag, the
 * overwrite. Corrected, as for the CD banner (Phase 1): OLD renamed the
 * uploaded bytes, so a gif or png sat under a `.jpg` name. A JPEG is stored
 * byte for byte; a GIF or PNG is decoded and re-encoded as a real JPEG (GD;
 * JPEG has no transparency, so it is flattened onto white). The file is
 * written beside the target and renamed into place, so a failed conversion
 * never replaces the current icon.
 */
final class CdGroupIconFiles
{
    public const MAX_BYTES = 10000;

    /** Refused before decoding, so a tiny file cannot expand into a huge bitmap. */
    public const MAX_PIXELS = 4000 * 4000;

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function upload(AdminUser $actor, W2acdGroup $group, UploadedFile $file, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'w2acd.managecds', $group);

        $info = @getimagesize((string) $file->getRealPath());
        $type = is_array($info) ? (int) $info[2] : 0;
        if (! in_array($type, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new InvalidArgumentException('not an image');
        }
        if ((int) $info[0] * (int) $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('too large');
        }

        DB::connection('main')->transaction(function () use ($actor, $group, $file, $type, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_w2acd_groups')->where('id', $group->getKey())->lockForUpdate()->first(['id', 'icon']);
            abort_if($row === null, 404);

            $target = CdGroupIcon::absolutePath((int) $row->id);
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create {$directory}");
            }

            $temporary = $directory.'/.'.basename($target).'.'.bin2hex(random_bytes(6));
            try {
                $this->writeJpeg((string) $file->getRealPath(), $type, $temporary);
                if (! @rename($temporary, $target)) {
                    throw new RuntimeException("Cannot write {$target}");
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }

            $main->table('nuke_w2acd_groups')->where('id', $row->id)->update(['icon' => 1]);

            $this->audit->record($actor, 'upload_group_icon', 'w2acd', 'cd_group', (int) $row->id, [
                'path' => CdGroupIcon::path((int) $row->id),
                'bytes' => (int) filesize($target),
                'source_type' => image_type_to_mime_type($type),
                'was' => (int) $row->icon,
            ], $ip);
        });
    }

    private function writeJpeg(string $source, int $type, string $destination): void
    {
        if ($type === IMAGETYPE_JPEG) {
            if (! @copy($source, $destination)) {
                throw new RuntimeException('Cannot store the icon');
            }

            return;
        }

        $image = $type === IMAGETYPE_GIF ? @imagecreatefromgif($source) : @imagecreatefrompng($source);
        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('not an image');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        if (! @imagejpeg($canvas, $destination, 90)) {
            throw new RuntimeException('Cannot store the icon');
        }
    }
}
