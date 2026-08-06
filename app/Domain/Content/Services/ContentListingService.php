<?php

namespace App\Domain\Content\Services;

use Illuminate\Support\Collection;
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
            ->select([
                'kh.id', 'kh.channel_id', 'ch.title as channel', 'kh.title', 'kh.comments',
                'kh.time', 'kh.hits', 'kh.weight', 'ad.adur', 'ath.name', 'ath.prename',
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
    public function khotabSeriesAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_series as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author_id', '=', 'tb2.id')
            ->where('tb1.vedio', '1')
            ->where('tb1.hidden', '0')
            ->where('tb1.count', '>', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.time', 'tb1.count', 'tb1.hidden', 'tb1.channel_id', 'tb1.author_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author_id', 'tb1.time', $filters);

        return $query->orderByDesc('tb1.id')->paginate(20);
    }

    /**
     * `khotab/search.php`'s `ListSearchKhotab()`, public branch. IF-018's
     * fix: exposes the author id as `author` (the real, selected column —
     * legacy's own query never selects `author_id` here), not the
     * undefined `author_id` legacy's link-building code incorrectly read.
     *
     * @param  array{title?: string, channel_id?: int, author_id?: int, start?: int, end?: int}  $filters
     */
    public function khotabAdvancedSearch(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab as tb1')
            ->join('nuke_islamic_authors as tb2', 'tb1.author', '=', 'tb2.id')
            ->where('tb1.vedio', '1')
            ->where('tb1.hidden', '0')
            ->select(['tb1.id', 'tb1.title', 'tb1.author', 'tb1.hits', 'tb1.time', 'tb1.weight', 'tb1.channel_id', 'tb2.name', 'tb2.prename']);

        $this->applyAdvancedSearchFilters($query, 'tb1.title', 'tb1.channel_id', 'tb1.author', 'tb1.time', $filters);

        return $query->orderByDesc('tb1.time')->paginate(20);
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

    /** Shared filter-application shape behind the two advanced-search methods above — only the column names differ (series uses `author_id`, khotab items use `author`, per each table's real schema). */
    private function applyAdvancedSearchFilters(
        \Illuminate\Database\Query\Builder $query,
        string $titleColumn,
        string $channelColumn,
        string $authorColumn,
        string $timeColumn,
        array $filters,
    ): void {
        if (! empty($filters['title'])) {
            $query->where($titleColumn, 'like', '%'.$filters['title'].'%');
        }

        if (! empty($filters['channel_id'])) {
            $query->where($channelColumn, $filters['channel_id']);
        }

        if (! empty($filters['author_id'])) {
            $query->where($authorColumn, $filters['author_id']);
        }

        if (! empty($filters['start']) && ! empty($filters['end'])) {
            $query->whereBetween($timeColumn, [$filters['start'], $filters['end']]);
        }
    }
}
