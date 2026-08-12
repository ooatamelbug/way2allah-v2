<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Replaces `advanced-search/index.php`'s `Search` class — Roadmap task
 * 6.2. Re-verified directly against the current legacy source (not
 * `advanced-search.md`'s summary alone) before implementing; every
 * decision below is either a confirmed legacy behavior or an explicitly
 * approved deviation — see the class-level notes on each point.
 *
 * **POST, preserved** — the search form is live, sitewide, embedded in
 * `header.php:472` (not just inside the orphaned `advanced-search/index.php`
 * page itself); legacy's own form and both AJAX follow-ups are POST-only.
 * Approved: preserve POST, do not switch to GET (unlike `KhotabSearchController`'s
 * own, unrelated, single-page GET decision).
 *
 * **Combined response, not the legacy two-request AJAX split** — approved
 * deviation. Legacy's `ajax_mawad`/`ajax_series` modes independently load
 * `#kh_ajax_results`/`#ser_ajax_results` as two separate POST requests
 * returning raw HTML fragments (`assets/frontend/layout/scripts/inc.js`'s
 * `new_advanced_search_mawad()`/`new_advanced_search_series()`). This
 * controller renders both result sets from one request instead, matching
 * `KhotabSearchController`'s own already-shipped, documented precedent for
 * the same simplification. The dead older AJAX generation
 * (`advanced_search_khotab()`/`advanced_search_series()`, targeting
 * `advanced_search.htm` with `mode:"ajax_khotab"` — a value `index.php`
 * never actually checks) is out of scope, per explicit instruction.
 *
 * **Legacy validation preserved exactly, NOT `KhotabSearchController`'s
 * more permissive rule** — `Search::validate()` (`index.php:458-463`) is a
 * single, self-consistent condition (title required, >=4 chars, AND
 * department required, always) — confirmed, on direct re-read, to be
 * unrelated to `khotab/search.php`'s own IF-024 bug (a genuine
 * self-contradiction in *that* file's two sequential checks). The two are
 * not the same situation; this controller does not inherit that fix.
 *
 * **Coexists with `/khotab/search`** — `KhotabSearchController` is not
 * modified, replaced, or delegated *to* by name; this controller instead
 * reuses its underlying `ContentListingService::khotabAdvancedSearch()`/
 * `khotabSeriesAdvancedSearch()` methods directly for the `video`
 * department, exactly as `KhotabSearchController` itself does. `header.php`'s
 * own real, live, separate `video-advanced-search.htm` nav link (khotab-only)
 * and this page's sitewide header form are two distinct, coexisting legacy
 * entry points — evidence favors coexistence, not replacement.
 *
 * **`gallery`/`cds` are not selectable** — confirmed dead code in legacy
 * (`IncMe()`/undefined `$db`, fatal if reached), no behavior to preserve.
 * If a request specifies one anyway (bypassing the department `<select>`),
 * it is treated as an invalid department — zero results, no error — the
 * same safe-default already used for any unrecognized value.
 */
class SearchController
{
    /** `functions.php:1187-1198`'s `w2a_search_depts_arr()`, minus `gallery`/`cds` (confirmed dead, not offered). */
    private const DEPARTMENTS = [
        'video' => 'المرئيات',
        'audio' => 'الصوتيات',
        'dumped_files' => 'المواد المفرغة',
        'anasheed' => 'الأناشيد',
        'sections' => 'المقاطع المؤثرة',
        'cartoon' => 'الكارتون',
        'documentary' => 'الوثائقيات',
        'video_sections' => 'مقاطع المرئية',
        'fatawa' => 'الفتاوى المرئية',
    ];

    /** `index.php:884-915`/`:1353-1384`'s confirmed `parent_id` discriminator for the 5 shared-config "varieties" departments. */
    private const VARIETIES_PARENT_IDS = [
        'anasheed' => 98,
        'sections' => 16,
        'cartoon' => 57,
        'documentary' => 12,
        'video_sections' => 158,
    ];

    public function search(Request $request, ContentListingService $listing): View
    {
        $title = trim((string) $request->input('kh_title', ''));
        $department = trim((string) $request->input('kh_dept', ''));
        $channelId = (int) $request->input('kh_channel', 0);
        $authorId = (int) $request->input('kh_author_name', 0);
        $from = trim((string) $request->input('kh_from', ''));
        $to = trim((string) $request->input('kh_to', ''));

        // Search::validate() (index.php:458-463) — title required, >=4
        // characters (mb_strlen: legacy's own byte-based strlen() is a
        // confirmed no-op for multi-byte Arabic input in this file's
        // sibling remove_al() helper — not reproduced as a byte-length
        // check, which would reject valid short Arabic titles it was
        // never actually capable of accepting in the first place), AND a
        // department always required.
        $valid = mb_strlen($title) >= 4 && $department !== '' && array_key_exists($department, self::DEPARTMENTS);

        $mawad = null;
        $series = null;
        $topics = null;

        if ($valid) {
            $filters = [
                'title' => $title,
                'channel_id' => $channelId,
                'author_id' => $authorId,
                'from' => $from,
                'to' => $to,
            ];

            [$mawad, $series, $topics] = $this->runSearch($department, $filters, $listing);
        }

        return view('search.results', [
            'departments' => self::DEPARTMENTS,
            'title' => $title,
            'department' => $department,
            'channelId' => $channelId,
            'authorId' => $authorId,
            'from' => $from,
            'to' => $to,
            'valid' => $valid,
            'mawad' => $mawad,
            'series' => $series,
            'topics' => $topics,
        ]);
    }

    /**
     * `Search::perform_search()` (`index.php:465-491`)'s department
     * dispatch. Returns `[mawad, series, topics]` — `topics` is only ever
     * non-null for `fatawa` (its confirmed third result set,
     * `ContentListingService::fatwaTopicsAdvancedSearch()`'s docblock).
     */
    private function runSearch(string $department, array $filters, ContentListingService $listing): array
    {
        return match ($department) {
            'video' => [$listing->khotabAdvancedSearch($filters), $listing->khotabSeriesAdvancedSearch($filters), null],
            'audio' => [$listing->khotabAudioAdvancedSearch($filters), $listing->khotabAudioSeriesAdvancedSearch($filters), null],
            'dumped_files' => [$listing->khotabDumpedFilesAdvancedSearch($filters), null, null],
            'fatawa' => [
                $listing->fatwaQuestionsAdvancedSearch($filters),
                $listing->fatwaGeneralQuestionsAdvancedSearch($filters),
                $listing->fatwaTopicsAdvancedSearch($filters),
            ],
            'anasheed', 'sections', 'cartoon', 'documentary', 'video_sections' => [
                $listing->anasheedAdvancedSearch($filters, self::VARIETIES_PARENT_IDS[$department]),
                $listing->anasheedGroupAdvancedSearch($filters, self::VARIETIES_PARENT_IDS[$department]),
                null,
            ],
            default => [null, null, null],
        };
    }
}
