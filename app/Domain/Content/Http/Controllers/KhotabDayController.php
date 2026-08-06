<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use Illuminate\Contracts\View\View;

/**
 * Replaces `khotab/day.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * IF-016's fix: page title uses the browsed date (mirroring the
 * breadcrumb text legacy already builds correctly), not a nonexistent
 * `$Author`.
 * IF-022's fix: the date actually comes from the route, not an always-
 * empty `$_POST['date']` — see that finding for why every dated legacy
 * URL was previously dead.
 *
 * `+1 day` end-of-range boundary computed as `$dayStart + 86400` rather
 * than legacy's `strtotime('+1 day' . $mydate)` (a concatenation without a
 * separating space) — an unambiguous, display-irrelevant simplification
 * of the exact same "next day" intent, not a behavior change.
 */
class KhotabDayController
{
    public function videoToday(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(true, $this->today(), $listing, $sidebar);
    }

    public function audioToday(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(false, $this->today(), $listing, $sidebar);
    }

    public function videoByDate(int $d, int $m, int $y, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(true, mktime(0, 0, 0, $m, $d, $y), $listing, $sidebar);
    }

    public function audioByDate(int $d, int $m, int $y, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(false, mktime(0, 0, 0, $m, $d, $y), $listing, $sidebar);
    }

    private function today(): int
    {
        return mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
    }

    private function render(bool $video, int $dayStart, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $dayEnd = $dayStart + 86400;

        $items = $listing->khotabItemsForDay($video, $dayStart, $dayEnd);
        $mostDownloaded = $sidebar->khotabMostDownloadedByVideoFlag($video);
        $mostRecent = $sidebar->khotabMostRecentByVideoFlag($video);

        return view('khotab.day', [
            'video' => $video,
            'date' => $dayStart,
            'items' => $items,
            'mostDownloaded' => $mostDownloaded,
            'mostRecent' => $mostRecent,
        ]);
    }
}
