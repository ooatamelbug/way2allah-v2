<?php

use App\Domain\Content\Services\ContentListingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures\MainSchema;
use Tests\Support\InMemoryConnection;

/**
 * Fixture schema uses the same canonical MainSchema table definitions as
 * ChannelTest/ContentSidebarWidgetTest — not a locally-drifted copy (see
 * MainSchema's own docblock for the hazard this prevents).
 */
function useInMemoryMainConnectionForListing(): void
{
    InMemoryConnection::setup('main', [
        'nuke_sat_channels' => MainSchema::nukeSatChannels(),
        'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
        'nuke_islamic_advanced' => MainSchema::nukeIslamicAdvanced(),
        'nuke_islamic_groups' => MainSchema::nukeIslamicGroups(),
        'nuke_islamic_series' => MainSchema::nukeIslamicSeries(),
        'series_category_index' => MainSchema::seriesCategoryIndex(),
        'khotab_category_index' => MainSchema::khotabCategoryIndex(),
        'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(),
        // G-02 (Homepage Migration) additions.
        'nuke_w2a_cat' => MainSchema::nukeW2aCat(),
        'nuke_fatwa_questions' => MainSchema::nukeFatwaQuestions(),
        'nuke_anasheed_anasheed' => MainSchema::nukeAnasheedAnasheed(),
        'nuke_anasheed_groups' => MainSchema::nukeAnasheedGroups(),
        'nuke_anasheed_advanced' => MainSchema::nukeAnasheedAdvanced(),
        'nuke_telawah_telawah' => MainSchema::nukeTelawahTelawah(),
        'nuke_telawah_groups' => MainSchema::nukeTelawahGroups(),
        'nuke_options' => MainSchema::nukeOptions(),
        'nuke_albums_images' => MainSchema::nukeAlbumsImages(),
    ]);
}

beforeEach(function () {
    useInMemoryMainConnectionForListing();
    $this->service = new ContentListingService;
});

// ---- groupsByAuthor (khotab) ----

it('groupsByAuthor: uses the LIVE COUNT(kh.id) aggregate, not the stored grp.count column, matching the duplicate-alias bug production has always served', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 5, 'title' => 'G1', 'count' => 999, 'vedio' => 1, 'hidden' => 0]);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'group_id' => 1, 'author' => 5],
        ['id' => 2, 'group_id' => 1, 'author' => 5],
    ]);

    $results = $this->service->groupsByAuthor(5, true);

    expect($results)->toHaveCount(1)
        ->and((int) $results->first()->count)->toBe(2) // live aggregate, not the stored 999
        ->and((int) $results->first()->stored_group_count)->toBe(999); // preserved, not lost
});

it('groupsByAuthor: excludes hidden groups by default, includes them when explicitly requested', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert(['id' => 1, 'author_id' => 5, 'title' => 'Hidden', 'count' => 1, 'vedio' => 1, 'hidden' => 1]);
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'group_id' => 1, 'author' => 5]);

    expect($this->service->groupsByAuthor(5, true))->toHaveCount(0)
        ->and($this->service->groupsByAuthor(5, true, includeHidden: true))->toHaveCount(1);
});

// ---- groupsByCategory (categories) ----

it('groupsByCategory: matches the pipe-delimited cat membership pattern and trusts the stored count directly, never joining khotab', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'cat' => '|3|7|', 'title' => 'In category 3', 'count' => 42, 'vedio' => 0, 'hidden' => 0],
        ['id' => 2, 'cat' => '|9|', 'title' => 'Not in category 3', 'count' => 5, 'vedio' => 0, 'hidden' => 0],
    ]);

    $results = $this->service->groupsByCategory(3, false);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe(1)
        ->and((int) $results->first()->count)->toBe(42); // stored value used as-is, no aggregate
});

