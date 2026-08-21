<?php

namespace App\Domain\Content\Services;

/**
 * Minimum-confirmed-contract replacement for legacy's `thumbnails.php`
 * (TimThumb 2.8.14, `legacy-project/thumbnails.php`, read in full to
 * derive every value below). Reproduces only the resize/crop algorithm
 * and security posture actually exercised by already-emitted
 * `/thumbnails.php?...` URLs (HomeController::anasheedThumb/albumThumb,
 * ContentSidebarWidget's anasheed sidebar fallback, gallery/index+show,
 * w2acd/index+show) — not the rest of TimThumb.
 *
 * Deliberately NOT reproduced (no confirmed caller needs them, and some
 * are the exact TimThumb behaviors this migration should not carry
 * forward): remote/`webshot` fetching, `zc=2`/`zc=3` letterbox modes,
 * `f`/`s`/`cc`/`ct` (filters, sharpen, canvas colour/transparency
 * overrides), disk cache, OptiPNG/PNGCrush post-processing, the
 * WordPress-derived palette-reduction step, and TimThumb's own directory-
 * traversal handling (replaced below with realpath-verified containment
 * inside an explicit allow-list instead of TimThumb's `stripos` prefix
 * check).
 */
class ThumbnailService
{
    /** The only two src roots any confirmed caller uses — both are public/ symlinks to legacy-project's asset dirs. */
    private const APPROVED_ROOTS = ['images', 'media'];

    /** TimThumb's MAX_WIDTH / MAX_HEIGHT (thumbnails.php:50-51). */
    private const MAX_DIMENSION = 1500;

    /** TimThumb's MAX_FILE_SIZE (thumbnails.php:41) — 10 MiB. */
    private const MAX_SOURCE_BYTES = 10_485_760;

    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * @return array{binary: string, mime: string}
     */
    public function render(string $src, ?int $w, ?int $h, int $zc, int $q): array
    {
        $path = $this->resolvePath($src);

        if (filesize($path) > self::MAX_SOURCE_BYTES) {
            throw new ThumbnailRejectedException('Source file exceeds the maximum allowed size.');
        }

        $info = @getimagesize($path);
        if ($info === false || ! in_array($info['mime'], self::SUPPORTED_MIME_TYPES, true)) {
            throw new ThumbnailRejectedException('Not a supported image type (jpeg/png/gif only).');
        }

        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];

        // Request-shape validation happens before the GD check below so a
        // malformed/insufficient request is always a 400 regardless of
        // whether this runtime has ext-gd — GD unavailability is a server
        // condition, not something a client request can be "wrong" about.
        [$targetWidth, $targetHeight] = $this->targetDimensions($w, $h, $width, $height);

        if (! extension_loaded('gd')) {
            throw new ThumbnailGdUnavailableException('ext-gd is required to resize images.');
        }

        $image = $this->openImage($mime, $path);
        if ($image === false) {
            throw new ThumbnailRejectedException('Unable to open image.');
        }

        $canvas = $this->makeCanvas($targetWidth, $targetHeight, $mime);

        if ($zc > 0) {
            $this->copyCropFill($canvas, $image, $width, $height, $targetWidth, $targetHeight);
        } else {
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        }

        $binary = $this->encode($canvas, $mime, $q);

        imagedestroy($canvas);
        imagedestroy($image);

