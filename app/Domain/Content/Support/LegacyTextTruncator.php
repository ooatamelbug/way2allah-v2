<?php

namespace App\Domain\Content\Support;

/**
 * G-02 (Homepage Migration Blueprint): the homepage's sections use FOUR
 * genuinely different, non-interchangeable truncation conventions — kept
 * as 3 distinct small methods here (rather than one unified "truncate"
 * helper) specifically so none of them get silently merged/simplified:
 *
 * - `words()` — `functions.php:1208-1229`'s `substrwords()`. Word-boundary
 *   truncation: walks whole words until the next one would exceed
 *   `$maxChars` (byte length), never splitting mid-word. Used by
 *   `list_latest_videos()` (90) and `get_latest_dump_files()` (65).
 * - `chars()` — `home_functions.php`'s plain `strlen()`/`substr()`
 *   character-count checks (NOT word-boundary): `list_latest_fatawas()`
 *   (110, ellipsis `'..'`) and `list_latest_telawahs()` (60, ellipsis
 *   `' ...'`) — same shape, different threshold/ellipsis per call site.
 * - `mixedMultibyte()` — `list_latest_audios()`'s own inconsistency,
 *   preserved as found: the *check* is `strlen($title) <= 40` (byte
 *   length) but the *truncation*, if it fires, is `mb_substr($title, 0,
 *   25, 'utf-8')` (25 UTF-8 characters, not 25 bytes) — a real mismatch
 *   between the two, not unified into one consistent measure here.
 */
class LegacyTextTruncator
{
    public static function words(string $text, int $maxChars, string $end = '...'): string
    {
        if (strlen($text) <= $maxChars && $text !== '') {
            return $text;
        }

        $words = preg_split('/\s/', $text) ?: [];
        $output = '';
        $i = 0;
        while (true) {
            $length = strlen($output) + strlen($words[$i] ?? '');
            if ($length > $maxChars) {
                break;
            }
            $output .= ' '.($words[$i] ?? '');
            $i++;
            if (! array_key_exists($i, $words)) {
                break;
            }
        }

        return $output.$end;
    }

    public static function chars(string $text, int $maxChars, string $end): string
    {
        return strlen($text) > $maxChars ? substr($text, 0, $maxChars).$end : $text;
    }

    public static function mixedMultibyte(string $text, int $strlenThreshold, int $mbChars, string $end = ' ...'): string
    {
        return strlen($text) <= $strlenThreshold ? $text : mb_substr($text, 0, $mbChars, 'utf-8').$end;
    }
}
