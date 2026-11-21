<?php

namespace App\Domain\Content\Support;

use App\Support\Media\ArchiveOrgLink;

/**
 * B2 / F07-A (owner-approved 2026-11-16, decision-log #147) — two network-free facts about a Variety (Anasheed) item's
 * or mirror's stored `link`, shared by the player, the public download buttons and the AdminCP save warning.
 *
 * - **playable shape** (`isPlayable()`): an absolute `http://` / `https://` URL with a host, after trimming the
 *   surrounding whitespace a browser ignores in `src`. Nothing else is judged: file names (`21.mp4_x264.mp4`,
 *   `x.mp3.mp3`), percent-encoded or Arabic paths and query strings all pass. Fails: empty, no scheme (bare file
 *   names, concatenations such as `a_x264.mp4b_x264.mp4`), no host (`/download/…`, `/cartoon/…`, `http:///x`), and
 *   anything before the scheme (`رhttp://…`, a title pasted into the field).
 * - **downloadable** (`isDownloadable()`): exactly what `/var-download-{id}` and `/var-mirror-{id}-{m}` do —
 *   `redirect(str_replace('http:', 'https:', ArchiveOrgLink::normalize($link)))` — leaves this site for an absolute
 *   URL. Laravel's URL generator treats anything it does not consider a valid URL (empty, relative, or with a LEADING
 *   space on a non-archive.org host) as a path on THIS site, i.e. a 404 or, for an empty link, the home page.
 */
final class VarietyMediaLink
{
    /** Session flash key of the AdminCP save warning (rendered by the admin layout and the public contextual flash). */
    public const ADMIN_WARNING_FLASH = 'admin_link_warning';

    private const ABSOLUTE = '~^https?://[^\s/?#]+~i';

    public static function isPlayable(?string $link): bool
    {
        return is_string($link) && preg_match(self::ABSOLUTE, trim($link)) === 1;
    }

    public static function isDownloadable(?string $link): bool
    {
        $target = str_replace('http:', 'https:', ArchiveOrgLink::normalize((string) $link));

        return app('url')->isValidUrl($target) && preg_match(self::ABSOLUTE, $target) === 1;
    }

    /** The AdminCP warning for a saved link, or null when the link has a playable shape (warning only; never blocks). */
    public static function adminWarning(?string $link, string $subject): ?string
    {
        if (self::isPlayable($link)) {
            return null;
        }

        return "تنبيه: وصلة {$subject} المحفوظة ليست رابطاً كاملاً يبدأ بـ http:// أو https:// ويتضمن اسم الموقع، "
            .'لذلك لن تعمل المشاهدة أو التحميل للزوار. تم الحفظ دون أي تعديل على الوصلة؛ يرجى مراجعتها.';
    }
}