        return ['binary' => $binary, 'mime' => $mime];
    }

    /**
     * Resolves `src` to a real, on-disk path guaranteed to sit inside one
     * of APPROVED_ROOTS. TimThumb (thumbnails.php:858-928) resolves
     * against DOCUMENT_ROOT with a `stripos($real, $docRoot) === 0` prefix
     * check; that's the exact class of bug realpath+DIRECTORY_SEPARATOR
     * containment below is designed to avoid (a sibling directory like
     * `images-evil/` would pass TimThumb's bare prefix check).
     */
    public function resolvePath(string $src): string
    {
        if (strlen($src) <= 3) {
            throw new ThumbnailRejectedException('No image specified.');
        }

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $src) === 1) {
            throw new ThumbnailRejectedException('Remote URLs are not supported.');
        }

        if (str_starts_with($src, '//')) {
            throw new ThumbnailRejectedException('Protocol-relative URLs are not supported.');
        }

        // TimThumb itself does ltrim($src, '/') before resolving (thumbnails.php:859)
        // — the one confirmed caller emitting a leading slash (w2acd/show.blade.php's
        // `src=/images/cds_image2/...`) relies on this being a normal web-root-relative
        // path, not a rejected "absolute filesystem path".
        $relative = ltrim($src, '/');

        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '..')) {
            throw new ThumbnailRejectedException('Invalid or unsafe image path.');
        }

        $root = null;
        foreach (self::APPROVED_ROOTS as $candidate) {
            if ($relative === $candidate || str_starts_with($relative, $candidate.'/')) {
                $root = $candidate;
                break;
            }
        }

        if ($root === null) {
            throw new ThumbnailRejectedException('Image path is outside the approved asset roots.');
        }

        $approvedRealRoot = realpath(public_path($root));
        if ($approvedRealRoot === false) {
            throw new ThumbnailRejectedException('Approved asset root is not available.');
        }

        $real = realpath(public_path($relative));

        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new ThumbnailRejectedException('Could not find the specified image.');
        }

        if (! str_starts_with($real, $approvedRealRoot.DIRECTORY_SEPARATOR)) {
            throw new ThumbnailRejectedException('Image path is outside the approved asset roots.');
        }

        return $real;
    }

    /**
     * TimThumb's own w/h/default/clamp/proportional logic
     * (thumbnails.php:528-568), minus the DEFAULT_WIDTH/DEFAULT_HEIGHT=100
     * fallback for when both are omitted — no confirmed caller ever omits
     * both, so per this task's scope that case is rejected rather than
     * invented.
     *
     * @return array{0: int, 1: int}
     */
    private function targetDimensions(?int $w, ?int $h, int $sourceWidth, int $sourceHeight): array
    {
        $w = $w ?? 0;
        $h = $h ?? 0;

        if ($w < 0 || $h < 0) {
            throw new ThumbnailRejectedException('Width/height must not be negative.');
        }

        if ($w === 0 && $h === 0) {
            throw new ThumbnailRejectedException('At least one of w or h is required.');
        }

        $w = min($w, self::MAX_DIMENSION);
        $h = min($h, self::MAX_DIMENSION);

        if ($w > 0 && $h === 0) {
            $h = (int) floor($sourceHeight * ($w / $sourceWidth));
        } elseif ($h > 0 && $w === 0) {
            $w = (int) floor($sourceWidth * ($h / $sourceHeight));
        }

        if ($w <= 0 || $h <= 0) {
            throw new ThumbnailRejectedException('Computed target dimensions are invalid.');
        }

        return [$w, $h];
    }

    /** TimThumb's openImage() (thumbnails.php:1105-1126). */
    private function openImage(string $mime, string $path): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => tap(imagecreatefrompng($path), function ($image) {
                if ($image !== false) {
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
            }),
            'image/gif' => imagecreatefromgif($path),
            default => false,
        };
    }

    /** TimThumb's canvas setup (thumbnails.php:584-632), `cc`/`ct` params dropped — no confirmed caller uses them, so the defaults (white background, PNG transparent) always apply. */
    private function makeCanvas(int $width, int $height, string $mime): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);

        $color = $mime === 'image/png'
            ? imagecolorallocatealpha($canvas, 255, 255, 255, 127) // fully transparent — TimThumb's default PNG_IS_TRANSPARENT=false + ct=true branch
            : imagecolorallocatealpha($canvas, 255, 255, 255, 0);  // opaque white — TimThumb's DEFAULT_CC

        imagefill($canvas, 0, 0, $color);
        imagesavealpha($canvas, true);

        return $canvas;
    }

    /**
     * `zc=1` (default): crop-to-fill. TimThumb's centered-crop math
     * (thumbnails.php:634-672) with the `a`/align param dropped — no
     * confirmed caller sets it, so it's always centered (TimThumb's own
     * default, `a=c`, which is a no-op against this math).
     */
    private function copyCropFill(\GdImage $canvas, \GdImage $image, int $width, int $height, int $newWidth, int $newHeight): void
    {
        $cmpX = $width / $newWidth;
        $cmpY = $height / $newHeight;

        $srcX = 0;
        $srcY = 0;
        $srcW = $width;
        $srcH = $height;

        if ($cmpX > $cmpY) {
            $srcW = (int) round($width / $cmpX * $cmpY);
            $srcX = (int) round(($width - $srcW) / 2);
        } elseif ($cmpY > $cmpX) {
            $srcH = (int) round($height / $cmpY * $cmpX);
            $srcY = (int) round(($height - $srcH) / 2);
        }

        imagecopyresampled($canvas, $image, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $srcW, $srcH);
    }

    /**
     * TimThumb's output encode step (thumbnails.php:749-762), quality
     * clamped per this task's "clamp to a safe valid range" requirement.
     *
     * @param  'image/jpeg'|'image/png'|'image/gif'  $mime
     */
    private function encode(\GdImage $canvas, string $mime, int $quality): string
    {
        $quality = max(0, min(100, $quality));

        ob_start();

        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, null, $quality),
            'image/png' => imagepng($canvas, null, (int) max(0, min(9, floor($quality * 0.09)))),
            'image/gif' => imagegif($canvas),
        };

        return (string) ob_get_clean();
    }
}
