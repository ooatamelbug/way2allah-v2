<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/fatwa-today.php` — Roadmap task 6.1, increment 3.
 *
 * **`.htaccess:281,283` (`fatwa-today.htm`/`fatwa-today-{page}.htm`, both
 * `op=day&page=...`) are confirmed implemented by this file** — its own
 * `$_GET['date']` read (defaulting to today) and `page`-based pagination
 * match exactly.
 *
 * **`.htaccess:285`'s `fatwa-date-{d}-{m}-{y}-{page}.htm` (`op=day&d=$1&m=$2&y=$3&page=$4`)
 * is deliberately NOT registered here — flagged, not invented.** Re-read
 * of `fatwa-today.php` in full confirms it reads only a single
 * `$_GET['date']` string, never `$_GET['d']`/`['m']`/`['y']` separately.
 * No file among the 16 read for this module consumes those three
 * parameters. This is the same category of finding as `fatawa-play-*`/
 * `fatawa-brokenlink-*`/`auther-all-fatawa-*` — a real `.htaccess` rule
 * with no confirmed implementing code — and is left unimplemented on the
 * same basis, not assumed to share this controller's logic. The
 * client-side calendar widget that generates these URLs (`fatwa-today.php`'s
 * inline `<script>`, `have_sub()`-free, ~180 lines of month-grid
 * rendering) is not reproduced either, for the same reason plus scope
 * discipline — a full interactive calendar is a presentation feature, not
 * confirmed page-load behavior, and re-implementing it was not required
 * to preserve this page's actual data behavior.
 */
class FatwaDayController
{
    /**
     * `fatwa-today.php:8-16` — `$date` defaults to today, otherwise
     * normalized via `date('Y-m-d', strtotime($date))`. This controller
     * only receives a `page` (from the two confirmed routes above), never
     * a raw `date` query value — legacy's own `$_GET['date']` path is not
     * reachable from either confirmed `.htaccess` rule (neither passes a
     * `date` parameter), so only the "today" default is reproduced here.
     */
    public function index(int $page, ContentListingService $listing): View
    {
        $date = now()->format('Y-m-d');

        $featured = $listing->fatwaRandomFeatured();
        $questions = $listing->fatwaQuestionsByDate($date, $page);
        $displayDate = $this->arabicDateConvert($date);

        return view('fatawa.day', compact('featured', 'questions', 'displayDate'));
    }

    /**
     * `fatawa/functions.php:753-782` `ArabicDateConvert($your_date, $year)`
     * — ported verbatim (weekday + day + Arabic month name + year, Eastern
     * Arabic numeral substitution). No equivalent existed anywhere in the
     * Laravel app yet; kept private/local here since this is its only
     * confirmed caller in the module scope covered so far, not promoted
     * to a shared helper without a second evidenced consumer.
     */
    private function arabicDateConvert(string $date, bool $withYear = false): string
    {
        if ($date === '0000-00-00' || $date === '') {
            return 'غير معلوم';
        }

        $months = [
            'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس', 'Apr' => 'أبريل',
            'May' => 'مايو', 'Jun' => 'يونيو', 'Jul' => 'يوليو', 'Aug' => 'أغسطس',
            'Sep' => 'سبتمبر', 'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر',
        ];
        $days = [
            'Sat' => 'السبت', 'Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء',
            'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة',
        ];

        $timestamp = strtotime($date);
        $arMonth = $months[date('M', $timestamp)] ?? '';
        $arDay = $days[date('D', $timestamp)] ?? '';

        $current = $withYear
            ? "{$arDay} ".date('d', $timestamp)." {$arMonth} ".date('Y', $timestamp).'م'
            : "{$arDay} ".date('d', $timestamp)." {$arMonth} ".date('Y', $timestamp);

        $current = str_replace(['pm', 'am'], ['م', 'ص'], $current);

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            $current
        );
    }
}
