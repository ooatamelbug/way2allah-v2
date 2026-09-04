<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Support\LegacyShortDateFormatter;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Replaces `khotab/day.php` — Roadmap task 4.1. Public-only, same scope
 * decision as `KhotabItemController`.
 *
 * IF-016 (superseded by the Title Gap Closure task, 2026-08-22): the
 * document `<title>` is day.php's plain, date-independent 'المرئيات '/
 * 'الصوتيات ' string (day.php:10-19,24-25) — set directly in
 * `khotab.day`'s own `@section('title', ...)` from the `$video` flag
 * already passed below, no controller change needed. IF-016's original
 * premise (page title "mirrors the breadcrumb text") conflated this with
 * day.php:100's separate, broken `title($Author->...)` call, which feeds
 * the visible `<h3>` heading, not this `<title>` tag — that heading's own
 * undefined-`$Author` bug is unrelated and stays correctly omitted (the
 * view's own comment).
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
    public function videoToday(Request $request, ContentListingService $listing, ContentSidebarWidget $sidebar): View|RedirectResponse
    {
        if ($redirect = $this->requestedDateRedirect($request, true)) {
            return $redirect;
        }

        return $this->render(true, $this->today(), true, $listing, $sidebar);
    }

    public function audioToday(Request $request, ContentListingService $listing, ContentSidebarWidget $sidebar): View|RedirectResponse
    {
        if ($redirect = $this->requestedDateRedirect($request, false)) {
            return $redirect;
        }

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

    private function requestedDateRedirect(Request $request, bool $video): ?RedirectResponse
    {
        $rawDate = $request->query('date');

        if (! is_string($rawDate) || $rawDate === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return redirect()->route($video ? 'khotab.day.video-date' : 'khotab.day.audio-date', [
            'd' => $date->format('j'),
            'm' => $date->format('n'),
            'y' => $date->format('Y'),
        ]);
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

        $todayRoute = $video ? 'khotab.day.video-today' : 'khotab.day.audio-today';
        $dateRoute = $video ? 'khotab.day.video-date' : 'khotab.day.audio-date';
        $yesterday = new DateTimeImmutable('yesterday');

        return view('khotab.day', [
            'video' => $video,
            'date' => $dayStart,
            'items' => $items,
            'mostDownloaded' => $mostDownloaded,
            'mostRecent' => $mostRecent,
            'breadcrumbTrail' => $breadcrumbTrail,
            'dateSearchAction' => route($todayRoute, [], false),
            'todayUrl' => route($todayRoute, [], false),
            'yesterdayUrl' => route($dateRoute, [
                'd' => $yesterday->format('j'),
                'm' => $yesterday->format('n'),
                'y' => $yesterday->format('Y'),
            ], false),
        ]);
    }
}
