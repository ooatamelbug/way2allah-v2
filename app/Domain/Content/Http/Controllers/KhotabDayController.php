<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Support\LegacyShortDateFormatter;
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
 *
 * Shared Page Chrome Parity Audit: `day.php:90-98`'s breadcrumb restored
 * (heading deliberately NOT — see the view's own comment). `$mydate`
 * (day.php:93-97) is `'اليوم - ' . CoolShortDate(time())` only on the
 * empty-`$date` legacy branch (i.e. `videoToday()`/`audioToday()`);
 * `videoByDate()`/`audioByDate()` use `CoolShortDate(strtotime($date))`
 * with no "اليوم - " prefix — reproduced via the `$isToday` flag, using
 * real `time()` (not `$dayStart`) for the today case, matching legacy's
 * own literal `CoolShortDate(time())` call exactly.
 */
class KhotabDayController
{
    public function videoToday(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(true, $this->today(), true, $listing, $sidebar);
    }

    public function audioToday(ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(false, $this->today(), true, $listing, $sidebar);
    }

    public function videoByDate(int $d, int $m, int $y, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(true, mktime(0, 0, 0, $m, $d, $y), false, $listing, $sidebar);
    }

    public function audioByDate(int $d, int $m, int $y, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        return $this->render(false, mktime(0, 0, 0, $m, $d, $y), false, $listing, $sidebar);
    }

    private function today(): int
    {
        return mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
    }

    private function render(bool $video, int $dayStart, bool $isToday, ContentListingService $listing, ContentSidebarWidget $sidebar): View
    {
        $dayEnd = $dayStart + 86400;

        $items = $listing->khotabItemsForDay($video, $dayStart, $dayEnd);
        $mostDownloaded = $sidebar->khotabMostDownloadedByVideoFlag($video);
        $mostRecent = $sidebar->khotabMostRecentByVideoFlag($video);

        $op = $video ? 'video' : 'audio';
        $mydate = $isToday
            ? 'اليوم - '.LegacyShortDateFormatter::format(time())
            : LegacyShortDateFormatter::format($dayStart);

        $breadcrumbTrail = [
            ['title' => $video ? 'المرئيات ' : 'الصوتيات ', 'url' => "/khotab-{$op}.htm"],
            ['title' => 'تقسيم المواد بالتاريخ'],
            ['title' => ' المواد المنشورة بتاريخ '.$mydate, 'url' => ''],
        ];

        return view('khotab.day', [
            'video' => $video,
            'date' => $dayStart,
            'items' => $items,
            'mostDownloaded' => $mostDownloaded,
            'mostRecent' => $mostRecent,
            'breadcrumbTrail' => $breadcrumbTrail,
        ]);
    }
}