it('groupsByCategory: has no hidden-override capability at all — hidden rows are always excluded', function () {
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'cat' => '|3|', 'title' => 'X', 'count' => 1, 'vedio' => 0, 'hidden' => 1]);

    expect($this->service->groupsByCategory(3, false))->toHaveCount(0);
});

// ---- seriesByAuthorAndGroup (khotab) vs seriesByCategoryAndGroup (categories) ----

it('seriesByAuthorAndGroup: filters by author+group and never joins authors (no name in the result)', function () {
    DB::connection('main')->table('nuke_islamic_series')->insert([
        'id' => 1, 'author_id' => 5, 'group_id' => 0, 'title' => 'S1', 'count' => 3, 'vedio' => 0, 'hidden' => 0, 'lastupdate' => 100,
    ]);

    $results = $this->service->seriesByAuthorAndGroup(5, 0, false);

    expect($results)->toHaveCount(1)
        ->and(property_exists($results->first(), 'name'))->toBeFalse();
});

it('seriesByCategoryAndGroup: filters via the junction table and does join authors (name present)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh X', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_series')->insert(['id' => 1, 'author_id' => 9, 'group_id' => 0, 'title' => 'S1', 'count' => 3, 'vedio' => 0, 'hidden' => 0, 'lastupdate' => 100]);
    $db->table('series_category_index')->insert(['series_id' => 1, 'category_id' => 4]);

    $results = $this->service->seriesByCategoryAndGroup(4, 0, false);

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Shaikh X');
});

// ---- khotabItemsFixedOrNew (khotab, mode fixed/new) ----

it('khotabItemsFixedOrNew: onlyFixed=true adds the fixed=1 filter that mode=new omits', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'vedio' => 0, 'fixed' => 1, 'weight' => 1, 'time' => 100],
        ['id' => 2, 'vedio' => 0, 'fixed' => 0, 'weight' => 1, 'time' => 200],
    ]);

    expect($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: true))->toHaveCount(1)
        ->and($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false))->toHaveCount(2);
});

it('khotabItemsFixedOrNew: author/ser/group filters are conditional — omitted entirely when 0, unlike the default-mode method', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 1, 'time' => 100],
        ['id' => 2, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 1, 'time' => 100],
    ]);

    // authorId=0 means "no author filter at all" here — both rows returned.
    expect($this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false))->toHaveCount(2);
});

it('khotabItemsFixedOrNew: orders by weight desc then time desc, and caps at 50 rows', function () {
    $db = DB::connection('main');
    for ($i = 1; $i <= 55; $i++) {
        $db->table('nuke_islamic_khotab')->insert(['id' => $i, 'vedio' => 0, 'weight' => 0, 'time' => $i]);
    }

    $results = $this->service->khotabItemsFixedOrNew(0, 0, 0, false, onlyFixed: false);

    expect($results)->toHaveCount(50)
        ->and($results->first()->time)->toBe(55); // highest time first (weight tied at 0)
});

// ---- khotabItemsForDay ----

it('khotabItemsForDay: only includes items within [dayStart, dayEnd)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'vedio' => 0, 'time' => 999, 'weight' => 0],  // before window
        ['id' => 2, 'vedio' => 0, 'time' => 1000, 'weight' => 0], // start of window (inclusive)
        ['id' => 3, 'vedio' => 0, 'time' => 1999, 'weight' => 0], // end of window (exclusive boundary - 1)
        ['id' => 4, 'vedio' => 0, 'time' => 2000, 'weight' => 0], // at/after end (exclusive)
    ]);

    $results = $this->service->khotabItemsForDay(false, 1000, 2000);

    expect($results->pluck('id')->sort()->values()->all())->toBe([2, 3]);
});

// ---- khotabItemsWithPdf ----

it('khotabItemsWithPdf: filters pdf > 0 and orders/selects using pdf_time as "time"', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'pdf' => 0, 'pdf_time' => 500, 'weight' => 0],
        ['id' => 2, 'pdf' => 1, 'pdf_time' => 500, 'weight' => 0],
    ]);

    $results = $this->service->khotabItemsWithPdf();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe(2)
        ->and((int) $results->first()->time)->toBe(500);
});

