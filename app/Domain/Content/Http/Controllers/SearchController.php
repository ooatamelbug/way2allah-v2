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
 * G-05 addition: the 3 distinct validation messages legacy itself shows
 * (`index.php:207-224`, sequential early-return checks) are now
 * reproduced individually, in the same precedence order, rather than one
 * combined message.
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
 * G-05 fix: the `video` department's date range (`kh_from`/`kh_to`) was a
 * confirmed, empirically-verified silent no-op — `khotabAdvancedSearch()`/
 * `khotabSeriesAdvancedSearch()` only read pre-computed `start`/`end`
 * timestamp filter keys (the calling convention `KhotabSearchController`
 * already satisfies via its own `dateRange()`), which this controller
 * never populated. Fixed here by computing the identical 3-branch
 * date-resolution `KhotabSearchController::dateRange()` already uses —
 * the *reason* to match that exact logic is that it's the shared
 * methods' own established calling contract, not because
 * `advanced-search/index.php`'s own `Listmawad()`/`ListSeries()` use this
 * logic themselves (they don't — every other department here uses the
 * simpler, independent `applyAdvancedSearchDateRange()` that already
 * matches `advanced-search/index.php`'s own literal `from`/`to` handling).
 * The already-approved architectural reuse of `khotabAdvancedSearch()`/
 * `khotabSeriesAdvancedSearch()` itself is kept exactly as before — only
 * this controller's own input handling changed.
 *
 * G-05 fix: `video`/`audio`/`dumped_files` now order by `weight DESC`,
 * matching `media_search()`'s `mawad_order_by` (`index.php:510`) —
 * `advanced-search/index.php`'s own literal source, not
 * `khotab/search.php`'s `time DESC` convention. For `video`, this is
 * passed as an explicit `$orderBy` argument to the reused
 * `khotabAdvancedSearch()` (defaulting to `time DESC` so
 * `KhotabSearchController`'s own call is completely unaffected); `audio`/
 * `dumped_files` use their own `SearchController`-exclusive methods,
 * corrected directly.
 *
 * G-05 fix: channel filtering now validates the channel actually exists
 * (`channel_id IN (SELECT id FROM nuke_sat_channels WHERE id=X)`),
 * matching `advanced-search/index.php`'s own query shape exactly — not
 * `khotab/search.php`'s plain-equality convention. For `video`, passed as
 * an explicit argument (default `false`, preserving `KhotabSearchController`);
 * every other department's method applies it unconditionally (no other
 * consumer exists for those methods).
 *
 * **`gallery`/`cds` are not selectable** — confirmed dead code in legacy
 * (`IncMe()`/undefined `$db`, fatal if reached), no behavior to preserve.
 * If a request specifies one anyway (bypassing the department `<select>`),
 * it is treated as an invalid department — zero results, a message, no
 * PHP error — the same safe-default already used for any unrecognized
 * value (deliberately not a literal port of legacy's own fatal-error path
 * for this case).
 *
 * **Deferred, not implemented here** (explicit scope decisions, not
 * silent gaps): the 15-second anti-repeat-search cookie
 * (`index.php:7-13`) and the author/channel name-to-id autocomplete
 * (`header.php`'s own inline JS + `w2a_autocomplete/authors.txt`/
 * `channels.txt`) — both real, confirmed legacy behaviors, both
 * genuinely new session/JS behavior rather than pure display restoration,
 * both explicitly deferred to a separate future pass.
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

    /** `search.php:253`'s hardcoded "1980" fallback for an open-ended "to" date with no "from" — identical constant to `KhotabSearchController::FIRST_START`. */
    private const FIRST_START = 315525600;

    public function search(Request $request, ContentListingService $listing): View
    {
        $title = trim((string) $request->input('kh_title', ''));
        $department = trim((string) $request->input('kh_dept', ''));
        $channelId = (int) $request->input('kh_channel', 0);
        $authorId = (int) $request->input('kh_author_name', 0);
        $from = trim((string) $request->input('kh_from', ''));
        $to = trim((string) $request->input('kh_to', ''));

        // Search::validate() (index.php:458-463) + index.php:207-224's 3
        // distinct, sequentially-checked error messages, same precedence.
        $titleEmpty = $title === '';
        $titleTooShort = ! $titleEmpty && mb_strlen($title) < 4;
        $departmentInvalid = $department === '' || ! array_key_exists($department, self::DEPARTMENTS);

        $errorMessage = match (true) {
            $titleEmpty => 'يجب عليك ادخال عنوان المادة',
            $titleTooShort => 'عفواً ، يجب إدخال أربعة أحرف على الأقل للبحث',
            $departmentInvalid => 'يجب عليك إختيار القسم',
            default => null,
        };

        $valid = $errorMessage === null;

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
                ...$this->dateRange($from, $to),
            ];

            [$mawad, $series, $topics] = $this->runSearch($department, $filters, $listing);
        }

        $mawadEmpty = $mawad === null || $mawad->total() === 0;
        $seriesEmpty = $series === null || $series->total() === 0;
        $topicsEmpty = $topics === null || $topics->total() === 0;

        return view('search.results', [
            'departments' => self::DEPARTMENTS,
            'title' => $title,
            'department' => $department,
            'resultKind' => $this->resultKind($department),
            'channelId' => $channelId,
            'authorId' => $authorId,
            'from' => $from,
            'to' => $to,
            'valid' => $valid,
            'errorMessage' => $errorMessage,
            'mawad' => $mawad,
            'series' => $series,
            'topics' => $topics,
            // `Listmawad()`/`ListSeries()` only ever invoke `*_view()` when
            // their own result count is > 0 — every "no items in this
            // specific set" message embedded inside media_view()/
            // varieties_view()/etc. is therefore unreachable dead code
            // (confirmed by direct trace of the calling gate), not
            // reproduced here. Sections are hidden entirely when empty,
            // matching legacy's real, reachable behavior instead.
            'mawadEmpty' => $mawadEmpty,
            'seriesEmpty' => $seriesEmpty,
            'topicsEmpty' => $topicsEmpty,
            // index.php:273-277 — the one, additional, page-level message shown
            // when NEITHER mawad NOR series produced anything (topics excluded,
            // matching legacy's own exact condition).
            'noResultsAtAll' => $valid && $mawadEmpty && $seriesEmpty,
            // Passed through so the fatawa result partials can call
            // countFatawaForQuestion()/countGeneralQuestionsForTopic()
            // once per row — the confirmed legacy N+1 pattern, reproduced
            // exactly per explicit instruction, not batched.
            'listing' => $listing,
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
            'video' => [$listing->khotabAdvancedSearch($filters, 'tb1.weight', true), $listing->khotabSeriesAdvancedSearch($filters, true), null],
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

    /** View-rendering discriminator — which of the 3 result-card shapes (`media`/`varieties`/`fatawa`) this department uses. */
    private function resultKind(string $department): ?string
    {
        return match ($department) {
            'video', 'audio', 'dumped_files' => 'media',
            'anasheed', 'sections', 'cartoon', 'documentary', 'video_sections' => 'varieties',
            'fatawa' => 'fatawa',
            default => null,
        };
    }

    /**
     * `khotab/search.php:251-281`'s date-range resolution — the exact
     * calling convention `khotabAdvancedSearch()`/`khotabSeriesAdvancedSearch()`
     * expect (`KhotabSearchController::dateRange()`'s own twin). Returns
     * `['start' => int, 'end' => int]` or `[]` if neither date was given
     * — harmless to merge into every department's filters array, since
     * only those 2 reused methods ever read `start`/`end`; every other
     * department's method reads `from`/`to` directly instead.
     */
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
