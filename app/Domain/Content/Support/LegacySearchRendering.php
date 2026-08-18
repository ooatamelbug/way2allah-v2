<?php

namespace App\Domain\Content\Support;

/**
 * G-05 (Migration Gap Register): two small, narrow, single-purpose
 * `advanced-search/index.php` rendering helpers — same "small standalone
 * utility, no premature abstraction" precedent as `LegacyTextTruncator`
 * (G-02) and `MediaPathResolver`.
 */
class LegacySearchRendering
{
    /**
     * `functions.php:45-56`'s `title_sub($title, $keywords)` — wraps each
     * space-split keyword (unconditionally, including any empty segment a
     * double-space would produce — `str_replace('', ..., $subject)` is a
     * documented PHP no-op, matching legacy's own unguarded loop exactly)
     * in `<sub class="red_sub">`. Escapes the title and each keyword via
     * `e()` BEFORE wrapping — legacy performs no escaping at all here,
     * but this project prioritizes safe output (this codebase's own
     * standing rule) over a byte-for-byte unescaped port; the escaping
     * happens once, before the real `<sub>` markup is added, so no
     * double-encoding occurs and the highlighting behavior itself is
     * unchanged.
     */
    public static function highlight(string $title, string $keywords): string
    {
        $title = e($title);

        foreach (explode(' ', $keywords) as $word) {
            $safeWord = e($word);
            $title = str_replace($safeWord, '<sub class="red_sub">'.$safeWord.'</sub>', $title);
        }

        return $title;
    }

    /**
     * `functions.php:57-69`'s `Graphic($datetime)` — a cascading-overwrite
     * "new" badge: each of the 3 time-window checks, if true, OVERWRITES
     * the previous result, so the most recent (narrowest) window that
     * matches wins — reproduced with the same cascade, not an early
     * return. Returns `''` (not `null`) when none match, matching
     * legacy's own effective empty-string behavior for items older than
     * a week.
     */
    public static function newBadge(?int $timestamp): string
    {
        if ($timestamp === null) {
            return '';
        }

        $now = time();
        $badge = '';

        if ($timestamp >= $now - 604800) {
            $badge = '<img border="0" width="24" height="11" alt="اضيفت خلال هذا الاسبوع" src="/images/new_7.gif">';
        }

        if ($timestamp >= $now - 259200) {
            $badge = '<img border="0" width="24" height="11" alt="اضيفت في الثلاثة أيام الأخيرة" src="/images/new_3.gif">';
        }

        if ($timestamp >= $now - 86400) {
            $badge = '<img border="0" width="24" height="11" alt="اضيفت اليوم" src="/images/new_1.gif">';
        }

        return $badge;
    }
}