// ---- khotabItemsDefault ----

it('khotabItemsDefault: filters author/ser/group unconditionally, even when 0 — the confirmed divergence from khotabItemsFixedOrNew', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1],
        ['id' => 2, 'author' => 7, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1],
    ]);

    // authorId=0 here means "filter to rows where author is literally 0" — only row 1.
    $results = $this->service->khotabItemsDefault(0, 0, 0, false);

    expect($results)->toHaveCount(1)->and($results->first()->id)->toBe(1);
});

it('khotabItemsDefault: never joins authors — no name/prename available in the result', function () {
    DB::connection('main')->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1]);

    $result = $this->service->khotabItemsDefault(0, 0, 0, false)->first();

    expect(property_exists($result, 'name'))->toBeFalse();
});

// ---- khotabItemsByCategory ----

it('khotabItemsByCategory: filters via the junction table, joins authors, and orders by time only (no weight)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Y', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 100, 'time' => 1],
        ['id' => 2, 'author' => 9, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 2],
    ]);
    $db->table('khotab_category_index')->insert([
        ['khotab_id' => 1, 'category_id' => 3],
        ['khotab_id' => 2, 'category_id' => 3],
    ]);

    $results = $this->service->khotabItemsByCategory(3, 0, 0, false);

    // id 2 has lower weight but a later time, and this method orders by time only —
    // if it were weight-ordered (like khotab's own methods) id 1 would come first.
    expect($results->pluck('id')->all())->toBe([2, 1])
        ->and($results->first()->name)->toBe('Shaikh Y');
});

it('khotabItemsByCategory: excludes rows outside the requested category via the junction table', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 0, 'weight' => 0, 'time' => 1]);
    $db->table('khotab_category_index')->insert(['khotab_id' => 1, 'category_id' => 999]);

    expect($this->service->khotabItemsByCategory(3, 0, 0, false))->toHaveCount(0);
});

// ---- Wave 3: groupsByChannel / seriesByChannel / khotabItemsByChannel (channels/functions.php) ----

it('groupsByChannel: filters by channel always, author only when positive, and joins authors', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Z']);
    $db->table('nuke_islamic_groups')->insert([
        ['id' => 1, 'channel_id' => 5, 'author_id' => 9, 'title' => 'G1', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
        ['id' => 2, 'channel_id' => 5, 'author_id' => 99, 'title' => 'G2', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
        ['id' => 3, 'channel_id' => 6, 'author_id' => 9, 'title' => 'G3', 'count' => 1, 'vedio' => 1, 'hidden' => 0],
    ]);

    $allInChannel = $this->service->groupsByChannel(5, 0, true);
    $byAuthorInChannel = $this->service->groupsByChannel(5, 9, true);

    expect($allInChannel)->toHaveCount(2)
        ->and($byAuthorInChannel)->toHaveCount(1)
        ->and($byAuthorInChannel->first()->author)->toBe('Shaikh Z');
});

it('groupsByChannel: has no hidden-override capability — hidden rows always excluded, matching legacy', function () {
    DB::connection('main')->table('nuke_islamic_groups')->insert(['id' => 1, 'channel_id' => 5, 'title' => 'X', 'count' => 1, 'vedio' => 1, 'hidden' => 1]);

    expect($this->service->groupsByChannel(5, 0, true))->toHaveCount(0);
});

it('seriesByChannel: filters by channel always, author only when positive, and joins authors', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 9, 'name' => 'Shaikh Z']);
    $db->table('nuke_islamic_series')->insert([
        'id' => 1, 'channel_id' => 5, 'author_id' => 9, 'title' => 'S1', 'count' => 1, 'vedio' => 1, 'hidden' => 0, 'lastupdate' => 1,
    ]);

    $results = $this->service->seriesByChannel(5, 9, true);

    expect($results)->toHaveCount(1)->and($results->first()->author)->toBe('Shaikh Z');
});

