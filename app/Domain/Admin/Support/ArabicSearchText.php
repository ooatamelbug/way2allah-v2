<?php

namespace App\Domain\Admin\Support;

/**
 * Arabic spelling-insensitive matching for admin searches (multi-author creation, 2026-11-25).
 *
 * The author columns are `utf8(mb3)_unicode_ci`, which already ignores some diacritics but treats أ / ا, ة / ه,
 * ى / ي and tatweel as different letters, so «احمد» would not find «أحمد». The same folding is applied to the
 * search text (in PHP) and to the column (inside the SQL expression only). Stored values are never changed.
 *
 * Folded: Alef variants أ إ آ ٱ → ا; Alef maqsura ى and Farsi ya ی → ي; Ta marbuta ة → ه; tatweel and the
 * harakat (fathatan … sukun, superscript alef) removed. Hamza on waw / ya (ؤ ئ) is deliberately left as is.
 */
final class ArabicSearchText
{
    /** @var array<string, string> */
    private const FOLD = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ی' => 'ي',
        'ة' => 'ه',
        'ـ' => '',
        "\u{064B}" => '', "\u{064C}" => '', "\u{064D}" => '', "\u{064E}" => '', "\u{064F}" => '',
        "\u{0650}" => '', "\u{0651}" => '', "\u{0652}" => '', "\u{0670}" => '',
    ];

    /** The `LIKE … ESCAPE` character: portable to MySQL, MariaDB and SQLite (a backslash is not). */
    public const LIKE_ESCAPE = '!';

    public static function normalize(?string $text): string
    {
        $folded = strtr((string) $text, self::FOLD);

        return trim((string) preg_replace('/\s+/u', ' ', $folded));
    }

    /**
     * The same folding as SQL, for a column that may be NULL.
     *
     * @return array{0: string, 1: list<string>} the expression and its bindings, in order
     */
    public static function sqlExpression(string $column): array
    {
        $expression = "COALESCE({$column}, '')";
        $bindings = [];

        foreach (self::FOLD as $from => $to) {
            $expression = "REPLACE({$expression}, ?, ?)";
            $bindings[] = $from;
            $bindings[] = $to;
        }

        return [$expression, $bindings];
    }

    /** A `%…%` pattern for already-normalized text, with LIKE's own wildcards taken literally. */
    public static function containsPattern(string $normalized): string
    {
        $e = self::LIKE_ESCAPE;

        return '%'.str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $normalized).'%';
    }
}
