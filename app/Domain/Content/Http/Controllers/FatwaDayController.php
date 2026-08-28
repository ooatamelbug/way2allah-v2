<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Support\ArabicDateConverter;
use Illuminate\Contracts\View\View;

/**
 * Replaces `fatawa/fatwa-today.php` — Roadmap task 6.1, increment 3.
 *
 * **`.htaccess:281,283` (`fatwa-today.htm`/`fatwa-today-{page}.htm`, both
 * `op=day&page=...`) are confirmed implemented by this file** — its own
 * `$_GET['date']` read (defaulting to today) and `page`-based pagination
 * match exactly.
 *
 * **Fatwa Date Route Completion (decision-log #46) — `.htaccess:285`'s
 * `fatwa-date-{d}-{m}-{y}-{page}.htm` (`op=day&d=$1&m=$2&y=$3&page=$4`) is
 * NOW implemented, superseding the prior "deliberately NOT registered"
 * decision recorded here.** `fatwa-today.php` was re-confirmed (again) to
 * only ever read `$_GET['date']`, never `$_GET['d']`/`['m']`/`['y']`
 * directly — that fact hasn't changed. What changed is the evidentiary
 * basis for implementing it anyway:
 *
 * 1. Both `.htaccess` rules dispatch to the exact same operation,
 *    `new_modules.php?name=Fatwa&op=day&...` — only the parameter SOURCE
 *    differs (a `date` string vs. 3 separate `d`/`m`/`y` segments), not
 *    the underlying feature.
 * 2. `fatwa-today.php`'s own inline calendar-widget `<script>` (already
 *    ported verbatim in `fatawa/day.blade.php` — real, unmodified legacy
 *    source, not invented here) generates exactly this URL shape
 *    (`/fatwa-date-{day}-{month}-{year}-1.htm`) when a calendar day is
 *    clicked — a genuine, real legacy *generator*, proving the intended
 *    target unambiguously: "show fatwas added on this specific day,"
 *    i.e. precisely what `get_all_questions_date($date)` already does.
 * 3. `$offset`/`$page` pagination state is ALSO never set anywhere in the
 *    16 `fatawa/` files (confirmed by a repo-wide grep for `$offset =` —
 *    zero results outside `english/`) — meaning per-request parameter
 *    normalization for this operation happened somewhere outside those
 *    16 files. `new_modules.php` is the only candidate site for it (every
 *    `op=day` rule routes through it), but it does not exist anywhere —
 *    not in this codebase's snapshot, not on real production (confirmed
 *    directly, decision-log #44). Its absence is a known, sitewide,
 *    already-documented fact (IF-026), not evidence this capability never
 *    worked.
 *
 * **Precision on what is and isn't proven, stated explicitly rather than
 * left implicit:** the `d`/`m`/`y`→`date` semantic *intent* and the exact
 * generated URL shape ARE source-backed (points 1–2 above, both directly
 * cited to real, still-present legacy source). The *actual mechanism*
 * `new_modules.php` used to normalize `d`/`m`/`y` into a queryable date is
 * NOT literally recoverable — that file does not exist anywhere to trace.
 * The normalization implemented below is therefore an **evidence-based
 * reconstruction** of that missing step (reusing `fatwa-today.php`'s own
 * proven `$_GET['date']` normalization algorithm as the most defensible
 * available basis), not a traced port of `new_modules.php`'s actual code.
 *
 * `d`/`m`/`y` are normalized into the same `Y-m-d` `$date` string
 * `fatwa-today.php` itself already uses, via the exact same
 * `strtotime()`+`date('Y-m-d', ...)` round-trip `fatwa-today.php:16`
 * already performs on its own `$_GET['date']` input — reproducing its
 * real permissive/rollover behavior (e.g. day=31, month=2 rolls to
 * March 3; a genuinely unparseable combination like month=13 falls back
 * to the Unix epoch, exactly matching `date('Y-m-d', false)`'s real
 * result of `1970-01-01` — verified directly against real PHP 8.3, not
 * assumed), not a newly-invented Laravel-style validation.
 */
class FatwaDayController
{
    /**
     * `fatwa-today.php:8-16` — `$date` defaults to today, otherwise
     * normalized via `date('Y-m-d', strtotime($date))`.
     */
    public function index(int $page, ContentListingService $listing): View
    {
        $date = now()->format('Y-m-d');
        $pageUrl = fn (int $requestedPage): string => $requestedPage === 1
            ? route('fatawa.day.today')
            : route('fatawa.day.today.paged', ['page' => $requestedPage]);

        return $this->render($date, $page, $listing, $pageUrl);
    }

    /**
     * `.htaccess:285` — `d`/`m`/`y`/`page` map positionally to the URL's
     * 4 segments in that exact order (`fatwa-date-{day}-{month}-{year}-{page}.htm`),
     * matching both the `.htaccess` capture-group order and the calendar
     * widget's own generated href (`day + '-' + month + '-' + year + '-1'`).
     */
    public function date(int $day, int $month, int $year, int $page, ContentListingService $listing): View
    {
        $date = $this->normalizeDate($day, $month, $year);
        $pageUrl = fn (int $requestedPage): string => route('fatawa.day.date', [
            'day' => $day, 'month' => $month, 'year' => $year, 'page' => $requestedPage,
        ]);

        return $this->render($date, $page, $listing, $pageUrl);
    }

    /**
     * `fatwa-today.php:16`'s own normalization, reproduced exactly for a
     * date built from 3 separate segments instead of one `date` string —
     * real, verified PHP 8.3 behavior: `strtotime('2026-2-31')` rolls
     * over to March 3 (permissive), `strtotime('2026-13-1')` returns
     * `false`, and `date('Y-m-d', false)` (legacy's own literal next
     * step, unguarded) resolves to `1970-01-01` — reproduced explicitly
     * here (`$timestamp === false ? 0 : $timestamp`) rather than relying
     * on PHP's implicit bool-to-int coercion, to keep this statically
     * typed without changing the resulting value.
     */
    private function normalizeDate(int $day, int $month, int $year): string
    {
        $timestamp = strtotime(sprintf('%d-%d-%d', $year, $month, $day));

        return date('Y-m-d', $timestamp === false ? 0 : $timestamp);
    }

    /**
     * @param  \Closure(int): string  $pageUrl
     */
    private function render(string $date, int $page, ContentListingService $listing, \Closure $pageUrl): View
    {
        $featured = $listing->fatwaRandomFeatured();
        $questions = $listing->fatwaQuestionsByDate($date, $page);
        // fatwa-today.php's own display call passes no second argument in
        // legacy either, but that op's real usage never needs the year
        // suffix — kept as the explicit `false` this controller already
        // relied on before the shared ArabicDateConverter existed.
        $displayDate = ArabicDateConverter::convert($date, false);

        return view('fatawa.day', compact('featured', 'questions', 'displayDate', 'date', 'pageUrl'));
    }
}
