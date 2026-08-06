<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Replaces `khotab/search.php` — Roadmap task 4.1. Public-only, same scope
 * decision as every other khotab controller (legacy's admin branch, which
 * drops the `hidden='0' AND count>'0'` filter, is not reproduced).
 *
 * `search.php` is itself orphaned (no `.htaccess` rule reaches it or its
 * self-referenced `video-advanced-search.htm` — confirmed by exhaustive
 * search) — same raw-path-only profile as `khotab/dump.php`; registered
 * at a new path with a legacy-path redirect, not at a pretend pretty URL
 * legacy never actually had.
 *
 * IF-018's fix: item search results link the author using the query's
 * actually-selected `author` column, not the undefined `author_id`
 * legacy's own link-building code read.
 * IF-023's fix: page title reflects what this page is, not an undefined
 * `$Author`.
 * IF-024's fix: the "title must be ≥4 characters" rule only applies when
 * a title was actually supplied — a channel/author/date-only search is
 * no longer unconditionally rejected.
 *
 * One deliberate behavior change beyond bug fixes: this action reads
 * filters from the query string (`GET`) instead of legacy's `POST`-only
 * form. A search is a read/idempotent operation, so a `GET` request makes
 * results bookmarkable/shareable — a real improvement, not a silent
 * change, and legacy's own AJAX split (`ajax_series`/`ajax_khotab` modes,
 * two independently-loading page fragments) isn't reproduced either: both
 * result sets render together in one request, matching the "mode not
 * set" full-page branch of the legacy file.
 */
class KhotabSearchController
{
    /** Legacy's own hardcoded "1980" fallback (`search.php`'s `$first_start`) for an open-ended "to" date with no "from". */
    private const FIRST_START = 315525600;

    public function index(Request $request, ContentListingService $listing): View
    {
        $title = trim((string) $request->query('title', ''));
        $channelId = (int) $request->query('channel', 0);
        $authorId = (int) $request->query('author', 0);
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $hasCriteria = $title !== '' || $channelId > 0 || $authorId > 0 || $from !== '' || $to !== '';
        $titleTooShort = $title !== '' && strlen($title) < 4;

        $series = null;
        $items = null;

        if ($hasCriteria && ! $titleTooShort) {
            $filters = array_merge([
                'title' => $title,
                'channel_id' => $channelId,
                'author_id' => $authorId,
            ], $this->dateRange($from, $to));

            $series = $listing->khotabSeriesAdvancedSearch($filters);
            $items = $listing->khotabAdvancedSearch($filters);
        }

        $authors = Cache::remember('khotab-search-authors-menu', 3600, fn () => Author::orderBy('name')->get(['id', 'name']));
        $channels = Cache::remember('khotab-search-channels-menu', 3600, fn () => Channel::orderBy('title')->get(['id', 'title']));

        return view('khotab.search', compact(
            'title', 'channelId', 'authorId', 'from', 'to',
            'hasCriteria', 'titleTooShort', 'series', 'items', 'authors', 'channels',
        ));
    }

    /** `search.php:251-281`'s date-range resolution, reproduced exactly (the 3 explicit cases, plus "no dates at all" which legacy also leaves unfiltered). */
    private function dateRange(string $from, string $to): array
    {
        if ($from === '' && $to === '') {
            return [];
        }

        $today = strtotime(date('Y-m-d'));

        if ($from !== '' && $to === '') {
            return ['start' => strtotime($from), 'end' => $today];
        }

        if ($from === '') {
            return ['start' => self::FIRST_START, 'end' => strtotime($to)];
        }

        return ['start' => strtotime($from), 'end' => strtotime($to)];
    }
}