it('khotabItemsByChannel: filters ser_id/group_id unconditionally (even at 0), the same IF-005 shape found in khotab itself', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'time' => 1],
        ['id' => 2, 'channel_id' => 5, 'author' => 0, 'ser_id' => 7, 'group_id' => 0, 'vedio' => 1, 'time' => 1],
    ]);

    // ser_id=0 here means "filter to rows where ser_id is literally 0" — only row 1.
    $results = $this->service->khotabItemsByChannel(5, 0, 0, 0, true);

    expect($results)->toHaveCount(1)->and($results->first()->id)->toBe(1);
});

it('khotabItemsByChannel: orders by time only, not weight (channels/functions.php has no weight ordering)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'weight' => 100, 'time' => 1],
        ['id' => 2, 'channel_id' => 5, 'author' => 0, 'ser_id' => 0, 'group_id' => 0, 'vedio' => 1, 'weight' => 0, 'time' => 2],
    ]);

    $results = $this->service->khotabItemsByChannel(5, 0, 0, 0, true);

    // id 2 has far lower weight but a later time — if weight were used, id 1 would lead.
    expect($results->pluck('id')->all())->toBe([2, 1]);
});

// ---- ramadanSeriesByYear (Task 6.3, pages/ramadan.php's authoritative boundaries) ----

it('ramadanSeriesByYear: filters by ramadan=1 AND hidden=0, joins the author, and buckets by ramadan.php\'s own id boundaries', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_series')->insert([
        ['id' => 5000, 'title' => 'In 1434 bucket', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1],
        ['id' => 12000, 'title' => 'In 1443 bucket', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'count' => 1, 'vedio' => 1],
        ['id' => 12001, 'title' => 'Not flagged ramadan, must not appear', 'author_id' => 1, 'ramadan' => 0, 'hidden' => 0, 'count' => 1, 'vedio' => 1],
        ['id' => 12002, 'title' => 'Hidden, must not appear', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 1, 'count' => 1, 'vedio' => 1],
    ]);

    $results = $this->service->ramadanSeriesByYear();

    expect(array_keys($results))->toBe([1447, 1446, 1444, 1443, 1442, 1441, 1440, 1439, 1438, 1437, 1436, 1435, 1434]);
    expect($results[1434]->pluck('title')->all())->toBe(['In 1434 bucket']);
    expect($results[1443]->pluck('title')->all())->toBe(['In 1443 bucket']);
    expect($results[1442])->toHaveCount(0);
});

it('ramadanSeriesByYear: has no 1445 bucket at all — ramadan.php\'s own current section list skips it (merged into 1446, unlike ramadan-archive.php\'s duplicate-bugged split)', function () {
    $results = $this->service->ramadanSeriesByYear();

    expect(array_key_exists(1445, $results))->toBeFalse();
});

it('ramadanSeriesByYear: the 1447 bucket is time-based (>= the confirmed 2026-02-09 threshold), not id-based', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'Shaikh']);
    $threshold = strtotime('2026-02-09 00:00:00');
    $db->table('nuke_islamic_series')->insert([
        ['id' => 1, 'title' => 'Before threshold, must not appear in 1447', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'time' => $threshold - 1, 'count' => 1, 'vedio' => 1],
        ['id' => 2, 'title' => 'At threshold, appears in 1447', 'author_id' => 1, 'ramadan' => 1, 'hidden' => 0, 'time' => $threshold, 'count' => 1, 'vedio' => 1],
    ]);

    $results = $this->service->ramadanSeriesByYear();

    expect($results[1447]->pluck('title')->all())->toBe(['At threshold, appears in 1447']);
});

// ---- G-02 (Homepage Migration Blueprint) ----

