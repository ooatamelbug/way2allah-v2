<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use App\Domain\Content\Support\KhotabCover;
use GdImage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * F10 / B5 — upload, replace and remove a Khotab VIDEO item's custom cover (`KhotabCover`), owner-approved
 * 2026-11-16. Permission `khotab.edititem` (no new key). No database column is written: the only database write is
 * the `admin_audit_log` row. `frame`, `gif`, `build_thumb`, `thumbnail` and the Archive.org files are never touched.
 *
 * Upload: the decoded content decides (`getimagesize()` type JPEG / PNG / WebP, then a full GD decode), within
 * `MIN_*` / `MAX_SOURCE_*` bounds (checked BEFORE decoding, against decompression bombs). The image is re-encoded to
 * JPEG (metadata and any appended payload dropped; transparency flattened on white), downscaled to fit
 * `MAX_WIDTH` × `MAX_HEIGHT` keeping its aspect ratio, never upscaled.
 *
 * Failure safety, under one per-item lock (`khotab-cover:{id}`):
 * 1. the new JPEG is written to a temporary file in the target directory;
 * 2. an existing cover is moved aside (`.rollback-*`) — never deleted yet;
 * 3. the temporary file is renamed into place (atomic on one filesystem);
 * 4. the audit row is written in a transaction;
 * 5. only then is the rollback copy deleted. Any failure in 2–4 moves the previous cover back (or removes the new
 *    one), so a failed replacement never destroys the valid cover. Temporary files are always cleaned up; no
 *    history is kept.
 *
 * Removal moves the cover aside, audits, then deletes it (restored if the audit fails). The Archive.org frame is a
 * different path and is never involved.
 */
final class KhotabItemCoverAction
{
    public const PERMISSION = 'khotab.edititem';

    public const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    /** Smallest accepted source: the smallest Archive.org frames on record are 160 × 110. */
    public const MIN_WIDTH = 160;

    public const MIN_HEIGHT = 90;

    /** Decompression-bomb guard, checked from the header before any decode. */
    public const MAX_SOURCE_WIDTH = 6000;

    public const MAX_SOURCE_HEIGHT = 6000;

    public const MAX_SOURCE_PIXELS = 24_000_000;

    /** Stored size bound (largest consumer: the 1200-px social image; Archive.org frames are ≤ 512 px). */
    public const MAX_WIDTH = 1280;

    public const MAX_HEIGHT = 1280;

    public const JPEG_QUALITY = 85;

    private const TYPES = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @return array{replaced: bool, width: int, height: int, bytes: int}
     *
     * @throws ValidationException a non-video item, or an unacceptable image
     */
    public function upload(AdminUser $actor, KhotabItem $item, UploadedFile $file, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, self::PERMISSION, $item);
        $itemId = (int) $item->id;
        $this->assertVideo($itemId);

        $source = $this->inspect($file);

        return $this->locked($itemId, function () use ($actor, $itemId, $source, $ip) {
            $target = KhotabCover::absolutePath($itemId);
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('Cannot create the cover directory.');
            }

            $temporary = $directory.'/'.$itemId.'.jpg.upload-'.bin2hex(random_bytes(6));
            $rollback = null;
            try {
                [$width, $height] = $this->encode($source, $temporary);
                $bytes = (int) filesize($temporary);
                $sha256 = (string) hash_file('sha256', $temporary);

                $replaced = is_file($target);
                if ($replaced) {
                    $rollback = $directory.'/'.$itemId.'.jpg.rollback-'.bin2hex(random_bytes(6));
                    if (! @rename($target, $rollback)) {
                        throw new RuntimeException('Cannot set the current cover aside.');
                    }
                }
                if (! @rename($temporary, $target)) {
                    throw new RuntimeException('Cannot move the new cover into place.');
                }

                try {
                    DB::connection('main')->transaction(function () use ($actor, $itemId, $replaced, $width, $height, $bytes, $sha256, $source, $ip) {
                        abort_unless(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $itemId)->lockForUpdate()->exists(), 404);
                        $this->audit->record($actor, $replaced ? 'replace_cover' : 'upload_cover', 'khotab', 'item', $itemId, [
                            'path' => KhotabCover::relativePath($itemId),
                            'source_format' => $source['format'],
                            'source_size' => [$source['width'], $source['height']],
                            'stored_size' => [$width, $height],
                            'bytes' => $bytes,
                            'sha256' => $sha256,
                        ], $ip);
                    });
                } catch (Throwable $e) {
                    @unlink($target);
                    if ($rollback !== null) {
                        @rename($rollback, $target);
                        $rollback = null;
                    }
                    throw $e;
                }
            } catch (Throwable $e) {
                // A failure before the swap leaves the current cover in place; restore it if it was already set aside.
                if ($rollback !== null && ! is_file($target)) {
                    @rename($rollback, $target);
                    $rollback = null;
                }
                throw $e;
            } finally {
                @unlink($temporary);
                if ($rollback !== null && is_file($target)) {
                    @unlink($rollback);
                }
            }

