<?php

namespace App\Domain\Content\Support;

/**
 * khotab-video-today.htm parity: `functions.php:398-443`'s `CoolShortDate($time)`
 * — used by `khotab/day.php:181`'s "جديد المواد" box (`topitems('time', ...)`
 * — the one call site on this page, verified against fresh live HTML:
 * `<small>بتاريخ: الخميس 23 يوليو 2026 مـ </small>`). Only the `$full_date=true`
 * branch is ported — no call site on this page ever passes `false`. A static
 * class method for the same reason as `LegacyDurationFormatter` — Blade views
 * render more than once per test-suite process, so a global `function`
 * declaration would fatal the second time.
 */
class LegacyShortDateFormatter
{
    private const DAYS = [
        'Fri' => 'الجمعة', 'Thu' => 'الخميس', 'Wed' => 'الأربعاء', 'Tue' => 'الثلاثاء',
        'Mon' => 'الأثنين', 'Sun' => 'الأحد', 'Sat' => 'السبت',
    ];

    private const MONTHS = [
        'JANUARY' => 'يناير', 'FEBRUARY' => 'فبراير', 'MARCH' => 'مارس', 'APRIL' => 'ابريل',
        'MAY' => 'مايو', 'JUNE' => 'يونيو', 'JULY' => 'يوليو', 'AUGUST' => 'اغسطس',
        'SEPTEMBER' => 'سبتمبر', 'OCTOBER' => 'اكتوبر', 'NOVEMBER' => 'نوفمبر', 'DECEMBER' => 'ديسمبر',
    ];

    public static function format(int $time): string
    {
        // Plain date(), matching every other khotab timestamp already rendered
        // in this codebase (e.g. date('Y-m-d', $item->time)) — not gmdate(),
        // which LegacyDurationFormatter uses for a duration/interval, a
        // different semantic case. Timezone behavior itself is untouched
        // (open finding, deliberately out of scope here).
        $day = self::DAYS[date('D', $time)];
        $month = self::MONTHS[strtoupper(date('F', $time))];

        return $day.' '.date('j', $time).' '.$month.' '.date('Y', $time).' مـ ';
    }
}
