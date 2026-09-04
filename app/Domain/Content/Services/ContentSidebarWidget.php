<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Support\MediaPathResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * TRACEABILITY (Blueprint v1.0 §4, P-016) — read before modifying
 * ============================================================================
 *
 * 1. EXACT LEGACY FUNCTIONS REPLACED
 *    - anasheed/functions.php: most_downloaded_list() [L842-861],
 *      most_recent_list() [L862-881] (the reference/original copy — its
 *      most_recent_html() [L882-910] builder is the one the others copied)
 *    - w2acd/functions.php: most_downloaded_list() [L181-196],
 *      most_recent_list() [L197-211]
 *    - telawah/functions.php: most_downloaded_list() [L309-329],
 *      most_recent_list() [L330-350]
 *    - live-stream/functions.php: most_viewed_list() [L145-168],
 *      most_recent_list() [L169-192]
 *    Four modules, 8 source functions, confirmed independently
 *    reimplemented (not shared) despite the near-identical names.
 *
 * 2. DIFFERENCES BETWEEN THE LEGACY IMPLEMENTATIONS
 *    - Tables/columns differ per module (expected — different content
 *      types): nuke_anasheed_anasheed (id, title, frame, downcount,
 *      mytime), nuke_w2acd_w2acd (id, title, hits, banner, thumbnail,
 *      mytime), nuke_telawah_telawah (id, title, hits, mytime),
 *      nuke_islamic_khotab (id, title, hits, channel_id — live-stream's
 *      copy queries khotab's own table, not a live-stream-specific one).
 *    - LIMIT differs per copy with no evident reason: anasheed uses 7,
 *      w2acd uses 6, telawah and live-stream use 10.
 *    - Optional filter differs by dimension: anasheed filters by
 *      `group_id` (only when a Group object is passed); live-stream
 *      filters by `channel_id`. telawah has no filter parameter at all.
 *      **w2acd accepts a `$Group` parameter but never uses it anywhere in
 *      the query — a dead parameter**, confirmed by reading the full
 *      function body, not assumed.
 *    - "Most recent" ordering differs: anasheed/w2acd/telawah order by a
 *      `mytime` column; live-stream orders by `id DESC` instead (khotab's
 *      table has no `mytime`-equivalent column available to this query).
 *    - Output/presentation differs sharply: anasheed and w2acd both call
 *      a shared `most_recent_html()` builder that renders a thumbnail per
 *      row (via `thumbnail()`/a comma-split `thumbnail` column
 *      respectively) — **w2acd's copy computes `$basefolder =
 *      floor($item->id/1000)` and then never uses it, a second dead
 *      value in the same function**, and instead builds its thumbnail
 *      path from the `thumbnail` column's first comma-separated segment
 *      under `images/cds_image2/`, not the `media/` MediaPathResolver
 *      convention at all. telawah and live-stream render plain text
 *      links with no thumbnail logic whatsoever.
 *    - URL prefix: anasheed and w2acd both link to `var-item-{id}.htm`
 *      (correct for anasheed's own URL scheme) — **w2acd's copy reusing
 *      this same prefix instead of its own `cds-item-{id}.htm` is a
 *      confirmed bug** (already cataloged in P-016/w2acd.md §5, not a
 *      new finding here, but directly relevant to what this service must
 *      NOT reproduce).
 *
 * 3. HOW THE DIFFERENCES WERE RECONCILED
 *    One explicitly-named public method per confirmed real function (8
 *    total), each with its own confirmed table/columns/filter/limit — not
 *    one generic method. A private query-building helper factors out only
 *    the genuinely identical mechanical shape (build a query, apply an
 *    optional WHERE, order, limit, select) so the 8 public methods stay
 *    short without pretending the underlying data shapes are the same.
 *
 * 4. BEHAVIOR PRESERVED EXACTLY
 *    Every table, column, filter dimension, LIMIT value, and ORDER BY
 *    column is reproduced as found per module, including live-stream's
 *    `id DESC` proxy-for-recency ordering and w2acd's confirmed-dead
 *    `$Group`/`$basefolder` values (the query itself never filtered by
 *    group in the legacy code, so this service's `w2acdMostDownloaded()`/
 *    `w2acdMostRecent()` correctly take no group parameter at all — adding
 *    one that silently did nothing would be worse than omitting it).
 *
 * 5. BEHAVIOR INTENTIONALLY CHANGED, AND WHY
 *    - Data/presentation split: legacy's most_recent_html()-style builders
 *      mix querying and HTML string-building in the same function. These
 *      methods return data only (a Collection of stdClass rows); thumbnail
 *      markup is Wave 3/4 Blade-view work once real item partials exist,
 *      not invented here ahead of need — consistent with how
 *      ContentListingService was scoped.
 *    - w2acd's `var-item-` URL bug and the dead `$basefolder` computation
 *      are not reproduced at all, since this service returns raw column
 *      data (id/title/hits/etc.), not rendered links — there is no URL to
 *      get wrong at this layer. The correct `cds-item-{id}.htm` URL
 *      construction is a Wave 4 view-layer decision, out of this
 *      service's scope, not silently fixed or silently ported broken here.
 *    - SQL injection: none of the four modules' original queries are
 *      parameterized (raw string concatenation for the optional filter
 *      values); every method here uses bound parameters instead. No
 *      observable behavior changes.
 *
 * 6. RISK IF THIS RECONCILIATION IS WRONG
 *    Every real content-type module (5, per Blueprint §4: khotab,
 *    anasheed, w2acd, telawah, live-stream) is a named consumer — a wrong
 *    LIMIT, filter column, or ORDER BY here reproduces incorrectly across
 *    all of them simultaneously. Specifically: mixing up which module
 *    filters by group vs. channel vs. not at all would silently show the
 *    wrong sidebar scope; reproducing live-stream's `id DESC` fallback
 *    everywhere instead of only where khotab's table lacks a recency
 *    column would silently misorder the other modules' genuinely
 *    time-ordered results.
 *
 * 7. TESTS PROVING BEHAVIORAL EQUIVALENCE
 *    tests/Feature/Content/ContentSidebarWidgetTest.php — one seeded
 *    fixture dataset per module, asserting the exact filter/limit/order
 *    behavior confirmed above, including an explicit test that no group
 *    filter exists for w2acd's two methods (proving the dead parameter
 *    was correctly dropped, not silently reintroduced).
 *
 * ============================================================================
 * WAVE 3 ADDITIONS
 * ============================================================================
 *
 * - mostViewedLiveChannels(): live-stream/functions.php's
 *   most_viewed_channels() [L36-55] — a *sixth* module for this pattern,
 *   but querying `nuke_sat_channels` itself (via Channel's
 *   eligibleForLiveStream() scope, ordered by ch_visits DESC LIMIT 10),
 *   not a content-item table. Doesn't fit the private query() helper's
 *   single-filter-column shape (the eligibility filter is 3 conditions),
 *   so it's written directly rather than forced through it.
 * - channelMostDownloadedKhotabItems()/channelMostRecentKhotabItems():
 *   channels/channel.php:100,110 — NOT a call to live-stream's
 *   most_viewed_list()/most_recent_list() despite computing a
 *   similar-sounding thing. This is a *different* legacy code path — a
 *   direct call to shared-core's generic topitems() helper
 *   (`topitems('hits', "channel_id='X' and vedio='1'", "hits DESC", 5)`),
 *   which happens to differ from live-stream's own pair in three
 *   confirmed ways: LIMIT 5 not 10, an explicit `vedio='1'` filter
 *   live-stream's version lacks, and "most recent" here orders by
 *   `time DESC` (not `id DESC`, since this call site does have a usable
 *   time column in scope). Reproducing topitems()'s raw-WHERE-fragment
 *   genericity was considered and rejected — with exactly one real call
 *   site in scope, two explicitly-parameterized methods are safer and
 *   clearer than importing that design smell for a single caller.
 * ============================================================================
 */
class ContentSidebarWidget
{
    /**
     * Enhancement Batch E-02 (F-02) — TTL for the cached khotab sidebar
     * widgets, in seconds. 300s is legacy's own value for these exact
     * queries (`functions.php:1032`, `SimpleCache::set($cacheKey, $items,
     * 300)`), not a number invented here, and matches the TTL this
     * application already uses for its homepage widgets.
     *
     * Deliberately finite: Laravel shares the content database with the
     * still-live legacy application, so rows can change without any
     * Laravel write to hook an invalidation onto. TTL expiry is therefore
     * the freshness mechanism — see this batch's report for the exact
     * staleness window.
     */
    private const CACHE_TTL_SECONDS = 300;

    // ---- anasheed ---------------------------------------------------

    public function anasheedMostDownloaded(?int $groupId = null): Collection
    {
        return $this->withAnasheedThumb($this->query('nuke_anasheed_anasheed', ['id', 'title', 'frame', 'downcount'], 'group_id', $groupId, 'hits', 7));
    }

    public function anasheedMostRecent(?int $groupId = null): Collection
    {
        return $this->withAnasheedThumb($this->query('nuke_anasheed_anasheed', ['id', 'title', 'frame', 'mytime'], 'group_id', $groupId, 'mytime', 7));
    }

    /**
     * G-13-09 (media/visual parity phase) — `anasheed/functions.php:882-910`'s
     * `most_recent_html()`, the shared builder behind both boxes above
     * (`item.php:93`'s `most_downloaded_recent_sidebar($Group)` call).
     * Confirmed real `thumbnail($args, ...)` call, `w=72,h=50` — routes
     * through `thumbnails.php`, matching `HomeController::anasheedThumb()`'s
     * already-established convention exactly (same w/h, same "frame flag
     * only, no file_exists()" behavior) — reused here via the same
     * `MediaPathResolver` path, not a new convention.
     */
    private function withAnasheedThumb(Collection $items): Collection
    {
        return $items->map(function ($item) {
            // var-item-17350.htm parity: functions.php:150-187's thumbnail()
            // routes BOTH branches through thumbnails.php (`$imgurl` is set
            // once, before the frame==1 check, since 'h'/'w' are always
            // passed here) — the fallback is `thumbnails.php?h=50&w=72&
            // src=images/tvnoise.gif`, not a direct /images/tvnoise.gif path.
            // Confirmed against live production, not assumed from the
            // frame==1 branch's own convention.
            $item->thumb = ((int) $item->frame) === 1
                ? '/thumbnails.php?h=50&w=72&src='.MediaPathResolver::path('anasheed/frame', (int) $item->id, 'jpg')
                : '/thumbnails.php?h=50&w=72&src=images/tvnoise.gif';

            return $item;
        });
    }

    // ---- w2acd (no group filter — confirmed dead parameter in legacy) ----

    public function w2acdMostDownloaded(): Collection
    {
        return $this->query('nuke_w2acd_w2acd', ['id', 'title', 'hits', 'banner', 'thumbnail'], null, null, 'hits', 6);
    }

    public function w2acdMostRecent(): Collection
    {
        return $this->query('nuke_w2acd_w2acd', ['id', 'title', 'mytime', 'banner', 'thumbnail'], null, null, 'mytime', 6);
    }

    // ---- telawah (no filter parameter in legacy at all) ----

    public function telawahMostDownloaded(): Collection
    {
        return $this->query('nuke_telawah_telawah', ['id', 'title'], null, null, 'hits', 10);
    }

    public function telawahMostRecent(): Collection
    {
        return $this->query('nuke_telawah_telawah', ['id', 'title'], null, null, 'mytime', 10);
    }

    // ---- live-stream (queries khotab's own table; filters by channel, not group) ----

    public function liveStreamMostViewed(int $channelId = 0): Collection
    {
        return $this->query('nuke_islamic_khotab', ['id', 'title'], 'channel_id', $channelId ?: null, 'hits', 10);
    }

    /** Orders by `id DESC`, not a time column — khotab's table has none available to this query, matching legacy exactly. */
    public function liveStreamMostRecent(int $channelId = 0): Collection
    {
        return $this->query('nuke_islamic_khotab', ['id', 'title'], 'channel_id', $channelId ?: null, 'id', 10);
    }

    // ---- Wave 3: live-stream's channel-directory widget + channels/channel.php's topitems()-derived pair ----

    public function mostViewedLiveChannels(): Collection
    {
        return Channel::eligibleForLiveStream()
            ->orderByDesc('ch_visits')
            ->limit(10)
            ->get(['id', 'title']);
    }

    /**
     * `channel.php:100`'s `topitems('hits', "channel_id='X' and vedio='1'",
     * "hits DESC", 5)` — WHERE/ORDER/LIMIT already matched; presentation
     * fields (`author`/`frame` for the thumbnail, `hits`/`time` for the
     * metadata line) were missing from the SELECT — added, additive only.
     * Reuses the already-established, already-verified `topitemsThumb()`
     * helper (G-13-01) rather than re-deriving thumbnail logic — this is
     * the same underlying legacy `topitems()` function every other
     * `topitemsThumb()` consumer in this file also reproduces.
     */
    public function channelMostDownloadedKhotabItems(int $channelId): Collection
    {
        // E-02 (F-02): cached — legacy reached this through its own
        // `topitems()`, which cached for 300s (`channel.php:110`).
        return $this->rememberRows(
            $this->cacheKey('channel-khotab', ['channel' => $channelId, 'order' => 'hits', 'limit' => 5]),
            fn () => DB::connection('main')->table('nuke_islamic_khotab')
                ->where('channel_id', $channelId)
                ->where('vedio', 1)
                ->select(['id', 'title', 'author', 'frame', 'hits', 'time'])
                ->orderByDesc('hits')
                ->limit(5)
                ->get()
        )->map(function ($item) {
            $item->thumb = $this->topitemsThumb((int) $item->frame, (int) $item->id);

            return $item;
        });
    }

    /** "Newest" counterpart to `channelMostDownloadedKhotabItems()` above — `topitems('time', ..., "time DESC", 5)`, mode='time' confirmed directly from `channel.php:110` (not assumed from a sibling page's own mode). */
    public function channelMostRecentKhotabItems(int $channelId): Collection
    {
        // E-02 (F-02): cached — see the "most downloaded" counterpart above.
        return $this->rememberRows(
            $this->cacheKey('channel-khotab', ['channel' => $channelId, 'order' => 'time', 'limit' => 5]),
            fn () => DB::connection('main')->table('nuke_islamic_khotab')
                ->where('channel_id', $channelId)
                ->where('vedio', 1)
                ->select(['id', 'title', 'author', 'frame', 'hits', 'time'])
                ->orderByDesc('time')
                ->limit(5)
                ->get()
        )->map(function ($item) {
            $item->thumb = $this->topitemsThumb((int) $item->frame, (int) $item->id);

            return $item;
        });
    }

    // ---- Wave 4: khotab/functions.php's topitems()-based sidebar pairs ----

    /**
     * `topitems('hits', "author = 'X' AND vedio ='Y'", "hits DESC", 5)` —
     * used correctly by `khotab/author.php:154`, `khotab/group.php:113`,
     * and (after the IF-015 fix) `khotab/series.php:146`. A DIFFERENT query
     * shape from `khotabMostDownloadedByVideoFlag()` below (author-scoped,
     * not just video-flag-scoped) — kept as its own method rather than an
     * optional-parameter variant, matching this class's existing "one
     * method per confirmed shape" convention (see class docblock §3).
     */
    public function khotabMostDownloadedByAuthor(int $authorId, bool $video): Collection
    {
        return $this->topitems(['author' => $authorId, 'vedio' => (int) $video], 'hits', 5);
    }

    /** `topitems('hits', "author = 'X' AND vedio ='Y'", "time DESC", 5)` — same call sites as above. */
    public function khotabMostRecentByAuthor(int $authorId, bool $video): Collection
    {
        return $this->topitems(['author' => $authorId, 'vedio' => (int) $video], 'time', 5);
    }

    /**
     * `topitems('hits', "vedio ='Y'", "hits DESC", 5)` — filters by video
     * flag only, no author. Used by `khotab/day.php:171` and (per IF-014's
     * fix) `khotab/item.php:467`'s "Most Downloaded" box — the item-page
     * legacy code read the undefined `$Khotab->video` instead of the real
     * `$Khotab->vedio`, which `topitems()`'s own normalization shim
     * silently turned into `vedio=0` (see IF-014's updated Evidence for
     * the shim's exact code/comment). This method takes the real, intended
     * `vedio` value instead, fixing that bug.
     */
    public function khotabMostDownloadedByVideoFlag(bool $video): Collection
    {
        return $this->topitems(['vedio' => (int) $video], 'hits', 5);
    }

    /** `topitems('time', "vedio ='Y'", "time DESC", 5)` — `khotab/day.php:181` and (IF-014 fix) `khotab/item.php:476`. */
    public function khotabMostRecentByVideoFlag(bool $video): Collection
    {
        return $this->topitems(['vedio' => (int) $video], 'time', 5);
    }

    /**
     * IF-017's fix: `khotab/news.php`'s PDF-listing branch left `$ob->video`
     * unset, which the same `topitems()` shim silently turned into
     * `vedio=0` — showing audio items on the PDF listing page instead of
     * being scoped to transcribed content at all. This method reproduces
     * `dump.php:76`'s already-correct pattern (`"(pdf > 0) AND hidden=0"`)
     * instead, since that is what a PDF-listing page's "Most Downloaded"
     * box should evidently be scoped to.
     */
    public function khotabMostDownloadedForPdf(): Collection
    {
        // E-02 (F-02): cached — legacy reached this shape through its own
        // 300s-cached `topitems()` (`dump.php:76`).
        return $this->rememberRows(
            $this->cacheKey('khotab-pdf', ['order' => 'hits', 'limit' => 5]),
            fn () => DB::connection('main')->table('nuke_islamic_khotab')
                ->select(['id', 'title', 'author', 'frame', 'hits', 'downcount', 'time'])
                ->where('pdf', '>', 0)
                ->where('hidden', '0')
                ->orderByDesc('hits')
                ->limit(5)
                ->get()
        );
    }

    /** IF-021's fix — same reasoning as `khotabMostDownloadedForPdf()` above, but scoped to one author (`khotab/author.php`'s `op=pdf` page). */
    public function khotabMostDownloadedByAuthorForPdf(int $authorId): Collection
    {
        // E-02 (F-02): cached — same legacy `topitems()` lineage as
        // `khotabMostDownloadedForPdf()`, author-scoped.
        return $this->rememberRows(
            $this->cacheKey('khotab-pdf', ['author' => $authorId, 'order' => 'hits', 'limit' => 5]),
            fn () => DB::connection('main')->table('nuke_islamic_khotab')
                ->select(['id', 'title', 'author', 'frame', 'hits', 'downcount', 'time'])
                ->where('author', $authorId)
                ->where('pdf', '>', 0)
                ->where('hidden', '0')
                ->orderByDesc('hits')
                ->limit(5)
                ->get()
        );
    }

    /** IF-021's fix, "Newest" counterpart — `author.php` renders both boxes unconditionally regardless of op. */
    public function khotabMostRecentByAuthorForPdf(int $authorId): Collection
    {
        // E-02 (F-02): cached — see the "most downloaded" counterpart above.
        return $this->rememberRows(
            $this->cacheKey('khotab-pdf', ['author' => $authorId, 'order' => 'time', 'limit' => 5]),
            fn () => DB::connection('main')->table('nuke_islamic_khotab')
                ->select(['id', 'title', 'author', 'frame', 'hits', 'downcount', 'time'])
                ->where('author', $authorId)
                ->where('pdf', '>', 0)
                ->where('hidden', '0')
                ->orderByDesc('time')
                ->limit(5)
                ->get()
        );
    }

    /**
     * `functions.php:1092-1141`'s `randomitems()` — picks a random khotab
     * id and scans forward for the first row with a visual asset
     * (`gif = 1 OR frame = 1`), falling back to scanning backward from the
     * same random id if the forward scan finds nothing (gif/frame rows are
     * sparse — a high random floor can land past the last match). Preserves
     * the exact "PRIMARY KEY range scan instead of ORDER BY RAND()"
     * optimization from prior-session performance work, not the original
     * `ORDER BY RAND()` this function itself replaced.
     */
    public function khotabRandomFeatured(int $limit = 1): Collection
    {
        $maxId = (int) (DB::connection('main')->table('nuke_islamic_khotab')->max('id') ?? 0);
        $randomId = random_int(1, max(1, $maxId));

        // khotab-series-{id}.htm parity: functions.php:1120-1127's randomitems()
        // branches on $Khotab->gif/$Khotab->frame to build the thumbnail path
        // (media/khotab_gifs/ vs media/khotab_frames/, bucketed by floor(id/1000)) —
        // neither column was selected here, so no consumer could build that path.
        $columns = ['kh.id', 'kh.channel_id', 'kh.author', 'kh.title', 'kh.comments', 'kh.time', 'kh.hits', 'kh.gif', 'kh.frame', 'ath.name', 'ath.prename'];

        $forward = DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->where('kh.id', '>=', $randomId)
            ->where(fn ($q) => $q->where('kh.gif', 1)->orWhere('kh.frame', 1))
            ->orderBy('kh.id')
            ->limit($limit)
            ->get($columns);

        if ($forward->isNotEmpty()) {
            return $forward;
        }

        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->leftJoin('nuke_islamic_authors as ath', 'kh.author', '=', 'ath.id')
            ->where('kh.id', '<', $randomId)
            ->where(fn ($q) => $q->where('kh.gif', 1)->orWhere('kh.frame', 1))
            ->orderByDesc('kh.id')
            ->limit($limit)
            ->get($columns);
    }

    /**
     * `categories/category.php:119,129`'s sidebar — `topitems('hits',
     * "hidden = 0 AND cat LIKE '%|{id}|%' and vedio ='1'", ...)`. Queried
     * here via the `khotab_category_index` junction table, NOT the raw
     * `cat LIKE` pattern — matching what `topitems()`'s own internal
     * regex rewrite (functions.php:1006-1016, prior-session performance
     * work) already turns this exact pattern into at runtime, and the
     * same junction table `ContentListingService::khotabItemsByCategory()`
     * already uses (Wave 2). Always `vedio=1` — `category.php`'s "audio"
     * branch is fully commented-out dead code (both live branches set
     * `$video=1`), so no audio variant exists to reproduce.
     */
    public function khotabMostDownloadedByCategory(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            })
            ->where('kh.hidden', 0)
            ->where('kh.vedio', 1)
            ->select(['kh.id', 'kh.title', 'kh.author', 'kh.frame', 'kh.hits', 'kh.downcount', 'kh.time'])
            ->orderByDesc('kh.hits')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $item->thumb = $this->topitemsThumb((int) $item->frame, (int) $item->id);

                return $item;
            });
    }

    /**
     * "Newest" counterpart to `khotabMostDownloadedByCategory()` above.
     *
     * `category-{id}.htm` Full Design Parity Pass: both this method and
     * the one above now append `->thumb` via the already-established
     * `topitemsThumb()` helper (G-13-01) — this JOIN-based query can't
     * reuse the private generic `topitems()` filter helper those other
     * `topitemsThumb()` consumers share, but the thumbnail resolution
     * itself is identical, so the same private method is called directly
     * rather than duplicating its logic.
     */
    public function khotabMostRecentByCategory(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            })
            ->where('kh.hidden', 0)
            ->where('kh.vedio', 1)
            ->select(['kh.id', 'kh.title', 'kh.author', 'kh.frame', 'kh.hits', 'kh.downcount', 'kh.time'])
            ->orderByDesc('kh.time')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $item->thumb = $this->topitemsThumb((int) $item->frame, (int) $item->id);

                return $item;
            });
    }

    /**
     * `categories/series.php:120`'s sidebar variant — confirmed, by direct
     * reading, to use `topitems('hits', "cat LIKE '%|$cat_id|%' and vedio
     * ='1'", ...)` with **no `hidden=0` filter**, unlike `category.php`'s
     * otherwise-identical widget (`khotabMostDownloadedByCategory()`
     * above, which does filter `hidden`). A real, confirmed difference
     * between the two pages' sidebars — not reused/collapsed into the
     * `category.php` version, which would silently drop hidden items this
     * page's real query includes.
     */
    public function khotabMostDownloadedByCategoryForSeries(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            })
            ->where('kh.vedio', 1)
            ->select(['kh.id', 'kh.title', 'kh.author', 'kh.frame', 'kh.hits', 'kh.downcount', 'kh.time'])
            ->orderByDesc('kh.hits')
            ->limit(5)
            ->get();
    }

    /** "Newest" counterpart to `khotabMostDownloadedByCategoryForSeries()` above — same no-`hidden`-filter difference, `categories/series.php:130`. */
    public function khotabMostRecentByCategoryForSeries(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_islamic_khotab as kh')
            ->join('khotab_category_index as kci', function ($join) use ($categoryId) {
                $join->on('kci.khotab_id', '=', 'kh.id')->where('kci.category_id', $categoryId);
            })
            ->where('kh.vedio', 1)
            ->select(['kh.id', 'kh.title', 'kh.author', 'kh.frame', 'kh.hits', 'kh.downcount', 'kh.time'])
            ->orderByDesc('kh.time')
            ->limit(5)
            ->get();
    }

    // ---- Wave 4 (post-Wave-4 addition): radio/index.php ----

    /**
     * `radio/index.php:140,149`'s two sidebar boxes — `topitems('hits',
     * "vedio=Y", "time DESC", 10)`. Identical filter/order shape to
     * `khotabMostRecentByVideoFlag()` above but LIMIT 10, not 5 — kept as
     * its own method rather than an optional-parameter variant, matching
     * this class's "one method per confirmed call-site shape" convention
     * (see class docblock §3 and `khotabMostDownloadedByAuthor()`'s own
     * note on the same point).
     */
    public function radioMostRecentByVideoFlag(bool $video): Collection
    {
        return $this->topitems(['vedio' => (int) $video], 'time', 10);
    }

    /**
     * `radio/index.php:42-48`'s continuous-playlist query — a JOIN across
     * `nuke_islamic_mirror`/`nuke_islamic_khotab`/`nuke_islamic_authors`
     * filtered to `.mp3` links only, LIMIT 40. A genuinely different shape
     * from every other method in this class (no single filter column, a
     * 3-table JOIN, an OR'd LIKE across two columns) — not forced through
     * the shared `query()`/`topitems()` helpers below.
     *
     * `ORDER BY khid DESC, linksize ASC` (not `RAND()`) — the legacy
     * comment at this call site notes `RAND()` was intentionally left
     * un-rewritten elsewhere in an earlier performance pass because the
     * `.mp3`-filtered candidate set can't be narrowed by an id-range
     * trick; reproduced here exactly as currently deployed, not as
     * originally written.
     *
     * Data only, no presentation: which link (`main_link` vs
     * `mirror_link`) is actually playable, and the resulting `pl_section`
     * label, is a real per-row decision the legacy code makes at render
     * time — kept in `RadioController` (Wave 4's established
     * data/presentation split, see class docblock §5), not duplicated
     * into this query.
     */
    public function radioPlaylist(int $limit = 40): Collection
    {
        return DB::connection('main')->table('nuke_islamic_mirror as mir')
            ->leftJoin('nuke_islamic_khotab as kh', 'kh.id', '=', 'mir.khid')
            ->leftJoin('nuke_islamic_authors as auth', 'auth.id', '=', 'kh.author')
            ->where(function ($query) {
                $query->where('kh.link', 'like', '%.mp3%')->orWhere('mir.link', 'like', '%.mp3%');
            })
            ->where('kh.broken', 0)
            ->where('kh.hidden', 0)
            ->select([
                'kh.title', 'kh.id as khid', 'kh.vedio as media_type', 'kh.link as main_link',
                'mir.time', 'mir.id as mirror_id', 'mir.link as mirror_link',
                'auth.prename', 'auth.name as author_name',
            ])
            ->orderByDesc('khid')
            ->orderBy('kh.linksize')
            ->limit($limit)
            ->get();
    }

    // ---- Wave 4 (post-Wave-4 addition): chat_room's lesson-browsing half (task 4.11) ----

    /**
     * `chat_room/functions.php`'s `most_chat_lessons("hits", $author_id)` —
     * filters `location_id=10, hidden=0`, optional author, LIMIT 10. A
     * seventh module for the P-016 sidebar shape, but location-scoped
     * rather than group/category/channel-scoped like every prior one.
     */
    public function chatRoomMostViewedLessons(int $authorId = 0): Collection
    {
        return $this->chatRoomLessons('hits', $authorId);
    }

    /** `chat_room/functions.php`'s `most_chat_lessons("id", $author_id)` — "newest" here orders by `id DESC`, not a time column (same fallback shape as `liveStreamMostRecent()`). */
    public function chatRoomMostRecentLessons(int $authorId = 0): Collection
    {
        return $this->chatRoomLessons('id', $authorId);
    }

    private function chatRoomLessons(string $orderColumn, int $authorId): Collection
    {
        $query = DB::connection('main')->table('nuke_islamic_khotab')
            ->select(['id', 'title', 'vedio'])
            ->where('location_id', 10)
            ->where('hidden', 0);

        if ($authorId > 0) {
            $query->where('author', $authorId);
        }

        return $query->orderByDesc($orderColumn)->limit(10)->get();
    }

    // ---- Fatawa (Roadmap task 6.1) ------------------------------------

    /**
     * `fatawa/functions.php:679-684` `mostdownload($topic_id)`'s
     * category-scoped branch (the only branch legacy's own call sites
     * actually exercise — `tobics.php`/`subtobics.php` both pass a
     * `nuke_w2a_cat` category id as this parameter, despite its
     * `$topic_id` name; the `$auther_id`/`$channel` branches of the same
     * legacy function are covered separately, not here). Preserves the
     * exact nested-subquery shape: `nuke_fatwa_questions.topic_id IN
     * (SELECT id FROM nuke_fatwa_topics WHERE parent_id = <category>)`.
     */
    public function fatwaMostDownloadedByCategory(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->whereIn('topic_id', function ($query) use ($categoryId) {
                $query->select('id')->from('nuke_fatwa_topics')->where('parent_id', $categoryId);
            })
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('num_download')
            ->limit(10)
            ->get();
    }

    /** "Newest" counterpart to `fatwaMostDownloadedByCategory()` above — `recentlyadd()`'s matching branch, `ORDER BY db_insertion_date DESC`. */
    public function fatwaMostRecentByCategory(int $categoryId): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->whereIn('topic_id', function ($query) use ($categoryId) {
                $query->select('id')->from('nuke_fatwa_topics')->where('parent_id', $categoryId);
            })
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('db_insertion_date')
            ->limit(10)
            ->get();
    }

    /**
     * `mostdownload()`'s channel-scoped branch (`functions.php:683`,
     * `channel_fatawa.php:153`'s `mostdownload(0,0,$id)` call) — unlike
     * the author branch below, this one's `WHERE channel_id=` filter is
     * NOT commented out in legacy; genuinely scoped, reproduced as such.
     */
    public function fatwaMostDownloadedByChannel(int $channelId): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->where('channel_id', $channelId)
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('num_download')
            ->limit(10)
            ->get();
    }

    /** "Newest" counterpart to `fatwaMostDownloadedByChannel()` above. */
    public function fatwaMostRecentByChannel(int $channelId): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->where('channel_id', $channelId)
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('db_insertion_date')
            ->limit(10)
            ->get();
    }

    /**
     * `mostdownload()`'s author-scoped branch (`functions.php:682`,
     * `auther_profile.php:73`'s `mostdownload(0,$auther_id)` call).
     * **Confirmed, re-verified directly: this branch's `WHERE auther_id=`
     * filter is commented out in legacy source** (`functions.php:682`,
     * `/*WHERE auther_id =".$auther_id." *&#47;` — a literal PHP comment
     * around the filter, dead code). The query is therefore genuinely
     * sitewide/unscoped — every author's page shows the identical global
     * top-10-most-downloaded list. **Preserved exactly, not "fixed" to
     * actually filter by author** — this is Behavior First: the confirmed
     * legacy behavior is the same 10 questions on every author's page,
     * not per-author filtering the code only appears to promise.
     */
    public function fatwaMostDownloadedSitewide(): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('num_download')
            ->limit(10)
            ->get();
    }

    /** "Newest" counterpart to `fatwaMostDownloadedSitewide()` above — same commented-out-filter behavior, `recentlyadd()`'s matching branch. */
    public function fatwaMostRecentSitewide(): Collection
    {
        return DB::connection('main')->table('nuke_fatwa_questions')
            ->select(['id', 'question_text', 'general_question_id', 'topic_id'])
            ->orderByDesc('db_insertion_date')
            ->limit(10)
            ->get();
    }

    /**
     * `fatawa.php:147-153` `tasnifat_latestadd()` — `fatawa.htm`'s own
     * "احدث التصنيفات المضافة" sidebar box. **G-07-02 fix:** legacy queries
     * the entire `nuke_w2a_cat` table, no `main_cat` filter at all — every
     * nesting level is eligible. Confirmed via Phase 1 audit against real
     * `olddb` data: the prior view-level approximation (sorting the
     * controller's own `main_cat=0`-filtered `$categories` collection) was
     * 0/10 correct against legacy's real top-10. Query reproduced verbatim:
     * `WHERE q_count != 0 ORDER BY id DESC LIMIT 10`.
     */
    public function fatwaLatestAddedCategories(): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('q_count', '!=', 0)
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * `fatawa.php:155-161` `tasnifat_active()` — `fatawa.htm`'s own
     * "التصنيفات الأكثر نشاطاً" sidebar box. Same G-07-02 fix and same
     * unfiltered-`main_cat` reasoning as `fatwaLatestAddedCategories()`
     * above. Query reproduced verbatim: `WHERE q_count != 0 ORDER BY
     * q_count DESC LIMIT 10`.
     */
    public function fatwaMostActiveCategories(): Collection
    {
        return DB::connection('main')->table('nuke_w2a_cat')
            ->where('q_count', '!=', 0)
            ->orderByDesc('q_count')
            ->limit(10)
            ->get();
    }

    /** Shared query shape behind the 4 `khotabMost*` methods above — `topitems()`'s fixed SELECT list/table, varying only the WHERE filters and ORDER BY column. */
    private function topitems(array $filters, string $orderColumn, int $limit): Collection
    {
        $rows = $this->rememberRows(
            $this->cacheKey('topitems', $filters + ['order' => $orderColumn, 'limit' => $limit]),
            function () use ($filters, $orderColumn, $limit) {
                $query = DB::connection('main')->table('nuke_islamic_khotab')
                    ->select(['id', 'title', 'author', 'frame', 'hits', 'downcount', 'time']);

                foreach ($filters as $column => $value) {
                    $query->where($column, $value);
                }

                return $query->orderByDesc($orderColumn)->limit($limit)->get();
            }
        );

        return $rows->map(function ($item) {
            $item->thumb = $this->topitemsThumb((int) $item->frame, (int) $item->id);

            return $item;
        });
    }

    /**
     * Enhancement Batch E-02 (F-02) — restores the caching legacy already
     * had for these exact widgets.
     *
     * Legacy's own `topitems()` (`functions.php:1027-1033`) cached its raw
     * result rows for 300s **before** its per-row decoration loop, so a
     * cache hit still recomputed the display-layer values fresh and only
     * the database round trip was skipped. That is reproduced exactly:
     * this helper caches the raw query rows only, and every caller
     * re-applies its own decoration (`topitemsThumb()`, which performs a
     * real `file_exists()` check) after retrieval — so a thumbnail
     * appearing or disappearing on disk is reflected immediately rather
     * than being frozen for the TTL.
     *
     * Rows are stored as **plain arrays** and rehydrated to `stdClass`
     * with a PHP cast on the way out — the same house pattern
     * `ContentListingService::homeLatestVideos()` and
     * `KhotabSearchController::rememberSafely()` already use. Caching the
     * `stdClass` rows directly would be silently broken on any real
     * serializing store, because this app runs Laravel's secure default
     * `cache.serializable_classes = false`, which turns every cached
     * object into `__PHP_Incomplete_Class` on read. That exact bug has
     * already hit this application once (see `homeLatestVideos()`'s
     * docblock); this helper exists so it cannot recur here.
     *
     * @param  \Closure(): Collection<int, \stdClass>  $rows
     * @return Collection<int, \stdClass>
     */
    private function rememberRows(string $key, \Closure $rows): Collection
    {
        $cached = Cache::remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn () => $rows()->map(fn ($row) => (array) $row)->all()
        );

        return collect($cached)->map(fn (array $row) => (object) $row);
    }

    /**
     * Deterministic cache key covering every input that changes the
     * result set. Values are cast and the filter list is sorted by column
     * name, so two callers expressing the same filters in a different
     * order share one entry rather than silently splitting the cache.
     * Keys are built only from internal column names and integer/bool
     * ids — never from raw user-supplied strings.
     *
     * @param  array<string, scalar>  $parts
     */
    private function cacheKey(string $widget, array $parts): string
    {
        ksort($parts);

        $suffix = collect($parts)
            ->map(fn ($value, $name) => $name.'='.(is_bool($value) ? (int) $value : $value))
            ->implode(':');

        return 'sidebar:'.$widget.':'.$suffix;
    }

    /**
     * G-13-01 (media/visual parity phase) — `functions.php:1046-1061`'s
     * `topitems()` renders an `<img>` thumbnail per row (`<li class="media">`),
     * which the Laravel views consuming this data were missing entirely
     * (plain text `<li>`, no image). `frame==1` branch reproduced exactly:
     * bucketed `khotab_frames` path, real `file_exists()` gate, matching
     * `HomeController::bucketedThumb()`'s already-established pattern for
     * the same convention.
     *
     * **The `else` (non-frame/author-photo) branch is deliberately NOT a
     * real lookup.** Legacy's own line 1056 is a malformed double-quoted
     * string — `"..."."media/authors/' . $folder_id . '/' . $item->author
     * . '.jpg"` — where the single-quote-concatenation syntax is written
     * *inside* an already-open double-quoted string, so PHP treats `' . `
     * and `.` as literal characters, not operators, while still
     * interpolating `$folder_id`/`$item->author`. The resulting path can
     * never match a real file, so `file_exists()` always returns false and
     * this branch always falls through to the placeholder — a confirmed,
     * deterministic legacy bug, not a real photo lookup. Reproduced here
     * as its actual observable output (always the placeholder) rather than
     * either blindly copying the broken string or silently "fixing" it
     * into a working lookup — the latter would be a real behavior change
     * (some author photos would newly start rendering) and needs the same
     * explicit confirmation G-10-01 required for a comparable case, not a
     * default made here.
     */
    private function topitemsThumb(int $frame, int $id): string
    {
        if ($frame === 1) {
            $rel = MediaPathResolver::path('khotab_frames', $id, 'jpg');
            if (file_exists(public_path($rel))) {
                return '/'.$rel;
            }
        }

        return '/images/way2_withoutimg.png';
    }

    private function query(string $table, array $columns, ?string $filterColumn, mixed $filterValue, string $orderColumn, int $limit): Collection
    {
        $query = DB::connection('main')->table($table)->select($columns);

        if ($filterColumn !== null && $filterValue !== null) {
            $query->where($filterColumn, $filterValue);
        }

        return $query->orderByDesc($orderColumn)->limit($limit)->get();
    }
}
