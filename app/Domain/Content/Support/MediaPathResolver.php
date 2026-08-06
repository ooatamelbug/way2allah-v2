<?php

namespace App\Domain\Content\Support;

/**
 * Reproduces the legacy `floor(id/1000)` media-path bucketing convention
 * exactly (Blueprint v1.0 §4/§10) — confirmed in functions.php's
 * `basefolder()` (line 190), `home_functions.php` (lines 30, 39, 372, 381),
 * and `topitems()` (functions.php lines 1046, 1055, 1121): media files live
 * at `media/<base-folder>/<floor(id/1000)>/<id>.<extension>`.
 *
 * Flagged in shared-core.md/00-database-schema.md as structurally
 * load-bearing — a bug here silently breaks thumbnail resolution for the
 * *entire* existing media library, not just new content. Kept as a small,
 * standalone, heavily-tested utility rather than a not-yet-built content
 * model's accessor (KhotabItem etc. don't exist until Wave 4) — each
 * content model wraps this in its own accessor once it exists.
 *
 * NOT resolved here — a genuine, previously undocumented legacy
 * inconsistency found while grounding this class in the real source: the
 * author-photo fallback bucket uses `$Item->thid` in home_functions.php
 * (lines 39-40) but `$item->author` in functions.php's topitems()
 * (line 1055) — two different id sources for what reads as the same
 * "author photo" concept. This class only provides the bucketing math and
 * path construction; which id feeds it for a given content type/context is
 * each content model's own decision in Wave 3/4, made once real data can
 * confirm which source is actually correct.
 */
class MediaPathResolver
{
    private const BUCKET_SIZE = 1000;

    public static function bucket(int $id): int
    {
        return intdiv($id, self::BUCKET_SIZE);
    }

    public static function path(string $baseFolder, int $id, string $extension): string
    {
        return sprintf(
            'media/%s/%d/%d.%s',
            trim($baseFolder, '/'),
            self::bucket($id),
            $id,
            ltrim($extension, '.'),
        );
    }
}
