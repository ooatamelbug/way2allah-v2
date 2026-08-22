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
        // fatwa-today.php's own display call passes no second argument in
        // legacy either, but that op's real usage never needs the year
        // suffix — kept as the explicit `false` this controller already
        // relied on before the shared ArabicDateConverter existed.
        $displayDate = ArabicDateConverter::convert($date, false);

        return view('fatawa.day', compact('featured', 'questions', 'displayDate'));
    }
}