it('homeLatestVideos: only vedio=1 AND newslist=1 rows, newest lastmirror first, limit 3, cached for 300s (stale reads survive a data change within the window)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 300, 'title' => 'V-newest'],
        ['id' => 2, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 200, 'title' => 'V-mid'],
        ['id' => 3, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 100, 'title' => 'V-oldest'],
        ['id' => 4, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 50, 'title' => 'V-excluded-by-limit'],
        ['id' => 5, 'author' => 1, 'vedio' => 1, 'newslist' => 0, 'lastmirror' => 400, 'title' => 'Excluded: newslist=0 despite newest'],
        ['id' => 6, 'author' => 1, 'vedio' => 0, 'newslist' => 1, 'lastmirror' => 500, 'title' => 'Excluded: vedio=0 despite newest'],
    ]);

    $first = $this->service->homeLatestVideos();

    expect($first->pluck('title')->all())->toBe(['V-newest', 'V-mid', 'V-oldest']);

    // Cache proof: change the underlying data, call again inside the 300s window, expect the SAME (stale) result.
    $db->table('nuke_islamic_khotab')->where('id', 1)->update(['title' => 'Changed after first call']);
    $second = $this->service->homeLatestVideos();

    expect($second->pluck('title')->all())->toBe(['V-newest', 'V-mid', 'V-oldest']);
});

/**
 * Homepage HTTP 500 investigation — the default test cache store
 * (`array`) never serializes at all, so it can't catch this class of bug:
 * this app's `config('cache.serializable_classes')` is `false` (Laravel's
 * own secure-by-default setting), so the real `file` store's
 * `unserialize(..., ['allowed_classes' => false])` converts every cached
 * *object* — including plain `stdClass`, not just Eloquent models — into
 * `__PHP_Incomplete_Class` on every cache read after the first. Genuinely
 * different root cause from the G-06/G-09-02 Eloquent-model corruption
 * this project already fixed elsewhere (`KhotabSearchController::
 * rememberSafely()`) — that fix's *pattern* (cache plain arrays, rehydrate
 * via `(object)` after reading) is exactly what closes this one too, but
 * the failure mechanism is a config-driven unserialize restriction, not
 * model hydration. Switches to the real `file` store deliberately, to
 * exercise the actual dev-server code path these tests would otherwise
 * never touch — and cleans up the cache file it writes.
 */
it('homeLatestVideos/homeLatestAudios/homeLatestDumpFiles: survive a real file-cache-store serialize/unserialize round-trip (config("cache.serializable_classes")=false does not corrupt the cached stdClass rows on a second read)', function () {
    config(['cache.default' => 'file']);

    // Defensive: the `file` store writes to the real, persistent
    // storage/framework/cache/data directory (shared with manual dev-server
    // testing done outside Pest), so a leftover entry from a prior run could
    // be read as a false cache HIT before this test ever writes its own.
    // Clearing up front (not just in `finally`) guarantees the first calls
    // below are genuine cache MISSes against this test's own fixture data.
    Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-videos');
    Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-audios');
    Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-dump-files-3');

    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'pdf' => 1, 'lastmirror' => 100, 'pdf_time' => 100, 'title' => 'File-cache item'],
        ['id' => 2, 'author' => 1, 'vedio' => 0, 'newslist' => 1, 'pdf' => 0, 'lastmirror' => 100, 'pdf_time' => 0, 'title' => 'File-cache audio item'],
    ]);

    try {
        // First call: cache MISS, writes the file. This alone never reproduced
        // the bug (the closure's return value is used directly, no unserialize
        // involved) — the second call is the one that exercises the real
        // vulnerability, reading back through FileStore::unserialize().
        $this->service->homeLatestVideos();
        $this->service->homeLatestAudios();
        $this->service->homeLatestDumpFiles();

        $videos = $this->service->homeLatestVideos();
        $audios = $this->service->homeLatestAudios();
        $dumps = $this->service->homeLatestDumpFiles();

        expect(get_class($videos->first()))->toBe('stdClass')
            ->and($videos->first()->title)->toBe('File-cache item')
            ->and(get_class($audios->first()))->toBe('stdClass')
            ->and(get_class($dumps->first()))->toBe('stdClass')
            ->and($dumps->first()->title)->toBe('File-cache item');
    } finally {
        Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-videos');
        Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-audios');
        Illuminate\Support\Facades\Cache::store('file')->forget('home-latest-dump-files-3');
    }
});

