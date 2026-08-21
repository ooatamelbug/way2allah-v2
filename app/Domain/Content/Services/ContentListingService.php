<?php

namespace App\Domain\Content\Services;

use App\Domain\Admin\Models\SiteOption;
use App\Domain\Content\Models\AnasheedItem;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * TRACEABILITY (Blueprint v1.0 §4, P-011) — read before modifying
 * ============================================================================
 *
 * 1. EXACT LEGACY FUNCTIONS REPLACED
 *    - khotab/functions.php: ListGroup() [L333-406], ListSeries() [L409-508],
 *      ListKhotab() [L511-~780, 4 internal modes: 'fixed'/'new', 'day', 'pdf', default]
 *    - categories/functions.php: ListGroup() [L2-75], ListSeries() [L78-~250],
 *      ListKhotab() [L318-423]
 *    Six source functions total, producing 9 distinct query shapes (khotab's
 *    ListKhotab alone branches into 4 shapes internally).
 *
 * 2. DIFFERENCES BETWEEN THE LEGACY IMPLEMENTATIONS
 *    These are NOT superficial — the two modules filter along fundamentally
 *    different dimensions:
 *      - khotab's versions filter by AUTHOR (author_id / kh.author) and
 *        accept a `$hidden` override parameter (used by admin-preview
 *        contexts to see hidden rows).
 *      - categories' versions filter by CATEGORY — ListGroup() via the
 *        pipe-delimited `grp.cat LIKE '%|$id|%'` pattern (the same
 *        data-quality anti-pattern already flagged for
 *        `nuke_islamic_groups.cat` elsewhere in this audit, P-013)
 *        rather than a junction table; ListSeries()/ListKhotab() instead
 *        use proper junction tables (`series_category_index`,
 *        `khotab_category_index`). categories' versions never accept a
 *        hidden override — hidden rows are always excluded, no exception.
 *      - khotab's ListGroup() computes the displayed `count` as a LIVE
 *        `COUNT(kh.id)` via an INNER JOIN to nuke_islamic_khotab. categories'
 *        ListGroup() instead trusts the stored `grp.count` column directly
 *        with no join to khotab at all — two different computations, not
 *        just two different filters.
 *      - khotab's ListGroup() SELECTs `grp.count` *and* aliases
 *        `COUNT(kh.id) AS count` in the same query — a genuine legacy bug
 *        (duplicate alias). MySQL/the result mapping resolves this so the
 *        aggregate silently wins over the stored column in the object
 *        actually handed to the template. This is what production has
 *        observably been serving, not a hypothetical.
 *      - khotab's ListSeries() never joins authors; categories' ListSeries()
 *        does (`auth.name`, `prename`, `auth.id as authID`).
 *      - khotab's ListKhotab() branches into 4 shapes with different
 *        SELECT lists, different JOINs, different ORDER BY (weight+time,
 *        weight+id, weight+pdf_time), and a LIMIT 50 in exactly one branch
 *        ('fixed'/'new'). Its default branch, unlike the other three
 *        branches, filters `author`/`ser_id`/`group_id` unconditionally
 *        (even when 0) rather than only when the value is positive, and
 *        never joins authors at all (no author name available to that
 *        branch's output). categories' ListKhotab() is a single shape,
 *        orders by `time` only (no `weight`), and does join authors.
 *
 * 3. HOW THE DIFFERENCES WERE RECONCILED
 *    Not reconciled into one generic method — that would either silently
 *    change one context's behavior to match the other's, or require a
 *    pile of nullable parameters whose valid combinations aren't obvious
 *    from the signature (arguably the same "mode string" anti-pattern
 *    driving khotab's own ListKhotab() branching, just relocated). Instead:
 *    nine explicitly-named public methods, one per confirmed distinct query
 *    shape, sharing only the genuinely identical plumbing (the channel
 *    LEFT JOIN, parameter binding). Each method's name states its filter
 *    dimension and mode so a caller can't accidentally get khotab's
 *    behavior while asking for categories', or vice versa.
 *
 * 4. BEHAVIOR PRESERVED EXACTLY
 *    Every WHERE condition, JOIN (including which are INNER vs LEFT),
 *    GROUP BY, ORDER BY, and LIMIT is reproduced as found. The
 *    author-vs-category filter split, the hidden-override availability
 *    split, the live-count-vs-stored-count split, and khotab default
 *    mode's unconditional (even-when-zero) filtering are all preserved
 *    per-method rather than normalized away.
 *
 * 5. BEHAVIOR INTENTIONALLY CHANGED, AND WHY
 *    - SQL injection: categories/functions.php's original queries
 *      interpolate `$id`/`$video`/etc. directly into the SQL string with
 *      no escaping. Every method here uses the query builder's bound
 *      parameters instead. This changes nothing observable (same rows,
 *      same order) — it removes a vulnerability class, not a behavior.
 *    - Return shape: legacy echoes HTML directly inside the loop. These
 *      methods return data only (a Collection of stdClass rows, matching
 *      ezSQL's own get_results() shape) — rendering is a Wave 3/4
 *      controller/Blade concern, not this service's.
 *    - khotab's ListGroup() duplicate-alias bug (§2 above): the *serving*
 *      behavior (aggregate count) is preserved as `count`; the shadowed
 *      stored column is additionally exposed as `stored_group_count` so
 *      no data is silently lost, rather than either silently "fixing" the
 *      bug (an unverified behavior change) or reproducing the information
 *      loss (discarding real data pointlessly, since building this query
 *      correctly costs nothing extra).
 *
 * 6. RISK IF THIS RECONCILIATION IS WRONG
 *    Wrong here means Wave 3/4's `khotab`, `categories`, `channels`, and
 *    `chat_room` controllers all inherit the same bug simultaneously,
 *    since all four are named Blueprint §4 consumers of this service —
 *    the exact failure mode P-011 exists to prevent, just relocated to
 *    the new code instead of the old. The specific risks: (a) swapping
 *    which methods apply the hidden-override would leak hidden content
 *    publicly or hide content from admin previews that should see it;
 *    (b) getting the author-vs-category filter columns wrong would
 *    return the wrong rows entirely, not merely mis-ordered ones;
 *    (c) getting khotab default mode's unconditional-filter behavior
 *    wrong would silently change which items appear when author_id/
 *    ser_id/group_id are legitimately 0.
 *
 * 7. TESTS PROVING BEHAVIORAL EQUIVALENCE
 *    tests/Feature/Content/ContentListingServiceTest.php — one seeded
 *    fixture dataset per method, asserting the exact row set, order, and
 *    (for the two ListGroup() variants) the exact computed values,
 *    including a dedicated test for the duplicate-alias/stored-count
 *    finding in §5.
 *
 * ============================================================================
 * WAVE 3 ADDITION — channels/functions.php's ListGroup()/ListSeries()/
 * ListKhotab() (a confirmed 5th distinct query shape, per channels.md §5
 * and P-011)
 * ============================================================================
 *
 * Filters by `channel_id` always, plus an OPTIONAL `author` filter (the
 * `$Author > 0` branch in all three legacy functions) — a different filter
 * combination from both khotab's (author-only, conditional) and
 * categories' (category+junction) variants. Specifics preserved exactly:
 *  - groupsByChannel()/seriesByChannel(): join `nuke_islamic_authors` for
 *    the author name (khotab's own ListGroup/ListSeries never do this);
 *    `hidden='0'` always, no override parameter exists in the legacy
 *    functions (same as categories' variants, unlike khotab's).
 *  - khotabItemsByChannel(): filters `ser_id`/`group_id` UNCONDITIONALLY
 *    (always in the WHERE clause, regardless of value) — the same
 *    unconditional-filter shape as khotab's own ListKhotab() default
 *    branch (IF-005), found independently here in a different module.
 *    Orders by `time` only, no `weight` (channels/functions.php:192) —
 *    matching categories' ListKhotab(), not khotab's.
 * Not a new Architecture Evolution Proposal: Blueprint §4 already named
 * `channels` as a planned ContentListingService consumer in Wave 1 — this
 * is that already-decided consolidation being carried out, not a new
 * cross-module pattern discovered mid-implementation.
 * ============================================================================
 */
class ContentListingService
{
    // ---- Groups -----------------------------------------------------

    /** khotab/functions.php ListGroup() — filter by author, live-computed count. */
    public function groupsByAuthor(int $authorId, bool $video, bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_groups as grp')
            ->join('nuke_islamic_khotab as kh', 'grp.id', '=', 'kh.group_id')
            ->leftJoin('nuke_sat_channels as ch', 'grp.channel_id', '=', 'ch.id')
            ->where('grp.author_id', $authorId)
            ->where('grp.vedio', (int) $video)
            ->where('grp.count', '>', 0)
            ->groupBy('grp.id')
            ->orderByDesc('grp.title')
            ->select([
                'grp.id', 'grp.channel_id', 'grp.title',
                'ch.title as channel',
                DB::raw('COUNT(kh.id) as count'),
                DB::raw('grp.count as stored_group_count'),
            ]);

        if (! $includeHidden) {
            $query->where('grp.hidden', '0');
        }

        return $query->get();
    }

    /** categories/functions.php ListGroup() — filter by category (pipe-delimited `cat`), stored count, no hidden override. */
    public function groupsByCategory(int $categoryId, bool $video): Collection
    {
        return DB::connection('main')->table('nuke_islamic_groups as grp')
            ->leftJoin('nuke_sat_channels as ch', 'grp.channel_id', '=', 'ch.id')
            ->where('grp.cat', 'like', "%|{$categoryId}|%")
            ->where('grp.vedio', (int) $video)
            ->where('grp.hidden', '0')
            ->where('grp.count', '>', 0)
            ->orderByDesc('grp.title')
            ->select(['grp.id', 'grp.cat', 'grp.channel_id', 'grp.title', 'grp.count', 'ch.title as channel'])
            ->get();
    }

    // ---- Series -------------------------------------------------------

    /** khotab/functions.php ListSeries() — filter by author + group, no author join. */
    public function seriesByAuthorAndGroup(int $authorId, int $groupId, bool $video, bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_series as ser')
            ->leftJoin('nuke_sat_channels as ch', 'ser.channel_id', '=', 'ch.id')
            ->where('ser.author_id', $authorId)
            ->where('ser.group_id', $groupId)
            ->where('ser.vedio', (int) $video)
            ->where('ser.count', '>', 0)
            ->orderByDesc('ser.lastupdate')
            ->select(['ser.id', 'ser.channel_id', 'ser.title', 'ser.time', 'ser.lastupdate', 'ser.count', 'ch.title as channel']);

        if ($groupId > 0) {
            $query->leftJoin('nuke_islamic_groups as grp', 'grp.id', '=', 'ser.group_id');
        }

        if (! $includeHidden) {
            $query->where('ser.hidden', '0');

            if ($groupId > 0) {
                $query->where('grp.hidden', '0');
            }
        }

        return $query->get();
    }

    /** categories/functions.php ListSeries() — filter by category (junction table) + group, includes author, no hidden override. */
    public function seriesByCategoryAndGroup(int $categoryId, int $groupId, bool $video): Collection
    {
        return DB::connection('main')->table('nuke_islamic_series as ser')
            ->join('series_category_index as sci', function ($join) use ($categoryId) {
                $join->on('sci.series_id', '=', 'ser.id')->where('sci.category_id', $categoryId);
            })
            ->leftJoin('nuke_sat_channels as ch', 'ser.channel_id', '=', 'ch.id')
            ->leftJoin('nuke_islamic_authors as auth', 'auth.id', '=', 'ser.author_id')
            ->where('ser.group_id', $groupId)
            ->where('ser.vedio', (int) $video)
            ->where('ser.hidden', '0')
            ->where('ser.count', '>', 0)
            ->orderByDesc('ser.lastupdate')
            ->select([
                'ser.id', 'ser.channel_id', 'ser.title', 'ser.time', 'ser.lastupdate', 'ser.count',
                'ch.title as channel', 'auth.name', 'auth.prename', 'auth.id as authID',
            ])
            ->get();
    }

    // ---- Khotab items ---------------------------------------------------

    /** khotab/functions.php ListKhotab(), mode='fixed'|'new'. */
    public function khotabItemsFixedOrNew(
        int $authorId,
        int $serId,
        int $groupId,
        bool $video,
        bool $onlyFixed,
        bool $includeHidden = false,
    ): Collection {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->where('kh.vedio', (int) $video)
            ->orderByDesc('kh.weight')->orderByDesc('kh.time')
            ->limit(50)
            ->select([
                'kh.id', 'kh.author', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments',
                'kh.time', 'kh.hits', 'kh.weight', 'ad.adur', 'ath.name', 'ath.prename',
            ]);

        if ($authorId > 0) {
            $query->where('kh.author', $authorId);
        }

        if ($serId > 0) {
            $query->leftJoin('nuke_islamic_series as ser', 'ser.id', '=', 'kh.ser_id')
                ->where('kh.ser_id', $serId);
            if (! $includeHidden) {
                $query->where('ser.hidden', '0');
            }
        }

        if ($groupId > 0) {
            $query->leftJoin('nuke_islamic_groups as grp', 'grp.id', '=', 'kh.group_id')
                ->where('kh.group_id', $groupId);
            if (! $includeHidden) {
                $query->where('grp.hidden', '0');
            }
        }

        if ($onlyFixed) {
            $query->where('kh.fixed', '1');
        }

        if (! $includeHidden) {
            $query->where('kh.hidden', '0');
        }

        return $query->get();
    }

    /** khotab/functions.php ListKhotab(), mode='day'. */
    public function khotabItemsForDay(bool $video, int $dayStart, int $dayEnd, bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->where('kh.vedio', (int) $video)
            ->where('kh.time', '>=', $dayStart)
            ->where('kh.time', '<', $dayEnd)
            ->orderByDesc('kh.weight')->orderByDesc('kh.id')
            // khotab-video-today.htm parity: khotab/functions.php's ListKhotab()
            // 'day' branch (mode == 'day') shows an author link per row —
            // functions.php:662 uses $item->author (the raw FK column), not an
            // aliased id — kh.author was missing here, added for that link.
            ->select([
                'kh.id', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments',
                'kh.time', 'kh.hits', 'kh.weight', 'ad.adur', 'kh.author', 'ath.name', 'ath.prename',
            ]);

        if (! $includeHidden) {
            $query->where('kh.hidden', '0');
        }

        return $query->get();
    }

    /** khotab/functions.php ListKhotab(), mode='pdf'. */
    public function khotabItemsWithPdf(bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->where('kh.pdf', '>', 0)
            ->orderByDesc('kh.weight')->orderByDesc('kh.pdf_time')
            ->select([
                'kh.id', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments',
                'kh.pdf_time as time', 'kh.hits', 'kh.weight', 'ad.adur', 'ath.name', 'ath.prename',
            ]);

        if (! $includeHidden) {
            $query->where('kh.hidden', '0');
        }

        return $query->get();
    }

    /** khotab/functions.php ListKhotab(), default (else) branch — no authors join, unconditional author/ser/group filtering. */
    public function khotabItemsDefault(int $authorId, int $serId, int $groupId, bool $video, bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->where('kh.author', $authorId)
            ->where('kh.vedio', (int) $video)
            ->where('kh.ser_id', $serId)
            ->where('kh.group_id', $groupId)
            ->orderByDesc('kh.weight')->orderByDesc('kh.time')
            ->select(['kh.id', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments', 'kh.time', 'kh.hits', 'kh.weight', 'ad.adur']);

        if ($serId > 0) {
            $query->leftJoin('nuke_islamic_series as ser', 'ser.id', '=', 'kh.ser_id');
            if (! $includeHidden) {
                $query->where('ser.hidden', '0');
            }
        }

        if ($groupId > 0) {
            $query->leftJoin('nuke_islamic_groups as grp', 'grp.id', '=', 'kh.group_id');
            if (! $includeHidden) {
                $query->where('grp.hidden', '0');
            }
        }

        if (! $includeHidden) {
            $query->where('kh.hidden', '0');
        }

        return $query->get();
    }

    /** categories/functions.php ListKhotab() — filter by category (junction table) + series + group, includes author, no hidden override, orders by time only (no weight). */
    public function khotabItemsByCategory(int $categoryId, int $serId, int $groupId, bool $video): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            })
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->leftJoin('nuke_islamic_authors as auth', 'auth.id', '=', 'kh.author')
            ->where('kh.vedio', (int) $video)
            ->where('kh.ser_id', $serId)
            ->where('kh.group_id', $groupId)
            ->where('kh.hidden', '0')
            ->orderByDesc('kh.time')
            ->select([
                'kh.id', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments',
                'kh.time', 'kh.hits', 'ad.adur', 'auth.name', 'auth.prename', 'auth.id as authID',
            ])
            ->get();
    }

    /**
     * `categories/downitems.php`'s own inline SQL (`khotab-series-{id}.grx`
     * / `khotab-series-{id}-{cat}.grx`) — a genuinely different shape from
     * `khotabItemsByCategory()` above: no `vedio` filter, no `group_id`
     * filter, and the `khotab_category_index` join is conditional
     * (`$cat_id>0`) rather than always applied. Confirmed by direct
     * reading, not assumed from the similarly-shaped sibling method.
     */
    public function khotabLinksForSeriesDownload(int $seriesId, ?int $categoryId): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh');

        if (! empty($categoryId)) {
            $query->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            });
        }

        return $query
            ->where('kh.ser_id', $seriesId)
            ->where('kh.hidden', '0')
            ->select(['kh.id', 'kh.link', 'kh.title'])
            ->get();
    }

    /**
     * `anasheed/functions.php:266-304`'s `download_var_group_getright()`
     * (`var-series-{id}.grx`) — confirmed a genuinely different query
     * shape from `khotabLinksForSeriesDownload()` above: no `hidden`
     * filter at all, ordered by `order_in_group DESC` (not unordered),
     * and — critically — the caller does NOT use `link` for the playlist
     * URL (see `AnasheedGroupController::downloadGetright()`'s own
     * docblock for why `id` is selected instead).
     */
    public function anasheedItemsForGroupDownload(int $groupId): Collection
    {
        return DB::connection('main')->table('nuke_anasheed_anasheed as an')
            ->where('an.group_id', $groupId)
            ->orderByDesc('an.order_in_group')
            ->select(['an.id', 'an.title', 'an.link'])
            ->get();
    }

    /**
     * categories/functions.php ListVar() — categories/category.php's
     * `op=var` branch (`var-category-{id}.htm`). Filters `nuke_anasheed_anasheed`
     * by the SAME pipe-delimited `cat_id LIKE '%|$id|%'` pattern ListGroup()
     * uses on khotab's `cat` column — a distinct table/column pair from
     * `khotabItemsByCategory()`'s junction-table approach above, reproduced
     * as found, not normalized. No `hidden` override, no ORDER BY (legacy
     * has none — confirmed by direct reading, not an omission).
     */
    public function anasheedItemsByCategory(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_anasheed_anasheed as ana')
            ->leftJoin('nuke_anasheed_advanced as ad', 'ana.id', '=', 'ad.id')
            ->leftJoin('nuke_sat_channels as ch', 'ana.channel_id', '=', 'ch.id')
            ->where('ana.cat_id', 'like', '%|'.$categoryId.'|%')
            ->where('ana.hidden', '0')
            ->select([
                'ana.id', 'ana.channel_id', 'ch.title as channel', 'ana.title', 'ana.comments',
                'ana.mytime as time', 'ana.hits', 'ad.adur',
            ])
            ->get();
    }

    /**
     * `categories/tree.php`'s `showtree()` — `categories.htm`'s flat
     * category-tree source data (`op`-less/default branch: `video_count`
     * filter). The 3-level hierarchy itself (top-level -> group -> leaf,
     * matched by `main_cat`) is built from this single flat result in the
     * Blade view, exactly mirroring `showtree()`'s own approach (PHP loops
     * over one flat array, not recursive SQL or eager-loaded relations).
     * `ksort($resultcat)` in the legacy source is a no-op (the array is
     * already sequentially-keyed from `get_results()`) — not reproduced.
     */
    public function categoryTree(): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('video_count', '>', 0)
            ->orderBy('title')
            ->orderByDesc('id')
            ->select(['id', 'title', 'main_cat'])
            ->get();
    }

    /**
     * `categories/tree.php`'s `showtree()`, `op=var` branch (`anasheed_count`
     * filter, `var-categories.htm`) — same shared function as
     * `categoryTree()` above, parameterized differently by the legacy file
     * itself; a separate method here rather than a `$field` parameter,
     * matching this class's own one-method-per-confirmed-query-shape
     * convention. Every node this tree produces links to
     * `var-category-{id}.htm`, already implemented
     * (`CategoryController::showAnasheed()`).
     */
    public function anasheedCategoryTree(): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('anasheed_count', '>', 0)
            ->orderBy('title')
            ->orderByDesc('id')
            ->select(['id', 'title', 'main_cat'])
            ->get();
    }

    /**
     * `categories/tree.php`'s `showtree()`, `op=fatawa` branch (`q_count`
     * filter, `fatawa-categories.htm`) — same shared function/pattern as
     * `categoryTree()`/`anasheedCategoryTree()` above. Every node this tree
     * produces links to `fatawa-category-{id}.htm`, whose own legacy
     * source (`fatawa/category.php`) is confirmed unrecoverable (IF-038,
     * Fatawa Categories Source Recovery pass) — those links are not
     * expected to resolve, and no redirect/replacement is implemented for
     * them here. This method covers only the tree page's own data, which
     * is independently complete and verified.
     */
    public function fatawaCategoryTree(): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('q_count', '>', 0)
            ->orderBy('title')
            ->orderByDesc('id')
            ->select(['id', 'title', 'main_cat'])
            ->get();
    }

    /**
     * `khotab/dump.php`'s own inline SQL (a THIRD distinct PDF-listing
     * shape, alongside `ListKhotab(mode='pdf')`/`khotabItemsWithPdf()` and
     * `ListPDF()`/`khotabPdfItemsByAuthor()` above) — `SELECT f.*, th.id as
     * author, th.prename, th.name FROM khotab f, authors th WHERE (f.pdf >
     * 0) AND f.hidden=0 AND f.author=th.id ORDER BY pdf_time DESC LIMIT
     * 50`. Confirmed DIFFERENT from `khotabItemsWithPdf()`: no `weight` in
     * the ORDER BY at all (pdf_time only) and `hidden=0` is unconditional
     * (no admin-preview override exists in `dump.php`) — reproduced as its
     * own method rather than reusing `khotabItemsWithPdf()`, which would
     * silently change this page's actual sort order.
     */
    public function khotabPdfDump(): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as f')
            ->join('nuke_islamic_authors as th', 'f.author', '=', 'th.id')
            ->where('f.pdf', '>', 0)
            ->where('f.hidden', '0')
            ->orderByDesc('f.pdf_time')
            ->limit(50)
            ->select(['f.id', 'f.title', 'f.pdf_time', 'th.id as author', 'th.prename', 'th.name'])
            ->get();
    }

    // ---- Wave 4: khotab/functions.php's ListPDF() (a DIFFERENT function from ListKhotab(mode='pdf')/khotabItemsWithPdf() above) ----

    /**
     * `khotab/functions.php:734-876`'s `ListPDF($ob, $hidden)`, its `else`
     * branch (the only one any confirmed call site reaches — `khotab/
     * author.php`'s `op=pdf` page calls `ListPDF($ob)` with `$ob->mode`
     * never set, so the `'fixed'/'new'` branch is dead code from this call
     * site and not ported here, matching this project's "don't build
     * ahead of real evidence" discipline). Filters `author`/`ser_id`/
     * `group_id` conditionally (only when positive, unlike
     * `khotabItemsDefault()`'s unconditional IF-005 shape), requires
     * `pdf > 0`, orders by `pdf_time DESC`, joins series (for a series
     * title column) and channel, NOT authors (author.php already has the
     * author in scope and doesn't need it re-joined).
     */
    public function khotabPdfItemsByAuthor(int $authorId, int $serId, int $groupId, bool $includeHidden = false): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_series as ser', 'kh.ser_id', '=', 'ser.id')
            ->leftJoin('nuke_sat_channels as ch', 'kh.channel_id', '=', 'ch.id')
            ->where('kh.pdf', '>', 0)
            ->orderByDesc('kh.pdf_time')
            ->select([
                'kh.id', 'kh.ser_id', 'kh.channel_id', 'ser.title as series', 'ch.title as channel',
                'kh.title', 'kh.comments', 'kh.pdf_time as time', 'kh.hits',
            ]);

        if ($authorId > 0) {
            $query->where('kh.author', $authorId);
        }

        if ($serId > 0) {
            $query->where('kh.ser_id', $serId);
        }

        if ($groupId > 0) {
            $query->where('kh.group_id', $groupId);
        }

        if (! $includeHidden) {
            $query->where('kh.hidden', '0');
        }

        return $query->get();
    }

    // ---- Wave 3: channels/functions.php's channel-scoped variants -----

    /** channels/functions.php ListGroup() — filter by channel, optional author, joins authors, no hidden override. */
    public function groupsByChannel(int $channelId, int $authorId, bool $video): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_groups as grp')
            ->leftJoin('nuke_islamic_authors as ath', 'grp.author_id', '=', 'ath.id')
            ->where('grp.channel_id', $channelId)
            ->where('grp.vedio', (int) $video)
            ->where('grp.hidden', '0')
            ->where('grp.count', '>', 0)
            ->orderByDesc('grp.title')
            ->select(['grp.id', 'grp.channel_id', 'grp.title', 'grp.count', 'ath.id as author_id', 'ath.name as author']);

        if ($authorId > 0) {
            $query->where('grp.author_id', $authorId);
        }

        return $query->get();
    }

    /** channels/functions.php ListSeries() — filter by channel, optional author, joins authors, no hidden override. */
    public function seriesByChannel(int $channelId, int $authorId, bool $video): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_series as ser')
            ->leftJoin('nuke_islamic_authors as ath', 'ser.author_id', '=', 'ath.id')
            ->where('ser.channel_id', $channelId)
            ->where('ser.vedio', (int) $video)
            ->where('ser.hidden', '0')
            ->where('ser.count', '>', 0)
            ->orderByDesc('ser.lastupdate')
            ->select(['ser.id', 'ser.channel_id', 'ser.title', 'ser.time', 'ser.lastupdate', 'ser.count', 'ath.id as author_id', 'ath.name as author']);

        if ($authorId > 0) {
            $query->where('ser.author_id', $authorId);
        }

        return $query->get();
    }

    /**
     * channels/functions.php ListKhotab() — filter by channel, optional
     * author, ser_id/group_id filtered UNCONDITIONALLY (IF-005-shaped,
     * found again here), orders by time only (no weight).
     */
    public function khotabItemsByChannel(int $channelId, int $authorId, int $serId, int $groupId, bool $video): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_advanced as ad', 'kh.id', '=', 'ad.id')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->where('kh.channel_id', $channelId)
            ->where('kh.vedio', (int) $video)
            ->where('kh.ser_id', $serId)
            ->where('kh.group_id', $groupId)
            ->where('kh.hidden', '0')
            ->orderByDesc('kh.time')
            ->select([
                'kh.id', 'kh.author as author_id', 'ath.name as author', 'kh.title',
                'kh.comments', 'kh.time', 'kh.hits', 'ad.adur',
            ]);

        if ($authorId > 0) {
            $query->where('kh.author', $authorId);
        }

        return $query->get();
    }

    // ---- Wave 4: khotab/search.php's advanced search ----

    /**
     * `khotab/search.php`'s `ListSearchSeries()` — public branch only (the
     * admin branch, which drops the `hidden='0' AND count>'0'` filter, is
     * out of scope per this controller's public-only decision, same as
     * every other Wave 4 khotab controller). Filters: `title` (LIKE,
     * caller's responsibility to enforce the ≥4-char rule — IF-024),
     * `channel_id`, `author_id`, `[start, end]` time range. Always
     * `vedio='1'` — legacy's advanced search has never had an audio
     * equivalent (the page is hardcoded "البحث المتقدم في المرئيات").
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, start?: int, end?: int}  $filters
     */
    /**
     * G-05 addition: `$validateChannelExists` — see
     * `applyAdvancedSearchFilters()`'s own docblock. Defaults to `false`
     * so `KhotabSearchController`'s existing call is unaffected; only
     * `SearchController` (the `advanced-search/index.php` port) opts in.
     */
    public function khotabSeriesAdvancedSearch(array $filters, bool $validateChannelExists = false): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_series as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author_id', '=', 'tb2.id')
            ->where('tb1.vedio', '1')
            ->where('tb1.hidden', '0')
            ->where('tb1.count', '>', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.time', 'tb1.count', 'tb1.hidden', 'tb1.channel_id', 'tb1.author_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author_id', 'tb1.time', $filters, $validateChannelExists);

        return $query->orderByDesc('tb1.id')->paginate(20);
    }

    /**
     * `khotab/search.php`'s `ListSearchKhotab()`, public branch. IF-018's
     * fix: exposes the author id as `author` (the real, selected column —
     * legacy's own query never selects `author_id` here), not the
     * undefined `author_id` legacy's link-building code incorrectly read.
     *
     * G-05 additions, both defaulted to preserve `KhotabSearchController`'s
     * existing, unmodified call exactly:
     * - `$orderBy`: `advanced-search/index.php`'s `media_search()` config
     *   orders this department by `tb1.weight DESC`, not `tb1.time DESC`
     *   (`khotab/search.php`'s own convention, kept as this method's
     *   default). `SearchController` passes `'tb1.weight'` explicitly —
     *   the already-approved reuse of this method is kept, only its
     *   ordering is made department-source-accurate for that caller.
     * - `$validateChannelExists`: see `applyAdvancedSearchFilters()`.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, start?: int, end?: int}  $filters
     */
    public function khotabAdvancedSearch(array $filters, string $orderBy = 'tb1.time', bool $validateChannelExists = false): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author', '=', 'tb2.id')
            ->where('tb1.vedio', '1')
            ->where('tb1.hidden', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.author', 'tb1.hits', 'tb1.time', 'tb1.weight', 'tb1.channel_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author', 'tb1.time', $filters, $validateChannelExists);

        return $query->orderByDesc($orderBy)->paginate(20);
    }

    // ---- Post-Wave-4: chat_room's lesson-browsing half (task 4.11, see
    // docs/reviews/gap-closure-action-plan.md item 4) ----

    /**
     * `chat_room/functions.php`'s own `ListGroup($id, $location)` —
     * filters through `nuke_islamic_groups_location` (author_id = $id,
     * location_id = $location), a junction table none of this class's
     * other `groupsBy*()` methods use. A sixth independent
     * reimplementation of the "list groups" shape (P-011).
     */
    public function groupsByAuthorAndLocation(int $authorId, int $locationId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_groups as grp')
            ->join('nuke_islamic_groups_location as loc', 'loc.group_id', '=', 'grp.id')
            ->where('grp.author_id', $authorId)
            ->where('loc.location_id', $locationId)
            ->where('grp.hidden', '0')
            ->orderByDesc('grp.id')
            ->select(['grp.id', 'grp.title', 'grp.time', 'grp.channel_id', 'loc.count'])
            ->get();
    }

    /** `chat_room/functions.php`'s `ListSeries($id, $group_id, $location)` — filters through `nuke_islamic_series_location`, same shape as `groupsByAuthorAndLocation()` above. */
    public function seriesByAuthorAndLocation(int $authorId, int $groupId, int $locationId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_series as ser')
            ->join('nuke_islamic_series_location as loc', 'loc.series_id', '=', 'ser.id')
            ->where('ser.author_id', $authorId)
            ->where('ser.group_id', $groupId)
            ->where('loc.location_id', $locationId)
            ->where('ser.hidden', '0')
            ->orderByDesc('ser.id')
            ->select(['ser.id', 'ser.title', 'ser.time', 'ser.channel_id', 'loc.count'])
            ->get();
    }

    /**
     * `chat_room/functions.php`'s `ListKhotab($id, $ser_id, $group_id,
     * $location)` — filters `nuke_islamic_khotab` directly by
     * `location_id` (no junction table for items, unlike groups/series
     * above), plus author/ser_id/group_id unconditionally (same
     * IF-005-shaped unconditional-filter pattern already confirmed for
     * `khotabItemsDefault()`/`khotabItemsByChannel()`). Orders by
     * `weight DESC`, unlike this class's category-scoped khotab methods
     * (`time` only).
     */
    public function khotabItemsByAuthorAndLocation(int $authorId, int $serId, int $groupId, int $locationId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->where('kh.author', $authorId)
            ->where('kh.ser_id', $serId)
            ->where('kh.group_id', $groupId)
            ->where('kh.location_id', $locationId)
            ->where('kh.hidden', '0')
            ->orderByDesc('kh.weight')
            ->select(['kh.id', 'kh.title', 'kh.author', 'kh.link', 'kh.hits', 'kh.downcount', 'kh.time', 'kh.ser_id'])
            ->get();
    }

    /**
     * `chat_room/alhedaya_room.php:79-81`'s author-listing query — a
     * genuinely different shape from the 3 above (author-*of*-a-location,
     * not author-*at*-a-location's groups/series/items): joins
     * `nuke_islamic_authors_location` for the location-scoped `count`,
     * ordered `BINARY name ASC`. That file hardcodes `location=10`; this
     * method is the generalization used by `LocationController::show()`
     * for any location (Wave C, "Public Locations & Da'wah Registration
     * Surfaces").
     */
    public function authorsByLocation(int $locationId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_authors as auth')
            ->join('nuke_islamic_authors_location as loc', 'loc.author_id', '=', 'auth.id')
            ->where('loc.location_id', $locationId)
            ->where('auth.hidden', '0')
            ->orderBy('auth.name')
            ->select(['auth.id', 'auth.name', 'auth.prename', 'loc.count'])
            ->get();
    }

    // ---- Fatawa (Roadmap task 6.1) ------------------------------------

    /**
     * `fatawa/functions.php:354-359` `get_all_tasnifat($id)` — fatwa topics
     * directly under one category. `$id` is a `nuke_w2a_cat` category id
     * (`FatwaTopic::category()`, not self-referential — see that model's
     * docblock). Legacy has no `ORDER BY` on this query — none added here
     * either, matching exactly rather than inventing an ordering.
     *
     * Legacy paginates via `.htaccess`'s confirmed `fatawa-topics-{cat_id}-{page}.htm`
     * (the *only* rule for this page — no 1-parameter variant exists) and
     * `w2a_config.php`'s manual `$perpage=25`/`$offset` mechanism —
     * reproduced here as Laravel's own `paginate()`, per the approved
     * technical plan's explicit instruction not to recreate the
     * page/offset pattern that was the subject of decision-log #17's fix.
     */
    public function fatwaTopicsByCategory(int $categoryId, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::connection('main')->table('nuke_fatwa_topics')
            ->where('parent_id', $categoryId)
            ->paginate(25, ['*'], 'page', $page);
    }

    /**
     * `fatawa/functions.php:403-408` `get_all_questions($id, $cat_id)` —
     * general questions under one topic. **Confirmed exact-match, not a
     * `LIKE` multi-membership match**: legacy's own query is
     * `WHERE topic_id='|{$id}|'`, matching only general questions whose
     * `topic_id` string is *exactly* one pipe-wrapped id — a
     * single-topic-only match, despite the column's pipe-delimited
     * multi-membership *storage* format (Option A, preserved as found,
     * not "corrected" to a `LIKE '%|id|%'` match this legacy function
     * never actually performs). No `ORDER BY` in legacy — none added here.
     *
     * Paginated the same way as `fatwaTopicsByCategory()` above — see
     * that method's docblock.
     */
    public function fatwaGeneralQuestionsByTopic(int $topicId, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::connection('main')->table('nuke_fatwa_general_questions')
            ->where('topic_id', "|{$topicId}|")
            ->paginate(25, ['*'], 'page', $page);
    }

    /**
     * `fatawa/answer.php:62-67` / `answer2.php` (identical query, both
     * files — `fatawa.md` §5's confirmed near-duplicate finding) — every
     * scholar answer for one general question, joined with the answering
     * author. Same exact-match convention as above:
     * `question.general_question_id='|".$q."|'`. This is the shared query
     * behind both `single.php`'s one-answer view and `answer.php`/`answer2.php`'s
     * all-answers view — the two legacy files differ only in markup, not
     * in this query (`fatawa.md` §5), so one service method serves both.
     */
    public function fatwaQuestionsForGeneralQuestion(int $generalQuestionId): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions as q')
            ->join('nuke_islamic_authors as auth', 'auth.id', '=', 'q.auther_id')
            ->where('q.general_question_id', "|{$generalQuestionId}|")
            ->select(['q.*', 'auth.name as author_name', 'auth.prename as author_prename'])
            ->get();
    }

    /** `more.php:8` — latest 50 individual answers, joined with the answering author, no pagination in legacy (a flat `LIMIT 50`, no `page`/`offset` — reproduced exactly, no `.htaccess` page parameter exists for `more-fatawa.htm` either). */
    public function fatwaLatestQuestions(int $limit = 50): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions as f')
            ->join('nuke_islamic_authors as auth', 'f.auther_id', '=', 'auth.id')
            ->orderByDesc('f.id')
            ->limit($limit)
            ->select(['f.*', 'auth.id as auth_id', 'auth.prename as auth_prename', 'auth.name as auth_name'])
            ->get();
    }

    /**
     * `fatawa/functions.php:454-461` `get_all_questions_date($date)` —
     * individual answers (`nuke_fatwa_questions`, not the general-question
     * table — confirmed by the query's own column selection and join)
     * added on one exact calendar date, joined with the answering author.
     * `$date` must already be normalized to `Y-m-d` by the caller (legacy
     * does this via `date('Y-m-d', strtotime($date))` before calling this
     * function, `fatwa-today.php:16` — reproduced the same way in
     * `FatwaDayController`, not inside this method).
     */
    public function fatwaQuestionsByDate(string $date, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::connection('main')->table('nuke_fatwa_questions as q')
            ->join('nuke_islamic_authors as auth', 'q.auther_id', '=', 'auth.id')
            ->where('q.db_insertion_date', $date)
            ->select(['q.*', 'auth.id as auth_id', 'auth.prename as auth_prename', 'auth.name as auth_name'])
            ->paginate(25, ['*'], 'page', $page);
    }

    /**
     * `fatawa/fatwa-today.php:26-28` — the "featured questions" box: 4
     * individual answers picked via a hardcoded random `OFFSET`
     * (`rand(1,7400)`), joined to their topic by a **plain equality**
     * `question.topic_id=topic.id` (confirmed: `nuke_fatwa_questions.topic_id`
     * is a plain integer reference here, not the pipe-delimited format
     * `general_question_id`/`nuke_fatwa_general_questions.topic_id` use —
     * a genuinely different column shape on the same table). **The `7400`
     * ceiling is legacy's own hardcoded assumption about table size,
     * reproduced exactly, not replaced with a `COUNT()`-derived or
     * `ORDER BY RAND()` alternative** — a crude mechanism, preserved as
     * found per Behavior First.
     */
    public function fatwaRandomFeatured(int $limit = 4, int $offsetCeiling = 7400): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions as question')
            ->join('nuke_fatwa_topics as topic', 'question.topic_id', '=', 'topic.id')
            ->limit($limit)
            ->offset(random_int(1, $offsetCeiling))
            ->select(['question.*', 'topic.parent_id'])
            ->get();
    }

    /**
     * `fatawa-channels.php:23-24` — channels that have at least one fatwa
     * question, ordered by title. **`$perpage=30` here, not the sitewide
     * default 25** (`fatawa-channels.php:5`, a local override confirmed by
     * direct re-read — preserved, not normalized to 25).
     */
    public function fatwaChannelsWithQuestions(int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::connection('main')->table('nuke_sat_channels')
            ->whereIn('id', function ($query) {
                $query->select('channel_id')->distinct()->from('nuke_fatwa_questions');
            })
            ->orderBy('title')
            ->paginate(30, ['*'], 'page', $page);
    }

    /**
     * `fatawa-by-authers.php:22-31`'s default (`else`) branch — the ONLY
     * branch reachable via the `fatawa-by-authers.htm` pretty URL, since
     * the missing `modules.php` dispatcher never sets `$_GET['op']` to
     * `video`/`audio`/`pdf` for this route (Phase 1 audit, G-07-03). The
     * `video`/`audio`/`pdf` branches have real source but no reachable
     * route — deliberately not built here (would invent reachability that
     * doesn't exist).
     *
     * **Reproduced exactly, including two easy-to-miss omissions confirmed
     * by direct re-read, not fixed:** no `hidden=0` filter (unlike the
     * other 3 branches, which do filter on it) — an author with `hidden=1`
     * but at least one fatwa answer still appears here. Ordered by plain
     * `name ASC` (collation-based), NOT `ORDER BY BINARY name ASC` like
     * the other 3 branches — a genuinely different, narrower ordering rule
     * for this specific branch only.
     */
    public function fatwaAuthorsWithQuestions(): Collection
    {
        // Eloquent, not the query builder, so the view can call
        // Author::fallbackImageUrl() on real model instances
        // (get_author_img()'s own reproduction) — same join/group/order
        // shape either way.
        return Author::query()
            ->join('nuke_fatwa_questions', 'nuke_fatwa_questions.auther_id', '=', 'nuke_islamic_authors.id')
            ->groupBy('nuke_islamic_authors.id')
            ->orderBy('nuke_islamic_authors.name')
            ->select([
                'nuke_islamic_authors.*',
                DB::raw('COUNT(nuke_fatwa_questions.id) as count'),
            ])
            ->get();
    }

    /**
     * `fatawa/functions.php:524-534` `get_all_channel_questions($id)` — a
     * genuinely multi-step legacy query, reproduced in the same shape
     * rather than optimized into a single join (Behavior First; the N+1
     * per-row author lookup below is legacy's own shape, not introduced
     * here): (1) paginate individual answers by `channel_id`; (2) collect
     * their general-question ids (pipe-stripped); (3) fetch those general
     * questions, ordered by `question_text`; (4) for each, resolve its
     * topic and one matching author via legacy's own correlated-subquery
     * pattern (`functions.php:539`, `answer_text`/`channel_id`/
     * `general_question_id` match, `limit 1` — arbitrary if more than one
     * row matches, same as legacy).
     *
     * Legacy's raw `WHERE id IN ($ids)` would break (empty-parenthesis SQL
     * error) if no answers exist for this channel — not reproduced;
     * Laravel's `whereIn()` on an empty array safely returns no rows
     * instead, a correctness guard for an edge case, not an observable
     * behavior change for any channel that actually has content.
     */
    public function fatwaQuestionsForChannel(int $channelId, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $answers = DB::connection('main')->table('nuke_fatwa_questions')
            ->where('channel_id', $channelId)
            ->paginate(25, ['*'], 'page', $page);

        $generalQuestionIds = $answers->getCollection()
            ->map(fn ($row) => (int) str_replace('|', '', (string) $row->general_question_id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $generalQuestions = DB::connection('main')->table('nuke_fatwa_general_questions')
            ->whereIn('id', $generalQuestionIds)
            ->orderBy('question_text')
            ->get()
            ->map(function ($question) use ($channelId) {
                $topicId = (int) str_replace('|', '', (string) $question->topic_id);
                $question->topic = $topicId > 0
                    ? DB::connection('main')->table('nuke_fatwa_topics')->find($topicId)
                    : null;

                $question->author = DB::connection('main')->table('nuke_islamic_authors')
                    ->whereIn('id', function ($query) use ($channelId, $question) {
                        $query->select('auther_id')->from('nuke_fatwa_questions')
                            ->where('channel_id', $channelId)
                            ->where('general_question_id', "|{$question->id}|");
                    })
                    ->limit(1)
                    ->first();

                return $question;
            });

        $answers->setCollection($generalQuestions);

        return $answers;
    }

    /**
     * `fatawa/functions.php:622-634` `get_all_auther_questions($auther_id)`
     * — same multi-step shape as `fatwaQuestionsForChannel()` above, minus
     * the per-row author lookup (already known — it's this author).
     * **Legacy's own pagination `count` is the count of individual answer
     * rows, not the count of distinct general questions actually
     * returned** (`functions.php:629`, `count($allquestions)` before
     * dedup) — a pre-existing legacy inaccuracy, not reproduced here since
     * Laravel's own `paginate()` computes a correct total from the actual
     * result set; this is a pagination-count correctness fix, not a
     * content/reachability change.
     */
    public function fatwaGeneralQuestionsByAuthor(int $autherId, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $generalQuestionIds = DB::connection('main')->table('nuke_fatwa_questions')
            ->where('auther_id', $autherId)
            ->pluck('general_question_id')
            ->map(fn ($id) => (int) str_replace('|', '', (string) $id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return DB::connection('main')->table('nuke_fatwa_general_questions')
            ->whereIn('id', $generalQuestionIds)
            ->orderBy('question_text')
            ->paginate(25, ['*'], 'page', $page)
            ->through(function ($question) {
                $topicId = (int) str_replace('|', '', (string) $question->topic_id);
                $question->topic = $topicId > 0
                    ? DB::connection('main')->table('nuke_fatwa_topics')->find($topicId)
                    : null;

                return $question;
            });
    }

    // ---- Cross-department advanced search (Roadmap task 6.2) ----------
    //
    // `advanced-search/index.php`'s `Search` class, re-verified directly
    // (not from `advanced-search.md`'s summary alone) before implementing.
    // `title`/`channel`/`author` reuse `applyAdvancedSearchFilters()`
    // below unchanged. Date-range boundaries use a NEW helper,
    // `applyAdvancedSearchDateRange()`, not the existing one — see its own
    // docblock for the confirmed semantic discrepancy this resolves.
    // `title`/`from`/`to` are safely parameterized here (Eloquent/query
    // builder throughout, no raw SQL) — this is the Laravel-side search
    // *implementation*, distinct from and not a reopening of the deferred
    // legacy `advanced-search/index.php` security decision (decision-log
    // #17), which concerns only the legacy PHP file's own raw-concatenation
    // code and is untouched by this class.

    /**
     * Confirmed day-inclusive date-boundary semantic
     * (`advanced-search/index.php:928-931` etc.): `DATE(FROM_UNIXTIME(col))
     * >= DATE('$from') AND <= DATE('$to')` — truncates both the column and
     * the boundary to a calendar day, so the entire `$to` day matches, not
     * just up to midnight. **Not implemented via a raw `DATE(FROM_UNIXTIME())`
     * SQL call** (MySQL-specific, and untestable against this project's
     * SQLite-based test fixtures) — instead computed in PHP as the
     * `$from` day's start (`00:00:00`) through the `$to` day's end
     * (`23:59:59`), applied via portable `where()` calls. This produces
     * the identical observable result (whole-day-inclusive boundaries) as
     * legacy's own `DATE()`-truncated SQL, via a portable technique.
     *
     * **Confirmed discrepancy from this class's existing `applyAdvancedSearchFilters()`
     * (used by `khotabAdvancedSearch()`/`khotabSeriesAdvancedSearch()`),
     * documented not silently carried over:** that method's `whereBetween($timeColumn,
     * [$filters['start'], $filters['end']])` compares against raw
     * `strtotime()` instants (both effectively midnight), which excludes
     * most of the `end` day — a different, narrower boundary than
     * `advanced-search/index.php`'s own confirmed semantic. `KhotabSearchController`
     * itself is unchanged by this finding; the new departments below use
     * this new, `advanced-search`-accurate helper instead.
     */
    private function applyAdvancedSearchDateRange(\Illuminate\Database\Query\Builder $query, string $timeColumn, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->where($timeColumn, '>=', strtotime($filters['from'].' 00:00:00'));
        }

        if (! empty($filters['to'])) {
            $query->where($timeColumn, '<=', strtotime($filters['to'].' 23:59:59'));
        }
    }

    /**
     * `advanced-search/index.php`'s `fatawa_search()` config + `Listmawad()`'s
     * mawad-side query — individual scholar answers (`nuke_fatwa_questions`).
     * `date_of_fatwa DESC` ordering. **No `hidden` filter** — confirmed:
     * neither `nuke_fatwa_questions` nor `nuke_fatwa_general_questions` has
     * a `hidden` column at all (`fatawa.md` §4's confirmed column lists),
     * matching `fatawa_search()`'s own `mawad_support_hidden = false`.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    /**
     * G-05 addition: `Listmawad()`'s fatawa-specific channel condition
     * (`index.php:922-923`) is richer than every other department's —
     * `channel_id IN (SELECT ...) OR place_of_fatwa LIKE '%X%'` — applied
     * here manually (channel excluded from the shared
     * `applyAdvancedSearchFilters()` call) since no other department has
     * this OR-extension. Confirmed this extra clause exists ONLY in
     * `Listmawad()`'s fatawa branch, not in `ListSeries()`'s (neither the
     * topics nor general-questions queries have it).
     */
    public function fatwaQuestionsAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_fatwa_questions as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.auther_id', '=', 'tb2.id')
            ->select(['tb1.*', 'tb2.name', 'tb2.prename']);

        $channelId = $filters['channel_id'] ?? null;
        $this->applyAdvancedSearchFilters($query, 'tb1.question_text', 'tb1.channel_id', 'tb1.auther_id', 'tb1.date_of_fatwa', [...$filters, 'channel_id' => null]);
        $this->applyAdvancedSearchDateRange($query, 'tb1.date_of_fatwa', $filters);

        if (! empty($channelId)) {
            $query->where(function ($sub) use ($channelId) {
                $sub->whereIn('tb1.channel_id', function ($inner) use ($channelId) {
                    $inner->select('id')->from('nuke_sat_channels')->where('id', $channelId);
                })->orWhere('tb1.place_of_fatwa', 'like', '%'.$channelId.'%');
            });
        }

        return $query->orderByDesc('tb1.date_of_fatwa')->paginate(20);
    }

    /**
     * `fatawa_search()`'s series-side query — general questions
     * (`nuke_fatwa_general_questions`). `num_view DESC` ordering, per
     * `series_order_by`. Same no-`hidden`-column reasoning as above.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    public function fatwaGeneralQuestionsAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_fatwa_general_questions as tb1')
            ->select(['tb1.*']);

        $this->applyAdvancedSearchFilters($query, 'tb1.question_text', 'tb1.channel_id', 'tb1.author_id', 'tb1.db_insertion_date', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.db_insertion_date', $filters);

        return $query->orderByDesc('tb1.num_view')->paginate(20);
    }

    /**
     * `advanced-search/index.php:1406-1414` — the fatawa department's
     * **third, extra** result set: `nuke_fatwa_topics` matching the
     * search title, ordered by `topic_name` (hardcoded — not
     * `series_order_by`; confirmed by direct re-read this pass).
     * `series_author_field` ("author_id") is applied against
     * `nuke_fatwa_topics.author_id` here — a real, distinct column from
     * `nuke_fatwa_questions.auther_id` (task 6.1's `FatwaQuestion`
     * docblock's previously-unresolved ambiguity — now resolved: see
     * decision-log for the full record). No file among `fatawa/`'s 16
     * renders this topics result, distinct from the `series_result`
     * general-questions query above; only `advanced-search/index.php`
     * itself does, for this department only.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    public function fatwaTopicsAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_fatwa_topics as tb1')
            ->select(['tb1.*']);

        $this->applyAdvancedSearchFilters($query, 'tb1.topic_name', 'tb1.channel_id', 'tb1.author_id', 'tb1.db_insertion_date', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.db_insertion_date', $filters);

        return $query->orderBy('tb1.topic_name')->paginate(20);
    }

    /**
     * G-05 (Migration Gap Register): `advanced-search/index.php`'s
     * `get_num_of_fatawa_for_question($g_q_id)` (`index.php:1007-1017`) —
     * `SELECT id FROM nuke_fatwa_questions WHERE general_question_id=%d`,
     * row count. Called once per `fatwaGeneralQuestionsAdvancedSearch()`
     * result row by `SearchController`'s view — a confirmed legacy N+1
     * pattern, reproduced exactly (not batched), per explicit instruction.
     * `general_question_id` is otherwise a pipe-wrapped string column
     * elsewhere in this codebase (`fatwaQuestionsForGeneralQuestion()`) —
     * this method reproduces legacy's own literal integer-placeholder
     * comparison as-is (MySQL's implicit string-to-int coercion applies
     * here exactly as it does in legacy's raw SQL; not special-cased).
     */
    public function countFatawaForQuestion(int $generalQuestionId): int
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->where('general_question_id', $generalQuestionId)
            ->count();
    }

    /**
     * `advanced-search/index.php`'s `get_num_of_general_questions_for_topic($t_id)`
     * (`index.php:418-427`) — `SELECT id FROM nuke_fatwa_general_questions
     * WHERE topic_id=%d`, row count. Same N+1-preserved reasoning as
     * `countFatawaForQuestion()` above — called once per
     * `fatwaTopicsAdvancedSearch()` result row.
     */
    public function countGeneralQuestionsForTopic(int $topicId): int
    {
        return DB::connection('main')->table('nuke_fatwa_general_questions')
            ->where('topic_id', $topicId)
            ->count();
    }

    /**
     * `advanced-search/index.php`'s `varieties_search()` config +
     * `Listmawad()`'s own second `switch($this->department)`
     * (`index.php:884-915`) — covers 5 legacy department values sharing
     * one query shape, discriminated only by a hardcoded `parent_id`:
     * `anasheed`=98, `sections`=16, `cartoon`=57, `documentary`=12,
     * `video_sections`=158 (`nuke_anasheed_anasheed.parent_id`,
     * cross-confirmed against `var-group-{id}.htm` links in task 6.1's
     * `sendemail.php` email template). `weight DESC` ordering.
     * `hidden = 0` filter applied (`mawad_support_hidden = true`,
     * confirmed real column on `AnasheedItem`).
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    public function anasheedAdvancedSearch(array $filters, int $parentId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_anasheed_anasheed as tb1')
            ->where('tb1.vedio', '1')
            ->where('tb1.parent_id', $parentId)
            ->where('tb1.hidden', '0')
            ->select(['tb1.*']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author_id', 'tb1.mytime', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.mytime', $filters);

        return $query->orderByDesc('tb1.weight')->paginate(20);
    }

    /**
     * `varieties_search()`'s series-side query — `nuke_anasheed_groups`,
     * same `parent_id` discriminator as above (`ListSeries()`'s own
     * switch, `index.php:1363-1377`). `id DESC` ordering (`series_order_by`).
     * **No `hidden` filter** (`series_support_hidden = false`) — confirmed:
     * no `hidden` column found on the existing `AnasheedGroup` model.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    public function anasheedGroupAdvancedSearch(array $filters, int $parentId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_anasheed_groups as tb1')
            ->where('tb1.parent_id', $parentId)
            ->select(['tb1.*']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author_id', 'tb1.time', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.time', $filters);

        return $query->orderByDesc('tb1.id')->paginate(20);
    }

    /**
     * **New method, `khotabAdvancedSearch()`/`khotabSeriesAdvancedSearch()`
     * NOT modified or reused for this department.** Confirmed discrepancy:
     * `media_search()`'s config covers `video`/`audio`/`dumped_files`
     * together, but `khotabAdvancedSearch()` (task 4.1) hardcodes
     * `WHERE vedio='1'` unconditionally — it only ever implements the
     * `video` case. `audio` (`vedio='0'`) has no existing method. Added
     * here as a new, parallel method rather than modifying the existing
     * one, per explicit instruction not to touch `KhotabSearchController`
     * behavior. Same fields/ordering shape as `khotabAdvancedSearch()`.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    /**
     * G-05 fix: ordered `tb1.weight DESC`, matching `media_search()`'s
     * `mawad_order_by` (`index.php:510`, shared by video/audio/
     * dumped_files) exactly — this method is `SearchController`-exclusive
     * (no `KhotabSearchController` caller exists), so the ordering is
     * corrected directly rather than parameterized. `$validateChannelExists`
     * always applied (`true`, hardcoded) for the same reason — see
     * `applyAdvancedSearchFilters()`'s docblock.
     */
    public function khotabAudioAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author', '=', 'tb2.id')
            ->where('tb1.vedio', '0')
            ->where('tb1.hidden', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.author', 'tb1.hits', 'tb1.time', 'tb1.weight', 'tb1.channel_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author', 'tb1.time', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.time', $filters);

        return $query->orderByDesc('tb1.weight')->paginate(20);
    }

    /** Series-side counterpart to `khotabAudioAdvancedSearch()` — ordering already matched (`tb1.id DESC`, `series_order_by`), unchanged; `$validateChannelExists` always applied. */
    public function khotabAudioSeriesAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_series as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author_id', '=', 'tb2.id')
            ->where('tb1.vedio', '0')
            ->where('tb1.hidden', '0')
            ->where('tb1.count', '>', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.time', 'tb1.count', 'tb1.hidden', 'tb1.channel_id', 'tb1.author_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author_id', 'tb1.time', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.time', $filters);

        return $query->orderByDesc('tb1.id')->paginate(20);
    }

    /**
     * `dumped_files` department — `vedio='1' AND pdf > 0` (`Listmawad()`'s
     * switch, `index.php:885-887`). **No series-side method exists for
     * this department** — legacy's own `ListSeries()` switch
     * (`index.php:1354-1356`) deliberately forces `tb1.id < 0` for
     * `dumped_files` ("to avoid getting series in dumped files", legacy's
     * own comment) — an always-false condition, confirming there is no
     * series concept for this department. Not reproduced as a fake
     * always-empty method; simply not offered.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, from?: string, to?: string}  $filters
     */
    /** G-05 fix: ordered `tb1.weight DESC` (same `media_search()` config, same reasoning as `khotabAudioAdvancedSearch()`'s docblock). `$validateChannelExists` always applied. */
    public function khotabDumpedFilesAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author', '=', 'tb2.id')
            ->where('tb1.vedio', '1')
            ->where('tb1.pdf', '>', 0)
            ->where('tb1.hidden', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.author', 'tb1.hits', 'tb1.time', 'tb1.weight', 'tb1.channel_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author', 'tb1.time', $filters, true);
        $this->applyAdvancedSearchDateRange($query, 'tb1.time', $filters);

        return $query->orderByDesc('tb1.weight')->paginate(20);
    }

    /**
     * `pages/ramadan.php`'s own 13 year-bucketed queries (Roadmap task
     * 6.3) — one boundary per year, copied verbatim from the confirmed-
     * authoritative file (Task 6.3 investigation §13.2: `ramadan.php`, NOT
     * `ramadan-archive.php`'s duplicate-bugged "1446"/"1445" boundary, and
     * NOT `ramadan1442.php`'s superseded snapshot). `ramadan.php` has no
     * 1445 section at all — its "1446" section's upper time bound already
     * subsumes what `ramadan-archive.php` split into two identical, buggy
     * sections — so 1445 is intentionally absent from this map, not an
     * omission.
     *
     * Returns `[year => Collection]`, ordered 1447 -> 1434 (array
     * insertion order), matching the legacy page's top-to-bottom render
     * order. Each row carries the same columns `ramadan.php`'s query
     * selects: id/title/count/vedio/time/lastupdate/channel_id/author_id
     * plus the joined author's prename/name.
     */
    public function ramadanSeriesByYear(): array
    {
        // ramadan.php:76,184 — `$start2026`/`$end2025` are, confirmed by
        // direct read, the exact same `strtotime()` call under two
        // different variable names. Computed once here for both.
        $threshold = strtotime('2026-02-09 00:00:00');

        $conditions = [
            1447 => fn ($q) => $q->where('ser.time', '>=', $threshold),
            1446 => fn ($q) => $q->where('ser.id', '>', 14642)->where('ser.time', '<', $threshold),
            1444 => fn ($q) => $q->where('ser.id', '>', 13228)->where('ser.id', '<', 14643),
            1443 => fn ($q) => $q->where('ser.id', '>', 11223)->where('ser.id', '<', 13228),
            1442 => fn ($q) => $q->where('ser.id', '>', 10943)->where('ser.id', '<', 11223),
            1441 => fn ($q) => $q->where('ser.id', '>', 9621)->where('ser.id', '<=', 10943),
            1440 => fn ($q) => $q->where('ser.id', '>', 9408)->where('ser.id', '<=', 9621),
            1439 => fn ($q) => $q->where('ser.id', '>', 8982)->where('ser.id', '<', 9409),
            1438 => fn ($q) => $q->where('ser.id', '>', 8033)->where('ser.id', '<', 8982),
            1437 => fn ($q) => $q->where('ser.id', '>', 7840)->where('ser.id', '<', 8033),
            1436 => fn ($q) => $q->where('ser.id', '>', 7204)->where('ser.id', '<', 7841),
            1435 => fn ($q) => $q->where('ser.id', '>', 5331)->where('ser.id', '<', 7205),
            1434 => fn ($q) => $q->where('ser.id', '<', 5332),
        ];

        $results = [];

        foreach ($conditions as $year => $condition) {
            $query = DB::connection('main')->table('nuke_islamic_series as ser')
                ->join('nuke_islamic_authors as auth', 'auth.id', '=', 'ser.author_id')
                ->where('ser.ramadan', '1')
                ->where('ser.hidden', '0')
                ->select(['ser.id', 'ser.title', 'ser.count', 'ser.vedio', 'ser.time', 'ser.lastupdate', 'ser.channel_id', 'ser.author_id', 'auth.prename', 'auth.name']);

            $condition($query);

            $results[$year] = $query->orderByDesc('ser.id')->get();
        }

        return $results;
    }

    /**
     * Shared filter-application shape behind the advanced-search methods
     * — only the column names differ (series uses `author_id`, khotab
     * items use `author`, per each table's real schema).
     *
     * G-05 (Migration Gap Register) addition: `$validateChannelExists`
     * reproduces `advanced-search/index.php`'s own channel condition —
     * `Listmawad()`/`ListSeries()`: `tb1.channel_id IN (SELECT id FROM
     * nuke_sat_channels WHERE id=X)`, not a plain equality — confirmed
     * this is `advanced-search/index.php`'s literal query shape,
     * genuinely different from `khotab/search.php`'s own plain-equality
     * `ListSearchKhotab()`/`ListSearchSeries()`. Defaults to `false`
     * (plain equality) so `khotabAdvancedSearch()`/
     * `khotabSeriesAdvancedSearch()`'s existing `KhotabSearchController`
     * callers are completely unaffected — only `SearchController`'s own
     * calls opt in.
     */
    private function applyAdvancedSearchFilters(
        \Illuminate\Database\Query\Builder $query,
        string $titleColumn,
        string $channelColumn,
        string $authorColumn,
        string $timeColumn,
        array $filters,
        bool $validateChannelExists = false,
    ): void {
        if (! empty($filters['title'])) {
            $query->where($titleColumn, 'like', '%'.$filters['title'].'%');
        }

        if (! empty($filters['channel_id'])) {
            if ($validateChannelExists) {
                $channelId = $filters['channel_id'];
                $query->whereIn($channelColumn, function ($sub) use ($channelId) {
                    $sub->select('id')->from('nuke_sat_channels')->where('id', $channelId);
                });
            } else {
                $query->where($channelColumn, $filters['channel_id']);
            }
        }

        if (! empty($filters['author_id'])) {
            $query->where($authorColumn, $filters['author_id']);
        }

        if (! empty($filters['start']) && ! empty($filters['end'])) {
            $query->whereBetween($timeColumn, [$filters['start'], $filters['end']]);
        }
    }

    // ============================================================================
    // G-02 (Homepage Migration Blueprint) — `index.php`/`new_content.php`/
    // `home_functions.php`. These 9 methods back the homepage's own 17
    // sections (several sections share a method: fatawa/telawah/videos/
    // audios/dump-files/albums/category-487/trending are each single
    // methods; parent-scoped anasheed backs 3 sections via one method).
    // ============================================================================

    /**
     * `home_functions.php:4-69`'s `list_latest_videos()`. Derived-table
     * structure preserved verbatim (own comment: avoids a real MySQL 5.7
     * join-order regression, not incidental style) — do not simplify to a
     * plain join. Cached 300s exactly as legacy's `SimpleCache` TTL.
     *
     * Visual/CSS parity phase — homepage HTTP 500 investigation: this
     * method's own prior comment ("cached payload is a plain array,
     * never a raw Collection — the G-06 khotab/search lesson applies
     * here too") was itself incomplete, not just wrong about the fix —
     * `->get()->all()` is an array of **stdClass objects**, not the
     * plain-scalar-array shape `KhotabSearchController::rememberSafely()`
     * (the actual G-06/G-09-02 fix) uses. Confirmed root cause: this
     * app's `config('cache.serializable_classes')` is `false` (Laravel's
     * own secure-by-default setting, `config/cache.php:141`), so the
     * `file` cache store's `unserialize($value, ['allowed_classes' =>
     * false])` converts **every** cached object — including harmless
     * `stdClass`, not just Eloquent models — into `__PHP_Incomplete_Class`
     * on every cache read. Reproduced deterministically: a fresh
     * `Cache::forget()` + first request always succeeds (closure runs,
     * no unserialize involved); every request after that fails 100% of
     * the time, not intermittently. Not reproducible under
     * `CACHE_STORE=array` (no serialization occurs at all), which is why
     * the test suite never caught it. Fixed the same way
     * `rememberSafely()` already does: cache plain arrays
     * (`(array) $row`), rehydrate to `stdClass` via `(object)` cast
     * after every read — that cast happens in PHP, never through
     * `unserialize()`, so it's unaffected by `serializable_classes`.
     */
    public function homeLatestVideos(): Collection
    {
        $rows = Cache::remember('home-latest-videos', 300, function () {
            return DB::connection('main')->query()->fromSub(function ($query) {
                $query->select(['id', 'time', 'ser_id', 'title', 'frame', 'author', 'lastmirror'])
                    ->from('nuke_islamic_khotab')
                    ->where('vedio', '1')
                    ->where('newslist', '1')
                    ->orderByDesc('lastmirror')
                    ->limit(3);
            }, 'kh')
                ->join('nuke_islamic_authors as th', 'kh.author', '=', 'th.id')
                ->orderByDesc('kh.lastmirror')
                ->select(['kh.id', 'kh.time', 'kh.ser_id', 'kh.title', 'kh.frame', 'th.id as thid', 'th.prename', 'th.name'])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        });

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    /**
     * `home_functions.php:285-326`'s `list_latest_audios()` — same
     * derived-table shape and cache reasoning as `homeLatestVideos()`,
     * `vedio = '0'` instead of `'1'`, limit 7 instead of 3. Same
     * `serializable_classes` cache fix applied — see `homeLatestVideos()`'s
     * own docblock for the full investigation.
     */
    public function homeLatestAudios(): Collection
    {
        $rows = Cache::remember('home-latest-audios', 300, function () {
            return DB::connection('main')->query()->fromSub(function ($query) {
                $query->select(['id', 'title', 'time', 'author', 'lastmirror'])
                    ->from('nuke_islamic_khotab')
                    ->where('vedio', '0')
                    ->where('newslist', '1')
                    ->orderByDesc('lastmirror')
                    ->limit(7);
            }, 'kh')
                ->join('nuke_islamic_authors as th', 'kh.author', '=', 'th.id')
                ->orderByDesc('kh.lastmirror')
                ->select(['kh.id', 'kh.title', 'kh.time', 'th.prename', 'th.name'])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        });

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    /**
     * `home_functions.php:347-400`'s `get_latest_dump_files()`. Deliberately
     * NOT `khotabPdfDump()` above (khotab/dump.php's own "latest 50 PDFs"
     * listing) — confirmed during the Blueprint's infrastructure inspection
     * that query additionally filters `hidden = '0'` (this homepage
     * function has no such filter), doesn't select `frame`, uses limit 50
     * not 3, isn't cached, and isn't a derived-table query — 4 real
     * differences, not safely reusable without silently changing homepage
     * behavior. Cache key includes `$limit` exactly as legacy's own
     * `SimpleCache` key does. Same `serializable_classes` cache fix
     * applied — see `homeLatestVideos()`'s own docblock for the full
     * investigation.
     */
    public function homeLatestDumpFiles(int $limit = 3): Collection
    {
        $rows = Cache::remember("home-latest-dump-files-{$limit}", 300, function () use ($limit) {
            return DB::connection('main')->query()->fromSub(function ($query) use ($limit) {
                $query->select(['id', 'title', 'frame', 'author', 'pdf_time'])
                    ->from('nuke_islamic_khotab')
                    ->where('pdf', '>', 0)
                    ->orderByDesc('pdf_time')
                    ->limit($limit);
            }, 'kh')
                ->join('nuke_islamic_authors as th', 'th.id', '=', 'kh.author')
                ->orderByDesc('kh.pdf_time')
                ->select(['kh.id', 'kh.title', 'kh.frame', 'th.id as thid', 'th.prename', 'th.name'])
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        });

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    /**
     * `home_functions.php:70-93`'s `list_latest_fatawas()`. Not cached —
     * legacy doesn't cache this one. `general_question_id`'s pipe-prefix
     * parsing is NOT done here (a rendering-time concern, same table shape
     * as the raw column) — see `home.blade.php`'s fatawa partial.
     */
    public function homeLatestFatawas(int $limit = 3): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions as f')
            ->join('nuke_islamic_authors as th', 'f.auther_id', '=', 'th.id')
            ->orderByDesc('f.id')
            ->limit($limit)
            ->select(['f.general_question_id', 'f.question_text', 'th.prename', 'th.name'])
            ->get();
    }

    /**
     * `home_functions.php:94-181`'s `list_latest_cat_487()`. The 10
     * hardcoded category-id -> logo mappings are NOT reproduced here (a
     * rendering-time concern) — see `home.blade.php`'s cat-487 partial.
     */
    public function homeCategory487(int $limit = 3): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('main_cat', 487)
            ->orderByDesc('id')
            ->limit($limit)
            ->select(['id', 'title'])
            ->get();
    }

    /** `home_functions.php:259-284`'s `list_latest_telawahs()`. Not cached — legacy doesn't cache this one. */
    public function homeLatestTelawahs(int $limit = 7): Collection
    {
        return DB::connection('main')->table('nuke_telawah_telawah as t')
            ->leftJoin('nuke_telawah_groups as g', 't.group_id', '=', 'g.id')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->select(['t.id', 't.title', 'g.title as group_title'])
            ->get();
    }

    /**
     * `home_functions.php:238-258`'s `list_latest_albums()`. Reuses the
     * existing `SiteOption` model (`nuke_options`, already built for
     * Wave 5's soundcloud/youtube settings) for the
     * `home_selected_album` lookup — same table, same key convention.
     * The 242x197 `thumbnails.php` resize itself is deliberately NOT done
     * here (Blueprint §10: homepage-scoped, not a generic thumbnail
     * service) — see `HomeController`/`home.blade.php`.
     *
     * @return array{album_id: int, images: Collection}
     */
    public function homeSelectedAlbumImages(int $limit = 7): array
    {
        $albumId = (int) (SiteOption::get('home_selected_album') ?? 0);

        $images = DB::connection('main')->table('nuke_albums_images')
            ->where('album_id', $albumId)
            ->orderBy('order')
            ->limit($limit)
            ->select(['url'])
            ->get();

        return ['album_id' => $albumId, 'images' => $images];
    }

    /**
     * G-13-06 (media/visual parity phase) — `slider.php` (included from
     * `header.php:532-535`, gated behind `$display_slider`, set `true`
     * only by `index.php:6` — confirmed via exhaustive grep, every other
     * file's own copy of that assignment is commented out). Query
     * reproduced exactly: `nuke_7amalat WHERE status=1 AND website=1
     * ORDER BY order_index ASC LIMIT 10`. `image` already stores the full
     * relative path (`media/7amlat/slide_*.jpg`), not a bare filename —
     * no bucketing/transformation needed, unlike `MediaPathResolver`'s
     * convention elsewhere.
     */
    public function homeSliderItems(): Collection
    {
        return DB::connection('main')->table('nuke_7amalat')
            ->where('status', 1)
            ->where('website', 1)
            ->orderBy('order_index')
            ->limit(10)
            ->select(['id', 'title', 'image', 'url'])
            ->get();
    }

    /**
     * `functions.php:195-224`'s `listvars()`, its `parent` filter branch —
     * backs homepage sections 6 (`parent=158`), 13 (`parent=12`), 14
     * (`parent=57`). Uses `AnasheedItem::scopeInParent()` (added for this
     * task) rather than `scopeInGroup()` — `parent_id`, not `group_id`,
     * confirmed by direct re-read of `listvars()` line 215-218. `class`
     * defaults to `'vars'` in every real call site here (none of the 3
     * homepage sections pass `'class'`), so only that rendering branch's
     * columns are selected.
     */
    public function homeAnasheedByParent(int $parentId, int $limit = 3): Collection
    {
        return AnasheedItem::query()
            ->from('nuke_anasheed_anasheed as an')
            ->leftJoin('nuke_anasheed_groups as gr', 'an.group_id', '=', 'gr.id')
            ->inParent($parentId)
            ->orderByDesc('an.id')
            ->limit($limit)
            ->select(['an.id', 'an.title', 'an.frame', 'gr.title as group_title'])
            ->get();
    }

    /**
     * `new_content.php`'s inline "تشاهدون الآن" query (no wrapping
     * function in legacy — the only homepage section built directly in
     * the template rather than via a `home_functions.php` helper):
     * `SELECT nuke_anasheed_anasheed.id, title, frame,
     * nuke_anasheed_advanced.adur FROM nuke_anasheed_anasheed,
     * nuke_anasheed_advanced WHERE nuke_anasheed_advanced.id =
     * nuke_anasheed_anasheed.id ORDER BY lastvisit DESC LIMIT 16` — an
     * implicit inner join, reproduced as an explicit one (same result set,
     * not a behavior change).
     */
    public function homeTrendingAnasheed(int $limit = 16): Collection
    {
        return DB::connection('main')->table('nuke_anasheed_anasheed as an')
            ->join('nuke_anasheed_advanced as ad', 'ad.id', '=', 'an.id')
            ->orderByDesc('an.lastvisit')
            ->limit($limit)
            ->select(['an.id', 'an.title', 'an.frame'])
            ->get();
    }
}
