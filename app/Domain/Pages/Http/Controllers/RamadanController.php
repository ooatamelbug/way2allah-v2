<?php

namespace App\Domain\Pages\Http\Controllers;

use App\Domain\Content\Models\IslamicSetting;
use App\Domain\Content\Services\ContentListingService;
use Illuminate\Contracts\View\View;

/**
 * Replaces `pages/ramadan.php` + `ramadan1442.php` + `ramadan-archive.php`
 * (Roadmap task 6.3, gated on Business Confirmation #2 — RESOLVED: both
 * `ramadan`/`share` active/required). Consolidates the 3 duplicated legacy
 * files into the single parameterized view `pages.md` §9's Migration
 * Decision Classification calls for. `ramadan.php` (the current, actively
 * maintained file) is the authoritative source for year boundaries and
 * counter behavior, per explicit authorization — `ramadan-archive.php`'s
 * confirmed "1446"/"1445" duplicate-section bug and `ramadan1442.php`'s
 * object-syntax counter bug are both deliberately NOT reproduced.
 *
 * Only the current year (1447) increments its visit counter on each load —
 * `ramadan.php` itself only ever increments its own single hardcoded
 * `$currentYear`, so a consolidated multi-year view incrementing every
 * section on every load would be new, invented behavior, not a faithful
 * port. Only years 1441-1447 (excluding 1445, which is not a section in
 * `ramadan.php` at all) render a visit counter — `ramadan.php`'s own
 * `$data` array has no `'tools'` key for the 1434-1440 sections (confirmed
 * by direct re-read; not previously documented in `pages.md`).
 *
 * `images/channels/{id}.png` (referenced once per row) is kept as the
 * exact legacy relative path — confirmed missing from this codebase
 * (`pages.md` §5 addendum) but explicitly authorized to remain as-is, no
 * new placeholder/asset infrastructure. Cross-links use this codebase's
 * existing named routes (`khotab.series.show`, `khotab.authors.show`,
 * `channels.show-author`) instead of `ramadan.php`'s raw hrefs.
 */
class RamadanController
{
    private const CURRENT_YEAR = 1447;

    /**
     * `ramadan.php`'s own set of year-sections whose `$data` array
     * includes a `'tools'` (visit-counter) key — 1447, 1446, 1444, 1442,
     * 1441. 1443's `'tools'` line is present in source but commented out
     * (`ramadan.php:420`, re-confirmed by direct re-read) — no counter for
     * 1443, unlike this constant previously claimed. 1434-1440 have no
     * counter in the legacy source at all (no `'tools'` key present,
     * commented or otherwise).
     */
    private const YEARS_WITH_COUNTER = [1447, 1446, 1444, 1442, 1441];

    public function __invoke(ContentListingService $listing): View
    {
        $seriesByYear = $listing->ramadanSeriesByYear();
        $counters = IslamicSetting::incrementRamadanCounter(self::CURRENT_YEAR);

        return view('pages.ramadan', [
            'seriesByYear' => $seriesByYear,
            'counters' => $counters,
            'yearsWithCounter' => self::YEARS_WITH_COUNTER,
        ]);
    }
}