it('homeLatestAudios: only vedio=0 AND newslist=1 rows, newest lastmirror first, limit 7', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'vedio' => 0, 'newslist' => 1, 'lastmirror' => 200, 'title' => 'Audio-newest'],
        ['id' => 2, 'author' => 1, 'vedio' => 0, 'newslist' => 1, 'lastmirror' => 100, 'title' => 'Audio-oldest'],
        ['id' => 3, 'author' => 1, 'vedio' => 1, 'newslist' => 1, 'lastmirror' => 300, 'title' => 'Excluded: vedio=1'],
    ]);

    $results = $this->service->homeLatestAudios();

    expect($results->pluck('title')->all())->toBe(['Audio-newest', 'Audio-oldest']);
});

it('homeLatestDumpFiles: pdf>0 rows only, newest pdf_time first, respects $limit, cache key includes the limit (matching legacy\'s own SimpleCache key)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert([
        ['id' => 1, 'author' => 1, 'pdf' => 1, 'pdf_time' => 300, 'title' => 'PDF-newest'],
        ['id' => 2, 'author' => 1, 'pdf' => 1, 'pdf_time' => 200, 'title' => 'PDF-mid'],
        ['id' => 3, 'author' => 1, 'pdf' => 0, 'pdf_time' => 999, 'title' => 'Excluded: pdf=0'],
    ]);

    expect($this->service->homeLatestDumpFiles(1)->pluck('title')->all())->toBe(['PDF-newest'])
        ->and($this->service->homeLatestDumpFiles(3)->pluck('title')->all())->toBe(['PDF-newest', 'PDF-mid']);
});

it('homeLatestDumpFiles: is NOT khotabPdfDump() — no hidden filter, confirmed by a hidden=1 row still appearing (the two are deliberately separate methods, see the service\'s own docblock)', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_islamic_khotab')->insert(['id' => 1, 'author' => 1, 'pdf' => 1, 'pdf_time' => 100, 'hidden' => 1, 'title' => 'Hidden but pdf>0']);

    expect($this->service->homeLatestDumpFiles()->pluck('title')->all())->toBe(['Hidden but pdf>0']);
});

it('homeLatestFatawas: newest id first, respects $limit', function () {
    $db = DB::connection('main');
    $db->table('nuke_islamic_authors')->insert(['id' => 1, 'name' => 'A', 'prename' => 'Dr.']);
    $db->table('nuke_fatwa_questions')->insert([
        ['id' => 1, 'auther_id' => 1, 'question_text' => 'Older', 'general_question_id' => '5'],
        ['id' => 2, 'auther_id' => 1, 'question_text' => 'Newer', 'general_question_id' => '6'],
    ]);

    expect($this->service->homeLatestFatawas(1)->pluck('question_text')->all())->toBe(['Newer']);
});

it('homeCategory487: only main_cat=487 rows, newest id first, respects $limit', function () {
    $db = DB::connection('main');
    $db->table('nuke_w2a_cat')->insert([
        ['id' => 1, 'title' => 'Other cat', 'main_cat' => 1],
        ['id' => 613, 'title' => 'C-newer', 'main_cat' => 487],
        ['id' => 600, 'title' => 'C-older', 'main_cat' => 487],
    ]);

    expect($this->service->homeCategory487()->pluck('title')->all())->toBe(['C-newer', 'C-older']);
});

