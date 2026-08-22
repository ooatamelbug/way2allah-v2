<?php

namespace App\Domain\Content\Support;

/**
 * `fatawa/functions.php:753-782`'s `ArabicDateConvert($your_date, $year=true)`
 * — ported verbatim (weekday + day + Arabic month name + year, Eastern
 * Arabic numeral substitution). Promoted here from `FatwaDayController`'s
 * former private copy: that class's own docblock explicitly deferred
 * promotion "without a second evidenced consumer" — `FatwaQuestionController
 * ::showAll()` (`answer2.php`'s `ArabicDateConvert($question->date_of_fatwa)`
 * / `ArabicDateConvert($question->db_insertion_date)`, both called with the
 * default `$year=true`) is that second consumer.
 *
 * **Confirmed legacy quirk, reproduced not fixed:** `db_insertion_date` is
 * a raw Unix-timestamp int column (`FatwaQuestion`'s own docblock), but
 * `ArabicDateConvert()` always runs its argument through `strtotime()`
 * expecting a date *string*. `strtotime((string) $unixTimestamp)` does not
 * parse a bare epoch-seconds string, PHP resolves the failure to `0`, so
 * legacy renders `db_insertion_date` as "الخميس ٠١ يناير ١٩٧٠م" for every
 * row — never a genuine date. Preserved exactly (accepting `int|string`
 * and always going through `strtotime()`), not special-cased into a
 * correct timestamp-to-date conversion.
 */
class ArabicDateConverter
{
    private const MONTHS = [
        'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل',
        'May' => 'مايو', 'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس',
        'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر',
    ];

    private const DAYS = [
        'Sat' => 'السبت', 'Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء',
        'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة',
    ];

    public static function convert(int|string $date, bool $withYear = true): string
    {
        $date = (string) $date;

        if ($date === '0000-00-00' || $date === '') {
            return 'غير معلوم';
        }

        $timestamp = strtotime($date);
        $arMonth = self::MONTHS[date('M', $timestamp)] ?? '';
        $arDay = self::DAYS[date('D', $timestamp)] ?? '';

        // functions.php:774 formats the year as 'Y ' (trailing space in the
        // format string itself) before appending 'م' when $year=true — a
        // real space-before-م that's easy to lose when porting; confirmed
        // against a fresh live fetch ("...٢٠٢٣ م", not "...٢٠٢٣م").
        $current = $withYear
            ? "{$arDay} ".date('d', $timestamp)." {$arMonth} ".date('Y', $timestamp).' م'
            : "{$arDay} ".date('d', $timestamp)." {$arMonth} ".date('Y', $timestamp);

        $current = str_replace(['pm', 'am'], ['م', 'ص'], $current);

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            $current
        );
    }
}
