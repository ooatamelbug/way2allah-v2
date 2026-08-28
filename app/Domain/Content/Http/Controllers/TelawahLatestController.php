<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `telawah/more.php` (`recite-news.htm`, `.htaccess:332`,
 * `op=MoreTelawah`) — Repair Batch 1 (decision-log #52), Sitewide Internal
 * 404 Audit finding #1, classified `MISSING_MIGRATION_ROUTE`. The only
 * real legacy generator is `home_functions.php:282`'s "المزيد .." link in
 * the homepage's `list_latest_telawahs()` widget — already reproduced
 * unchanged in `home.blade.php` (this task does not touch that widget).
 *
 * `.htaccess:332` routes through the missing `new_modules.php` dispatcher
 * (confirmed absent from both this codebase and real production,
 * decision-log #44) — same evidentiary shape as `more-fatawa.htm`'s own
 * `op=more_fatawa` (`FatwaLatestController`'s docblock): the real target
 * file (`telawah/more.php`) is fully present and readable, so the
 * dispatcher's own absence isn't a blocker to recovering real behavior.
 *
 * No `.htaccess` page parameter exists for this URL — legacy's own query
 * is a flat `LIMIT 24`, not paginated (same "no pagination" shape as
 * `more-fatawa.htm`).
 */
class TelawahLatestController
{
    /**
     * `more.php:10`'s real query (`LIMIT 24`, plain `t1,t2 WHERE
     * t1.group_id=t2.id` inner join) is reused via
     * `ContentListingService::homeLatestTelawahs(24)` — the exact same
     * query the homepage's own "latest telawahs" widget already uses at
     * `LIMIT 7`, extended (not duplicated) to select the 2 extra columns
     * (`hits`, the group's own `id`) this page's per-row markup needs
     * that the homepage widget doesn't. **One confirmed, deliberate,
     * pre-existing divergence from legacy, not introduced by this
     * repair:** `homeLatestTelawahs()` already used a `LEFT JOIN` where
     * `more.php:10` itself uses a plain (inner) join — that choice was
     * made when the homepage widget was originally built, not
     * re-litigated here; reusing it as-is is preferred over duplicating
     * the query with a different join type for one caller.
     *
     * `most_downloaded_list()` (`telawah/functions.php:309-329`, the
     * page's only sidebar box — `most_recent_list()` is defined in the
     * same file but never called by `more.php`, so it is correctly not
     * reproduced here) is already reused unchanged via
     * `ContentSidebarWidget::telawahMostDownloaded()` (`hits DESC`,
     * `LIMIT 10`) — the exact same query, already built and tested for
     * an unrelated caller.
     */
    public function index(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $telawahs = $listing->homeLatestTelawahs(24);
        $mostDownloaded = $sidebar->telawahMostDownloaded();

        return view('telawah.latest', compact('telawahs', 'mostDownloaded'));
    }
}