it('homeLatestTelawahs: LEFT JOIN preserves telawah rows even when the group is missing, newest id first', function () {
    $db = DB::connection('main');
    $db->table('nuke_telawah_groups')->insert(['id' => 1, 'title' => 'Group A']);
    $db->table('nuke_telawah_telawah')->insert([
        ['id' => 1, 'title' => 'T-older', 'group_id' => 1],
        ['id' => 2, 'title' => 'T-newer, orphaned group', 'group_id' => 999],
    ]);

    $results = $this->service->homeLatestTelawahs();

    expect($results->pluck('title')->all())->toBe(['T-newer, orphaned group', 'T-older'])
        ->and($results->firstWhere('title', 'T-older')->group_title)->toBe('Group A')
        ->and($results->firstWhere('title', 'T-newer, orphaned group')->group_title)->toBeNull();
});

it('homeSelectedAlbumImages: resolves the album via the nuke_options home_selected_album key, ordered by `order`, limit respected', function () {
    $db = DB::connection('main');
    $db->table('nuke_options')->insert(['option_name' => 'home_selected_album', 'option_value' => '5']);
    $db->table('nuke_albums_images')->insert([
        ['album_id' => 5, 'url' => 'second.jpg', 'order' => 2],
        ['album_id' => 5, 'url' => 'first.jpg', 'order' => 1],
        ['album_id' => 9, 'url' => 'wrong-album.jpg', 'order' => 0],
    ]);

    $result = $this->service->homeSelectedAlbumImages(1);

    expect($result['album_id'])->toBe(5)
        ->and($result['images']->pluck('url')->all())->toBe(['first.jpg']);
});

it('homeAnasheedByParent: filters by parent_id (NOT group_id — a same-named but different column on this table)', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Right parent', 'parent_id' => 158, 'group_id' => 1],
        ['id' => 2, 'title' => 'Wrong parent, matching group_id only', 'parent_id' => 1, 'group_id' => 158],
    ]);

    $results = $this->service->homeAnasheedByParent(158);

    expect($results->pluck('title')->all())->toBe(['Right parent']);
});

it('homeAnasheedByParent: parent=98 special case includes group 16 too, matching listvars()\'s own `98 -> [16,98]` substitution', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Parent 98', 'parent_id' => 98],
        ['id' => 2, 'title' => 'Parent 16 (included via the 98 special case)', 'parent_id' => 16],
        ['id' => 3, 'title' => 'Parent 17 (must NOT be included)', 'parent_id' => 17],
    ]);

    $results = $this->service->homeAnasheedByParent(98);

    expect($results->pluck('title')->sort()->values()->all())->toBe([
        'Parent 16 (included via the 98 special case)',
        'Parent 98',
    ]);
});

it('homeAnasheedByParent: ordering is newest id first, respects $limit', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 10, 'title' => 'Older', 'parent_id' => 12],
        ['id' => 20, 'title' => 'Newer', 'parent_id' => 12],
    ]);

    expect($this->service->homeAnasheedByParent(12, 1)->pluck('title')->all())->toBe(['Newer']);
});

it('homeTrendingAnasheed: INNER JOINs nuke_anasheed_advanced (rows without an advanced record are excluded, matching legacy\'s own implicit join), newest lastvisit first', function () {
    $db = DB::connection('main');
    $db->table('nuke_anasheed_anasheed')->insert([
        ['id' => 1, 'title' => 'Has advanced row, newer', 'lastvisit' => 200],
        ['id' => 2, 'title' => 'Has advanced row, older', 'lastvisit' => 100],
        ['id' => 3, 'title' => 'No advanced row - excluded', 'lastvisit' => 999],
    ]);
    $db->table('nuke_anasheed_advanced')->insert([
        ['id' => 1, 'adur' => '1:00'],
        ['id' => 2, 'adur' => '2:00'],
    ]);

    $results = $this->service->homeTrendingAnasheed();

    expect($results->pluck('title')->all())->toBe(['Has advanced row, newer', 'Has advanced row, older']);
});