            return ['replaced' => $replaced, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
        });
    }

    /** @return bool false when the item had no cover (nothing written, nothing recorded) */
    public function remove(AdminUser $actor, KhotabItem $item, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, self::PERMISSION, $item);
        $itemId = (int) $item->id;

        return $this->locked($itemId, function () use ($actor, $itemId, $ip) {
            $target = KhotabCover::absolutePath($itemId);
            if (! is_file($target)) {
                return false;
            }

            $aside = dirname($target).'/'.$itemId.'.jpg.removing-'.bin2hex(random_bytes(6));
            if (! @rename($target, $aside)) {
                throw new RuntimeException('Cannot remove the cover.');
            }

            try {
                DB::connection('main')->transaction(function () use ($actor, $itemId, $aside, $ip) {
                    abort_unless(DB::connection('main')->table('nuke_islamic_khotab')->where('id', $itemId)->lockForUpdate()->exists(), 404);
                    $this->audit->record($actor, 'remove_cover', 'khotab', 'item', $itemId, [
                        'path' => KhotabCover::relativePath($itemId),
                        'bytes' => (int) filesize($aside),
                        'sha256' => (string) hash_file('sha256', $aside),
                    ], $ip);
                });
            } catch (Throwable $e) {
                @rename($aside, $target);
                throw $e;
            }

            @unlink($aside);

            return true;
        });
    }

    /** Covers are for video items only (owner decision B). */
    private function assertVideo(int $itemId): void
    {
        $vedio = DB::connection('main')->table('nuke_islamic_khotab')->where('id', $itemId)->value('vedio');
        if ((int) $vedio !== 1) {
            throw ValidationException::withMessages(['cover' => 'الصورة المخصصة متاحة لمواد الفيديو فقط']);
        }
    }

    /**
     * The decoded content decides: header type and dimensions first (no decode), then the full decode.
     *
     * @return array{image: GdImage, format: string, width: int, height: int}
     */
    private function inspect(UploadedFile $file): array
    {
        $fail = fn (string $message) => ValidationException::withMessages(['cover' => $message]);
        $path = $file->getRealPath();
        if ($path === false || ! $file->isValid() || $file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw $fail('الملف غير صالح أو أكبر من 2 ميجابايت');
        }

        $info = @getimagesize($path);
        if (! is_array($info) || ! isset(self::TYPES[$info[2]])) {
            throw $fail('الملف ليس صورة JPEG أو PNG أو WebP صالحة');
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            throw $fail('أبعاد الصورة أصغر من المسموح ('.self::MIN_WIDTH.'×'.self::MIN_HEIGHT.')');
        }
        if ($width > self::MAX_SOURCE_WIDTH || $height > self::MAX_SOURCE_HEIGHT || $width * $height > self::MAX_SOURCE_PIXELS) {
            throw $fail('أبعاد الصورة أكبر من المسموح ('.self::MAX_SOURCE_WIDTH.'×'.self::MAX_SOURCE_HEIGHT.')');
        }

        $format = self::TYPES[$info[2]];
        $image = match ($format) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };
        if (! $image instanceof GdImage || imagesx($image) !== $width || imagesy($image) !== $height) {
            throw $fail('تعذرت قراءة الصورة (ملف تالف أو غير مدعوم)');
        }

        return ['image' => $image, 'format' => $format, 'width' => $width, 'height' => $height];
    }

    /**
     * Fit within MAX_WIDTH × MAX_HEIGHT (aspect kept, never upscaled), flatten on white, write a JPEG.
     *
     * @param  array{image: GdImage, format: string, width: int, height: int}  $source
     * @return array{0: int, 1: int}
     */
    private function encode(array $source, string $destination): array
    {
        $scale = min(1, self::MAX_WIDTH / $source['width'], self::MAX_HEIGHT / $source['height']);
        $width = max(1, (int) round($source['width'] * $scale));
        $height = max(1, (int) round($source['height'] * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Cannot allocate the cover image.');
        }
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source['image'], 0, 0, 0, 0, $width, $height, $source['width'], $source['height']);
        imageinterlace($canvas, true);

        if (! @imagejpeg($canvas, $destination, self::JPEG_QUALITY) || ! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Cannot write the cover image.');
        }

        return [$width, $height];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(int $itemId, callable $callback): mixed
    {
        try {
            return Cache::lock('khotab-cover:'.$itemId, 30)->block(max(0, (int) config('admin_khotab.cover_lock_wait', 10)), $callback);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['cover' => 'يجري تعديل صورة هذه المادة الآن، حاول مرة أخرى بعد لحظات']);
        }
    }
}
